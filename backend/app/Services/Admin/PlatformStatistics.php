<?php

namespace App\Services\Admin;

use App\Enums\BusinessStatus;
use App\Enums\InvoiceStatus;
use App\Enums\UserStatus;
use App\Models\BillingInvoice;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\Queue;
use App\Models\User;
use App\Services\Billing\SubscriptionResolver;
use App\Services\Billing\SubscriptionState;
use Illuminate\Support\Carbon;

/**
 * The numbers of the admin dashboard (docs/ADMIN_DASHBOARD.md, "Overview"
 * and "Revenue").
 *
 * - Subscription states are computed now, with the same resolver as the
 *   API, so they are right even before the scheduler stored a change.
 * - Revenue counts paid invoices only: payments a platform admin approved
 *   and payments verified with the bank. Recorded payments awaiting
 *   approval, rejected and failed ones never count.
 * - Months are calendar months in UTC.
 */
final class PlatformStatistics
{
    public const STATES = ['trialing', 'paid', 'complimentary', 'past_due', 'expired', 'free'];

    public function __construct(private readonly SubscriptionResolver $resolver) {}

    /**
     * @return array{users: array<string, int>, businesses: array<string, int>, branches: int, queues: int, awaiting_approval: int}
     */
    public function counts(): array
    {
        $users = User::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $businesses = Business::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'users' => [
                'total' => (int) $users->sum(),
                'active' => (int) ($users[UserStatus::Active->value] ?? 0),
                'suspended' => (int) ($users[UserStatus::Suspended->value] ?? 0),
                'admins' => User::query()->where('is_platform_admin', true)->count(),
            ],
            'businesses' => [
                'total' => (int) $businesses->sum(),
                'active' => (int) ($businesses[BusinessStatus::Active->value] ?? 0),
                'inactive' => (int) ($businesses[BusinessStatus::Inactive->value] ?? 0),
                'suspended' => (int) ($businesses[BusinessStatus::Suspended->value] ?? 0),
                'awaiting_review' => Business::query()->whereNull('approved_at')->count(),
            ],
            'branches' => Branch::query()->whereHas('business')->count(),
            'queues' => Queue::query()->whereHas('branch.business')->count(),
            'awaiting_approval' => BillingInvoice::query()
                ->where('status', InvoiceStatus::Open->value)
                ->where('provider', 'manual')
                ->count(),
        ];
    }

    /**
     * Every business's subscription as it stands now.
     *
     * @return array{states: array<string, int>, plans: array<string, int>, overdue: list<array{business: Business, plan: string, grace_ends_at: ?Carbon}>, trials_ending: list<array{business: Business, plan: string, trial_ends_at: ?Carbon}>}
     */
    public function subscriptions(int $soonDays = 3, int $listLimit = 8): array
    {
        $states = array_fill_keys(self::STATES, 0);
        $plans = [];
        $overdue = [];
        $trialsEnding = [];
        $seen = 0;
        $now = now();

        BusinessSubscription::query()
            ->with(['plan.prices', 'scheduledPlan.prices', 'business'])
            ->whereHas('business')
            ->chunkById(500, function ($subscriptions) use (&$states, &$plans, &$overdue, &$trialsEnding, &$seen, $now, $soonDays, $listLimit): void {
                foreach ($subscriptions as $subscription) {
                    $state = $this->resolver->resolve($subscription, $now);
                    $seen++;
                    $key = $this->stateKey($state);
                    $states[$key]++;
                    $plans[$state->plan->code] = ($plans[$state->plan->code] ?? 0) + 1;

                    if ($key === 'past_due' && count($overdue) < $listLimit) {
                        $overdue[] = ['business' => $subscription->business, 'plan' => $state->subscribedPlan->code, 'grace_ends_at' => $state->graceEndsAt];
                    }
                    if ($key === 'trialing' && $state->trialEndsAt !== null && $state->trialEndsAt->lte($now->copy()->addDays($soonDays))
                        && count($trialsEnding) < $listLimit) {
                        $trialsEnding[] = ['business' => $subscription->business, 'plan' => $state->plan->code, 'trial_ends_at' => $state->trialEndsAt];
                    }
                }
            });

        // Businesses without a subscription record (older than Phase 08) are on the default plan.
        $without = Business::query()->count() - $seen;
        if ($without > 0) {
            $default = $this->resolver->resolve(null);
            $states['free'] += $without;
            $plans[$default->plan->code] = ($plans[$default->plan->code] ?? 0) + $without;
        }
        arsort($plans);

        return ['states' => $states, 'plans' => $plans, 'overdue' => $overdue, 'trials_ending' => $trialsEnding];
    }

    /**
     * Complimentary: a paid plan given by a platform admin (assignPlan),
     * with or without an end date. Paid: a plan period that was paid for.
     */
    public function stateKey(SubscriptionState $state): string
    {
        return match (true) {
            $state->status === SubscriptionState::TRIALING => 'trialing',
            $state->status === SubscriptionState::PAST_DUE => 'past_due',
            $state->status === SubscriptionState::EXPIRED => 'expired',
            $state->isComplimentary() => 'complimentary',
            $state->isPaid() && $state->subscription?->provider === 'admin' => 'complimentary',
            $state->isPaid() => 'paid',
            default => 'free',
        };
    }

    /**
     * New businesses per month, oldest first.
     *
     * @return array<string, int> "2026-11" => count
     */
    public function newBusinessesByMonth(int $months = 6): array
    {
        $series = $this->emptyMonths($months);
        Business::query()->withTrashed()
            ->where('created_at', '>=', array_key_first($series).'-01')
            ->pluck('created_at')
            ->each(function ($createdAt) use (&$series): void {
                $month = Carbon::parse($createdAt)->utc()->format('Y-m');
                if (array_key_exists($month, $series)) {
                    $series[$month]++;
                }
            });

        return $series;
    }

    /**
     * Revenue from paid invoices, per month and currency, split by source.
     *
     * @return array{months: list<string>, currencies: list<string>, by_month: array<string, array<string, array{manual: int, gateway: int, total: int, count: int}>>, by_plan: array<string, array<string, int>>, totals: array<string, array{manual: int, gateway: int, total: int, count: int}>}
     */
    public function revenue(int $months = 12): array
    {
        $monthKeys = array_keys($this->emptyMonths($months));
        $from = Carbon::parse($monthKeys[0].'-01', 'UTC')->startOfMonth();
        $byMonth = [];
        $byPlan = [];
        $totals = [];
        $currencies = [];

        BillingInvoice::query()
            ->with('plan:id,code')
            ->where('status', InvoiceStatus::Paid->value)
            ->where('paid_at', '>=', $from)
            ->chunkById(500, function ($invoices) use (&$byMonth, &$byPlan, &$totals, &$currencies): void {
                foreach ($invoices as $invoice) {
                    $month = $invoice->paid_at->copy()->utc()->format('Y-m');
                    $currency = $invoice->currency;
                    $source = $invoice->isGatewayVerified() ? 'gateway' : 'manual';
                    $currencies[$currency] = true;

                    $byMonth[$month][$currency] ??= ['manual' => 0, 'gateway' => 0, 'total' => 0, 'count' => 0];
                    $byMonth[$month][$currency][$source] += $invoice->amount_minor;
                    $byMonth[$month][$currency]['total'] += $invoice->amount_minor;
                    $byMonth[$month][$currency]['count']++;

                    $totals[$currency] ??= ['manual' => 0, 'gateway' => 0, 'total' => 0, 'count' => 0];
                    $totals[$currency][$source] += $invoice->amount_minor;
                    $totals[$currency]['total'] += $invoice->amount_minor;
                    $totals[$currency]['count']++;

                    $plan = $invoice->plan->code ?? '?';
                    $byPlan[$plan][$currency] = ($byPlan[$plan][$currency] ?? 0) + $invoice->amount_minor;
                }
            });

        $currencyList = array_keys($currencies);
        sort($currencyList);
        if ($currencyList === []) {
            $currencyList = [strtoupper((string) config('billing.currency'))];
        }

        return [
            'months' => $monthKeys,
            'currencies' => $currencyList,
            'by_month' => $byMonth,
            'by_plan' => $byPlan,
            'totals' => $totals,
        ];
    }

    /**
     * @return array<string, int> the last $months months, oldest first, at 0
     */
    private function emptyMonths(int $months): array
    {
        $months = max(1, min(36, $months));
        $series = [];
        $start = now()->utc()->startOfMonth()->subMonths($months - 1);
        for ($i = 0; $i < $months; $i++) {
            $series[$start->copy()->addMonths($i)->format('Y-m')] = 0;
        }

        return $series;
    }
}
