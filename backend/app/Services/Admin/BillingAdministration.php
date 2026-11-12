<?php

namespace App\Services\Admin;

use App\Enums\BillingPeriod;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PlatformAuditAction;
use App\Exceptions\AdminActionException;
use App\Exceptions\BillingException;
use App\Models\BillingInvoice;
use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Billing\PlanCatalog;
use App\Services\Billing\SubscriptionService;
use App\Services\Platform\PlatformAuditLogger;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Billing operations of platform admins (docs/ADMIN_DASHBOARD.md,
 * "Billing"), shared by the admin API and the dashboard. Authorization
 * happens before these methods are called. Every change goes through the
 * subscription lifecycle (SubscriptionService) and the platform audit log.
 *
 * Payments received outside DORY take two steps: an admin records what
 * arrived (nothing changes yet), then an admin approves it after checking
 * the bank statement or the cash book. Only approved payments start a plan
 * and count as revenue. With four-eyes approval on, the approver must be
 * another admin.
 */
final class BillingAdministration
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly PlanCatalog $catalog,
        private readonly PlatformAuditLogger $audit,
    ) {}

    // ----- Plans ----------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $attributes  Validated plan fields.
     * @param  list<array{billing_period: string, currency: string, amount_minor: int, is_active?: bool}>  $prices
     */
    public function createPlan(array $attributes, array $prices, User $admin): SubscriptionPlan
    {
        $plan = DB::transaction(function () use ($attributes, $prices, $admin): SubscriptionPlan {
            $plan = new SubscriptionPlan;
            $plan->forceFill($attributes + ['is_active' => true, 'sort_order' => 0])->saveOrFail();
            $this->savePrices($plan, $prices);
            $this->audit->record(PlatformAuditAction::PlanCreated, $admin, $plan, [
                'code' => $plan->code,
                'limits' => $this->limits($plan),
                'prices' => $this->priceList($plan->load('prices')),
            ]);

            return $plan;
        });
        $this->catalog->forget();

        return $plan->load('prices');
    }

    /**
     * @param  array<string, mixed>  $attributes  Validated plan fields (code and is_free never change).
     * @param  list<array{billing_period: string, currency: string, amount_minor: int, is_active?: bool}>  $prices
     */
    public function updatePlan(SubscriptionPlan $plan, array $attributes, array $prices, User $admin): SubscriptionPlan
    {
        unset($attributes['code'], $attributes['is_free']);

        DB::transaction(function () use ($plan, $attributes, $prices, $admin): void {
            $locked = SubscriptionPlan::query()->with('prices')->whereKey($plan->getKey())->lockForUpdate()->firstOrFail();
            $before = $locked->only(array_keys($attributes));
            $pricesBefore = $this->priceList($locked);

            $locked->forceFill($attributes)->saveOrFail();
            $this->savePrices($locked, $prices);
            $pricesAfter = $this->priceList($locked->load('prices'));

            $changes = [];
            foreach ($attributes as $key => $value) {
                $old = $before[$key] ?? null;
                $new = $locked->getAttribute($key);
                if ($old !== $new) {
                    $changes[$key] = ['from' => $old, 'to' => $new];
                }
            }
            if ($pricesBefore !== $pricesAfter) {
                $changes['prices'] = ['from' => $pricesBefore, 'to' => $pricesAfter];
            }
            if ($changes !== []) {
                $this->audit->record(PlatformAuditAction::PlanUpdated, $admin, $locked, ['code' => $locked->code, 'changes' => $changes]);
            }
        });
        $this->catalog->forget();

        return $plan->refresh()->load('prices');
    }

    // ----- Payments received outside DORY ---------------------------------------------

    /**
     * Records a payment as received. Nothing changes until it is approved.
     *
     * @param  array{plan: SubscriptionPlan, billing_period: BillingPeriod, currency: string, amount_minor?: ?int, method: PaymentMethod, reference?: ?string, received_at?: ?Carbon, notes?: ?string}  $payment
     */
    public function recordPayment(Business $business, array $payment, User $admin): BillingInvoice
    {
        $this->ensureNotArchived($business);
        $plan = $payment['plan'];
        $currency = strtoupper($payment['currency']);
        $amount = $payment['amount_minor'] ?? $plan->priceFor($payment['billing_period'], $currency)?->amount_minor;
        if ($plan->is_free || $amount === null) {
            throw BillingException::planNotAvailable();
        }

        return DB::transaction(function () use ($business, $payment, $plan, $currency, $amount, $admin): BillingInvoice {
            $invoice = new BillingInvoice;
            $invoice->forceFill([
                'business_id' => $business->getKey(),
                'subscription_id' => BusinessSubscription::query()->where('business_id', $business->getKey())->value('id'),
                'plan_id' => $plan->getKey(),
                'billing_period' => $payment['billing_period'],
                'currency' => $currency,
                'amount_minor' => (int) $amount,
                'status' => InvoiceStatus::Open,
                'issued_at' => now(),
                'received_at' => $payment['received_at'] ?? now(),
                'provider' => 'manual',
                'payment_method' => $payment['method'],
                'provider_reference' => $payment['reference'] ?? null,
                'recorded_by' => $admin->getKey(),
                'notes' => $payment['notes'] ?? null,
            ])->saveOrFail();

            $this->audit->record(PlatformAuditAction::PaymentRecorded, $admin, $invoice, [
                'plan' => $plan->code,
                'billing_period' => $payment['billing_period']->value,
                'amount' => Money::decimal((int) $amount, $currency).' '.$currency,
                'method' => $payment['method']->value,
                'reference' => $payment['reference'] ?? null,
            ], $business);

            return $invoice;
        });
    }

    /**
     * Approves a recorded payment: the plan period starts (or renews,
     * upgrades), and the payment becomes a paid, numbered invoice.
     */
    public function approvePayment(BillingInvoice $invoice, User $admin): BillingInvoice
    {
        return DB::transaction(function () use ($invoice, $admin): BillingInvoice {
            $locked = BillingInvoice::query()->with(['plan.prices', 'business'])->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->awaitsApproval()) {
                throw AdminActionException::notAllowed('not_awaiting_approval');
            }
            if (config('billing.manual_payments.four_eyes') && (int) $locked->recorded_by === (int) $admin->getKey()) {
                throw AdminActionException::forbidden('needs_another_admin');
            }
            $this->ensureNotArchived($locked->business);

            $this->subscriptions->recordPayment(
                $locked->business,
                $locked->plan,
                $locked->billing_period,
                $locked->currency,
                $locked->amount_minor,
                [
                    'provider' => 'manual',
                    'method' => $locked->payment_method ?? PaymentMethod::Other,
                    'reference' => $locked->provider_reference,
                    'paid_at' => $locked->received_at ?? now(),
                    'notes' => $locked->notes,
                    'recorded_by' => $locked->recorded_by,
                ],
                $admin,
                $locked,
            );
            $locked->forceFill(['reviewed_by' => $admin->getKey(), 'reviewed_at' => now()])->saveOrFail();

            $this->audit->record(PlatformAuditAction::PaymentApproved, $admin, $locked, [
                'number' => $locked->number,
                'plan' => $locked->plan->code,
                'amount' => Money::decimal($locked->amount_minor, $locked->currency).' '.$locked->currency,
                'period_end' => $locked->period_end?->toIso8601String(),
            ], $locked->business);

            return $locked;
        });
    }

    public function rejectPayment(BillingInvoice $invoice, User $admin, string $reason): BillingInvoice
    {
        return DB::transaction(function () use ($invoice, $admin, $reason): BillingInvoice {
            $locked = BillingInvoice::query()->with('business')->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->awaitsApproval()) {
                throw AdminActionException::notAllowed('not_awaiting_approval');
            }
            $locked->forceFill([
                'status' => InvoiceStatus::Void,
                'failure_reason' => mb_substr($reason, 0, 120),
                'reviewed_by' => $admin->getKey(),
                'reviewed_at' => now(),
            ])->saveOrFail();

            $this->audit->record(PlatformAuditAction::PaymentRejected, $admin, $locked, ['reason' => $reason], $locked->business);

            return $locked;
        });
    }

    // ----- Subscriptions --------------------------------------------------------------------

    public function extendTrial(Business $business, int $days, ?SubscriptionPlan $plan, User $admin): BusinessSubscription
    {
        $this->ensureNotArchived($business);
        $subscription = $this->subscriptions->extendTrial($business, $days, $plan, $admin);
        $this->audit->record(PlatformAuditAction::TrialExtended, $admin, $business, [
            'days' => $days,
            'plan' => $subscription->plan?->code,
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
        ]);

        return $subscription;
    }

    public function assignPlan(Business $business, SubscriptionPlan $plan, ?Carbon $until, User $admin, ?string $note = null): BusinessSubscription
    {
        $this->ensureNotArchived($business);
        $subscription = $this->subscriptions->assignPlan($business, $plan, $until, $admin, $note);
        $this->audit->record(PlatformAuditAction::PlanAssigned, $admin, $business, array_filter([
            'plan' => $plan->code,
            'until' => $until?->toIso8601String(),
            'note' => $note,
        ]));

        return $subscription;
    }

    public function endSubscription(Business $business, User $admin, ?string $reason = null): BusinessSubscription
    {
        $subscription = $this->subscriptions->expireNow($business, $admin, $reason);
        $this->audit->record(PlatformAuditAction::SubscriptionEnded, $admin, $business, array_filter(['reason' => $reason]));

        return $subscription;
    }

    /**
     * On the owner's behalf: a paid plan stops at the end of its period, a
     * trial or an overdue period ends now (SubscriptionService::cancel).
     */
    public function cancel(Business $business, User $admin): BusinessSubscription
    {
        $this->ensureNotArchived($business);
        $subscription = $this->subscriptions->cancel($business, $admin);
        $this->audit->record(PlatformAuditAction::SubscriptionCanceled, $admin, $business, [
            'ends_at' => $subscription->current_period_end?->toIso8601String(),
        ]);

        return $subscription;
    }

    public function resume(Business $business, User $admin): BusinessSubscription
    {
        $this->ensureNotArchived($business);
        $subscription = $this->subscriptions->resume($business, $admin);
        $this->audit->record(PlatformAuditAction::SubscriptionResumed, $admin, $business);

        return $subscription;
    }

    // ----- Internals ---------------------------------------------------------------------------

    /**
     * An archived (deleted) business keeps its history, but gets no new plan,
     * trial or payment. Ending its subscription stays possible.
     */
    private function ensureNotArchived(?Business $business): void
    {
        if ($business === null || $business->trashed()) {
            throw AdminActionException::notAllowed('business_archived');
        }
    }

    /**
     * Adds or changes prices by period and currency. Prices are never
     * deleted (invoices refer to what was charged); deactivate them instead.
     *
     * @param  list<array{billing_period: string, currency: string, amount_minor: int, is_active?: bool}>  $prices
     */
    private function savePrices(SubscriptionPlan $plan, array $prices): void
    {
        foreach ($prices as $price) {
            $plan->prices()->updateOrCreate(
                [
                    'billing_period' => $price['billing_period'],
                    'currency' => strtoupper($price['currency']),
                ],
                [
                    'amount_minor' => (int) $price['amount_minor'],
                    'is_active' => (bool) ($price['is_active'] ?? true),
                ],
            );
        }
    }

    /**
     * @return array<string, ?int>
     */
    private function limits(SubscriptionPlan $plan): array
    {
        return ['branches' => $plan->max_branches, 'queues' => $plan->max_queues, 'members' => $plan->max_members];
    }

    /**
     * @return array<string, string>
     */
    private function priceList(SubscriptionPlan $plan): array
    {
        $list = [];
        foreach ($plan->prices as $price) {
            $list[$price->billing_period->value.' '.$price->currency] = Money::decimal($price->amount_minor, $price->currency)
                .($price->is_active ? '' : ' (inactive)');
        }
        ksort($list);

        return $list;
    }
}
