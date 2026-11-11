<?php

namespace App\Services\Billing;

use App\Enums\BillingPeriod;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionStatus;
use App\Exceptions\BillingException;
use App\Models\BillingInvoice;
use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Every change of a business subscription, each one recorded in
 * subscription_events (docs/SUBSCRIPTIONS.md, "Lifecycle").
 *
 * Paid periods are prepaid: a payment (recorded by a platform admin, or
 * verified with the payment provider) buys one month or one year. Renewal is
 * the next payment; an unpaid period ends in a grace period, then the free
 * plan. Nothing is ever deleted: a business that falls back to the free plan
 * keeps its branches, queues, members and history.
 */
final class SubscriptionService
{
    private const SWEEP_CHUNK = 200;

    public function __construct(
        private readonly PlanCatalog $catalog,
        private readonly SubscriptionResolver $resolver,
    ) {}

    /**
     * A new business: the trial plan, or the free plan. Idempotent.
     */
    public function start(Business $business, ?User $owner = null): BusinessSubscription
    {
        $existing = BusinessSubscription::query()->where('business_id', $business->getKey())->first();
        if ($existing !== null) {
            return $existing;
        }

        $trial = $this->trialFor($owner);
        $subscription = new BusinessSubscription;
        $subscription->business_id = $business->getKey();
        if ($trial !== null) {
            $subscription->plan_id = $trial->getKey();
            $subscription->status = SubscriptionStatus::Trialing;
            $subscription->trial_ends_at = now()->addDays((int) config('billing.trial.days'));
        } else {
            $subscription->plan_id = $this->catalog->default()->getKey();
            $subscription->status = SubscriptionStatus::Active;
        }
        $subscription->saveOrFail();

        if ($trial !== null) {
            $this->log($subscription, SubscriptionEventType::TrialStarted, null, null, $owner, [
                'trial_ends_at' => $subscription->trial_ends_at->toIso8601String(),
            ]);
        } else {
            $this->log($subscription, SubscriptionEventType::Started, null, null, $owner);
        }

        return $subscription;
    }

    /**
     * A payment for one period of $plan: activation, renewal or upgrade.
     * Used for payments recorded by a platform admin and for verified online
     * payments ($invoice is then the checkout's open invoice).
     *
     * @param  array{provider: string, method: PaymentMethod, reference?: ?string, paid_at?: ?Carbon, notes?: ?string, recorded_by?: ?int}  $payment
     */
    public function recordPayment(
        Business $business,
        SubscriptionPlan $plan,
        BillingPeriod $period,
        string $currency,
        int $amountMinor,
        array $payment,
        ?User $actor = null,
        ?BillingInvoice $invoice = null,
    ): BillingInvoice {
        if ($plan->is_free || $amountMinor < 0) {
            throw BillingException::planNotAvailable();
        }

        return DB::transaction(function () use ($business, $plan, $period, $currency, $amountMinor, $payment, $actor, $invoice): BillingInvoice {
            $subscription = $this->locked($business);
            $paidAt = $payment['paid_at'] ?? now();
            $currency = strtoupper($currency);
            [$type, $start, $end, $meta] = $this->applyPaidPeriod($subscription, $plan, $period, $currency, $amountMinor, $payment['provider']);

            $invoice ??= new BillingInvoice;
            $invoice->forceFill([
                'business_id' => $business->getKey(),
                'subscription_id' => $subscription->getKey(),
                'plan_id' => $plan->getKey(),
                'billing_period' => $period,
                'currency' => $currency,
                'amount_minor' => $amountMinor,
                'status' => InvoiceStatus::Paid,
                'period_start' => $start,
                'period_end' => $end,
                'issued_at' => $invoice->issued_at ?? $paidAt,
                'paid_at' => $paidAt,
                'failed_at' => null,
                'failure_reason' => null,
                'provider' => $payment['provider'],
                'payment_method' => $payment['method'],
                'provider_reference' => $payment['reference'] ?? $invoice->provider_reference,
                'recorded_by' => $payment['recorded_by'] ?? null,
                'notes' => $payment['notes'] ?? $invoice->notes,
            ])->saveOrFail();
            $this->number($invoice);

            $this->log($subscription, $type, $meta['from_plan'], $meta['from_status'], $actor, [
                'invoice' => $invoice->number,
                'period_start' => $start->toIso8601String(),
                'period_end' => $end->toIso8601String(),
            ] + ($meta['credit_days'] > 0 ? ['credit_days' => $meta['credit_days']] : []));

            return $invoice;
        });
    }

    /**
     * A payment attempt that failed. The subscription itself changes only
     * when its period ends unpaid (sweep): a failed attempt ends nothing.
     */
    public function recordFailedPayment(Business $business, BillingInvoice $invoice, string $reason): void
    {
        DB::transaction(function () use ($business, $invoice, $reason): void {
            $subscription = $this->locked($business);
            $invoice->forceFill([
                'subscription_id' => $subscription->getKey(),
                'status' => InvoiceStatus::Failed,
                'failed_at' => now(),
                'failure_reason' => $reason,
            ])->saveOrFail();
            $this->number($invoice);
            $this->log($subscription, SubscriptionEventType::PaymentFailed, $subscription->plan_id, $subscription->status->value, null, [
                'invoice' => $invoice->number,
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Stops paying: a paid plan stays until the end of its paid period
     * (nothing is refunded); a trial, or a period already in its grace
     * days, ends now.
     */
    public function cancel(Business $business, User $actor): BusinessSubscription
    {
        return $this->change($business, function (BusinessSubscription $subscription, SubscriptionState $state) use ($actor): void {
            match (true) {
                $state->status === SubscriptionState::TRIALING => $this->endTrial($subscription, $actor, 'canceled'),
                $state->status === SubscriptionState::PAST_DUE => $this->expire($subscription, $actor, 'canceled'),
                $state->isComplimentary() => throw BillingException::changeNotAllowed('managed_by_admin'),
                ! $state->isPaid() => throw BillingException::changeNotAllowed('nothing_to_cancel'),
                $subscription->cancel_at_period_end => throw BillingException::changeNotAllowed('already_canceled'),
                default => $this->scheduleEnd($subscription, $actor),
            };
        });
    }

    /**
     * Undoes a cancellation or a scheduled downgrade before the period ends.
     */
    public function resume(Business $business, User $actor): BusinessSubscription
    {
        return $this->change($business, function (BusinessSubscription $subscription, SubscriptionState $state) use ($actor): void {
            $pending = $subscription->cancel_at_period_end || $subscription->scheduled_plan_id !== null;
            if ($state->status !== SubscriptionState::ACTIVE || ! $state->isPaid() || ! $pending) {
                throw BillingException::changeNotAllowed('nothing_to_resume');
            }

            $type = $subscription->cancel_at_period_end ? SubscriptionEventType::Resumed : SubscriptionEventType::DowngradeCanceled;
            $subscription->cancel_at_period_end = false;
            $subscription->canceled_at = null;
            $subscription->scheduled_plan_id = null;
            $subscription->scheduled_billing_period = null;
            $subscription->saveOrFail();
            $this->log($subscription, $type, $subscription->plan_id, $subscription->status->value, $actor);
        });
    }

    /**
     * Moves to a smaller plan. Paid time is never cut short: the change waits
     * for the end of the paid period. Bigger plans are bought (checkout).
     */
    public function downgrade(Business $business, SubscriptionPlan $target, ?BillingPeriod $period, User $actor): BusinessSubscription
    {
        if ($target->is_free) {
            return $this->cancel($business, $actor);
        }

        return $this->change($business, function (BusinessSubscription $subscription, SubscriptionState $state) use ($target, $period, $actor): void {
            if (! $target->is_active) {
                throw BillingException::planNotAvailable();
            }
            if ($state->isComplimentary()) {
                throw BillingException::changeNotAllowed('managed_by_admin');
            }
            if ($state->status !== SubscriptionState::ACTIVE || ! $state->isPaid()
                || $target->rank() >= $state->subscribedPlan->rank()) {
                throw BillingException::changeNotAllowed('use_checkout');
            }
            $period ??= $subscription->billing_period ?? BillingPeriod::Monthly;
            if ($subscription->currency !== null && $target->priceFor($period, $subscription->currency) === null) {
                throw BillingException::planNotAvailable();
            }

            $subscription->scheduled_plan_id = $target->getKey();
            $subscription->scheduled_billing_period = $period;
            $subscription->cancel_at_period_end = false;
            $subscription->canceled_at = null;
            $subscription->saveOrFail();
            $this->log($subscription, SubscriptionEventType::DowngradeScheduled, $subscription->plan_id, $subscription->status->value, $actor, [
                'to_plan' => $target->code,
                'billing_period' => $period->value,
                'at' => $subscription->current_period_end?->toIso8601String(),
            ]);
        });
    }

    // ----- Platform admin ---------------------------------------------------

    public function extendTrial(Business $business, int $days, ?SubscriptionPlan $plan, User $admin): BusinessSubscription
    {
        return $this->change($business, function (BusinessSubscription $subscription, SubscriptionState $state) use ($days, $plan, $admin): void {
            if ($state->status === SubscriptionState::ACTIVE && $state->isPaid()) {
                throw BillingException::changeNotAllowed('paid_plan_active');
            }
            $trialPlan = $plan
                ?? ($state->status === SubscriptionState::TRIALING ? $subscription->plan : $this->catalog->trial());
            if ($trialPlan === null || $trialPlan->is_free) {
                throw BillingException::planNotAvailable();
            }

            $fromPlan = (int) $subscription->plan_id;
            $fromStatus = $subscription->status->value;
            $base = $state->status === SubscriptionState::TRIALING ? $subscription->trial_ends_at->copy() : now();
            $this->clearPaidPeriod($subscription);
            $subscription->plan_id = $trialPlan->getKey();
            $subscription->status = SubscriptionStatus::Trialing;
            $subscription->trial_ends_at = $base->addDays($days);
            $subscription->ended_at = null;
            $subscription->saveOrFail();
            $this->log($subscription, SubscriptionEventType::TrialExtended, $fromPlan, $fromStatus, $admin, [
                'days' => $days,
                'trial_ends_at' => $subscription->trial_ends_at->toIso8601String(),
            ]);
        });
    }

    /**
     * Gives a plan without payment (support, partnerships). A paid plan
     * given until a date ends then, without a grace period; given without a
     * date, it stays until an admin changes it.
     */
    public function assignPlan(Business $business, SubscriptionPlan $plan, ?Carbon $until, User $admin, ?string $note = null): BusinessSubscription
    {
        return $this->change($business, function (BusinessSubscription $subscription) use ($plan, $until, $admin, $note): void {
            $fromPlan = (int) $subscription->plan_id;
            $fromStatus = $subscription->status->value;
            $this->clearPaidPeriod($subscription);
            $subscription->forceFill([
                'plan_id' => $plan->getKey(),
                'status' => SubscriptionStatus::Active,
                'trial_ends_at' => null,
                'current_period_start' => $plan->is_free ? null : now(),
                'current_period_end' => $plan->is_free ? null : $until,
                'cancel_at_period_end' => ! $plan->is_free && $until !== null,
                'ended_at' => null,
                'provider' => 'admin',
            ])->saveOrFail();
            $this->log($subscription, SubscriptionEventType::PlanAssigned, $fromPlan, $fromStatus, $admin, array_filter([
                'until' => $until?->toIso8601String(),
                'note' => $note,
            ]));
        });
    }

    public function expireNow(Business $business, User $admin, ?string $reason = null): BusinessSubscription
    {
        return $this->change($business, function (BusinessSubscription $subscription, SubscriptionState $state) use ($admin, $reason): void {
            if ($state->status === SubscriptionState::EXPIRED || $state->subscribedPlan->is_free) {
                throw BillingException::changeNotAllowed('already_expired');
            }
            $this->expire($subscription, $admin, $reason ?? 'admin');
        });
    }

    // ----- Scheduler ----------------------------------------------------------

    /**
     * Stores the transitions that time has caused: trials and paid periods
     * that ended, grace periods that ran out. Entitlements never wait for
     * this (SubscriptionResolver computes the same); it keeps the records and
     * the history right. Idempotent: each subscription is re-checked under a
     * lock. Returns how many changed.
     */
    public function sweep(?Carbon $at = null): int
    {
        $now = $at ?? now();
        $changed = 0;
        $after = 0;

        do {
            $ids = BusinessSubscription::query()
                ->where('id', '>', $after)
                ->where(fn ($due) => $due
                    ->where(fn ($q) => $q->where('status', SubscriptionStatus::Trialing->value)->where('trial_ends_at', '<=', $now))
                    ->orWhere(fn ($q) => $q->where('status', SubscriptionStatus::Active->value)
                        ->where('current_period_end', '<=', $now)
                        ->whereHas('plan', fn ($plans) => $plans->where('is_free', false)))
                    ->orWhere(fn ($q) => $q->where('status', SubscriptionStatus::PastDue->value)->where('grace_ends_at', '<=', $now)))
                ->orderBy('id')
                ->limit(self::SWEEP_CHUNK)
                ->pluck('id');

            foreach ($ids as $id) {
                $changed += DB::transaction(fn (): int => $this->transition((int) $id, $now));
                $after = (int) $id;
            }
        } while ($ids->count() === self::SWEEP_CHUNK);

        return $changed;
    }

    private function transition(int $id, Carbon $now): int
    {
        $subscription = BusinessSubscription::query()->with(['plan.prices', 'scheduledPlan.prices'])->lockForUpdate()->find($id);
        if ($subscription === null) {
            return 0;
        }
        $state = $this->resolver->resolve($subscription, $now);
        $fromPlan = (int) $subscription->plan_id;
        $fromStatus = $subscription->status->value;
        $stored = $subscription->status;

        if ($stored === SubscriptionStatus::Trialing && $state->status === SubscriptionState::EXPIRED) {
            $subscription->status = SubscriptionStatus::Expired;
            $subscription->ended_at = $subscription->trial_ends_at;
            $subscription->saveOrFail();
            $this->log($subscription, SubscriptionEventType::TrialEnded, $fromPlan, $fromStatus, null, ['reason' => 'trial_over']);
        } elseif ($stored === SubscriptionStatus::Active && $state->status === SubscriptionState::PAST_DUE) {
            // A scheduled downgrade takes effect here: the grace days and the
            // next payment are for the smaller plan.
            $next = $state->subscribedPlan;
            $period = $subscription->scheduled_billing_period ?? $subscription->billing_period;
            if ((int) $next->getKey() !== $fromPlan && $period !== null && $subscription->currency !== null) {
                $subscription->amount_minor = $next->priceFor($period, $subscription->currency)?->amount_minor;
            }
            $subscription->plan_id = $next->getKey();
            $subscription->billing_period = $period;
            $subscription->status = SubscriptionStatus::PastDue;
            $subscription->grace_ends_at = $state->graceEndsAt;
            $subscription->scheduled_plan_id = null;
            $subscription->scheduled_billing_period = null;
            $subscription->saveOrFail();
            $this->log($subscription, SubscriptionEventType::PastDue, $fromPlan, $fromStatus, null, [
                'grace_ends_at' => $state->graceEndsAt?->toIso8601String(),
            ]);
        } elseif (in_array($stored, [SubscriptionStatus::Active, SubscriptionStatus::PastDue], true)
            && $state->status === SubscriptionState::EXPIRED) {
            $canceled = $stored === SubscriptionStatus::Active
                && ($subscription->cancel_at_period_end || ($subscription->scheduledPlan?->is_free ?? false));
            $subscription->status = SubscriptionStatus::Expired;
            $subscription->ended_at = match (true) {
                $subscription->grace_ends_at !== null => $subscription->grace_ends_at,
                $canceled => $subscription->current_period_end,
                default => $subscription->current_period_end?->copy()->addDays(max(0, (int) config('billing.grace_days'))),
            } ?? $now;
            $subscription->scheduled_plan_id = null;
            $subscription->scheduled_billing_period = null;
            $subscription->saveOrFail();
            $this->log($subscription, SubscriptionEventType::Expired, $fromPlan, $fromStatus, null, [
                'reason' => $canceled ? 'canceled' : 'unpaid',
            ]);
        } else {
            return 0;
        }

        return 1;
    }

    // ----- Internals ------------------------------------------------------------

    /**
     * Runs $apply on the locked subscription and its state now.
     *
     * @param  callable(BusinessSubscription, SubscriptionState): void  $apply
     */
    private function change(Business $business, callable $apply): BusinessSubscription
    {
        return DB::transaction(function () use ($business, $apply): BusinessSubscription {
            $subscription = $this->locked($business);
            $apply($subscription, $this->resolver->resolve($subscription));

            return $subscription->refresh()->load(['plan.prices', 'scheduledPlan.prices']);
        });
    }

    /**
     * The business's subscription, locked, inside the caller's transaction.
     * The business row is locked first, so changes of one business never
     * interleave. Businesses older than subscriptions get the free plan here.
     */
    private function locked(Business $business): BusinessSubscription
    {
        Business::query()->withTrashed()->whereKey($business->getKey())->lockForUpdate()->value('id');

        $subscription = BusinessSubscription::query()
            ->with(['plan.prices', 'scheduledPlan.prices'])
            ->where('business_id', $business->getKey())
            ->lockForUpdate()
            ->first();
        if ($subscription !== null) {
            return $subscription;
        }

        $subscription = new BusinessSubscription;
        $subscription->business_id = $business->getKey();
        $subscription->plan_id = $this->catalog->default()->getKey();
        $subscription->status = SubscriptionStatus::Active;
        $subscription->saveOrFail();
        $this->log($subscription, SubscriptionEventType::Started, null, null, null);

        return $subscription->load(['plan.prices', 'scheduledPlan.prices']);
    }

    /**
     * The trial a new business of $owner gets, if any.
     */
    private function trialFor(?User $owner): ?SubscriptionPlan
    {
        $trial = config('billing.enabled') ? $this->catalog->trial() : null;
        if ($trial === null || $trial->is_free) {
            return null;
        }
        if ($owner !== null && config('billing.trial.once_per_owner')
            && SubscriptionEvent::query()
                ->where('type', SubscriptionEventType::TrialStarted->value)
                ->where('actor_id', $owner->getKey())
                ->exists()) {
            return null;
        }

        return $trial;
    }

    /**
     * Applies one paid period of $plan to the locked subscription.
     *
     * - Same plan while paid: renewal. The new period follows the current one,
     *   also when paid early or during the grace days.
     * - Bigger plan while paid: upgrade, now. The unused part of the current
     *   period becomes extra time on the new plan (same currency only).
     * - During a trial: the remaining trial days are kept.
     * - Smaller plan while a bigger one is paid: refused; downgrades are
     *   scheduled for the end of the period (downgrade()).
     *
     * @return array{0: SubscriptionEventType, 1: Carbon, 2: Carbon, 3: array{from_plan: int, from_status: string, credit_days: float}}
     */
    private function applyPaidPeriod(
        BusinessSubscription $subscription,
        SubscriptionPlan $plan,
        BillingPeriod $period,
        string $currency,
        int $amount,
        string $provider,
    ): array {
        $now = now();
        $state = $this->resolver->resolve($subscription, $now);
        $current = $state->subscribedPlan;
        $samePlan = (int) $plan->getKey() === (int) $current->getKey();
        $fromPlan = (int) $subscription->plan_id;
        $fromStatus = $subscription->status->value;
        $credit = 0;
        $periodStart = null;

        if ($state->isPaid() && $samePlan) {
            $type = SubscriptionEventType::Renewed;
            $start = $subscription->current_period_end->copy();
            if ($period->endFrom($start)->lte($now)) {
                $start = $now->copy();
            }
            // While the current period runs, it keeps its start; the new
            // period is appended to it.
            $periodStart = $state->status === SubscriptionState::ACTIVE ? $subscription->current_period_start : $start;
        } elseif ($state->isPaid() && $state->status === SubscriptionState::ACTIVE && $plan->rank() < $current->rank()) {
            throw BillingException::changeNotAllowed('use_downgrade');
        } elseif ($state->isPaid() && $state->status === SubscriptionState::ACTIVE) {
            $type = SubscriptionEventType::Upgraded;
            // A paid period that has not begun yet (trial days kept) still waits.
            $start = $subscription->current_period_start !== null && $subscription->current_period_start->gt($now)
                ? $subscription->current_period_start->copy()
                : $now->copy();
            $credit = $this->unusedSeconds($subscription, $currency, $amount, $period, $start);
        } else {
            $type = $state->isPaid() && $plan->rank() > $current->rank()
                ? SubscriptionEventType::Upgraded
                : SubscriptionEventType::Activated;
            $start = $state->status === SubscriptionState::TRIALING && $subscription->trial_ends_at?->gt($now)
                ? $subscription->trial_ends_at->copy()
                : $now->copy();
        }
        $end = $period->endFrom($start)->addSeconds($credit);

        $subscription->forceFill([
            'plan_id' => $plan->getKey(),
            'status' => SubscriptionStatus::Active,
            'billing_period' => $period,
            'currency' => $currency,
            'amount_minor' => $amount,
            'current_period_start' => $periodStart ?? $start,
            'current_period_end' => $end,
            'trial_ends_at' => null,
            'grace_ends_at' => null,
            'cancel_at_period_end' => false,
            'canceled_at' => null,
            'ended_at' => null,
            'scheduled_plan_id' => null,
            'scheduled_billing_period' => null,
            'provider' => $provider,
        ])->saveOrFail();
        $subscription->setRelation('plan', $plan);

        return [$type, $start, $end, [
            'from_plan' => $fromPlan,
            'from_status' => $fromStatus,
            'credit_days' => round($credit / 86400, 1),
        ]];
    }

    /**
     * Seconds of the new plan worth the unused paid time of the current one.
     */
    private function unusedSeconds(BusinessSubscription $subscription, string $currency, int $amount, BillingPeriod $period, Carbon $start): int
    {
        $paid = (int) $subscription->amount_minor;
        $periodStart = $subscription->current_period_start;
        $periodEnd = $subscription->current_period_end;
        if ($subscription->currency !== $currency || $paid <= 0 || $amount <= 0
            || $subscription->billing_period === null || $periodStart === null || $periodEnd === null) {
            return 0;
        }

        $from = now()->max($periodStart);
        if ($periodEnd->lte($from)) {
            return 0;
        }
        $left = $from->diffInSeconds($periodEnd, true);
        $onePeriod = max(1, $periodStart->diffInSeconds($subscription->billing_period->endFrom($periodStart), true));
        $unused = $paid * $left / $onePeriod;

        return (int) floor($unused / $amount * $start->diffInSeconds($period->endFrom($start), true));
    }

    private function endTrial(BusinessSubscription $subscription, ?User $actor, string $reason): void
    {
        $fromPlan = (int) $subscription->plan_id;
        $fromStatus = $subscription->status->value;
        $this->clearPaidPeriod($subscription);
        $subscription->plan_id = $this->catalog->default()->getKey();
        $subscription->status = SubscriptionStatus::Active;
        $subscription->ended_at = now();
        $subscription->saveOrFail();
        $this->log($subscription, SubscriptionEventType::TrialEnded, $fromPlan, $fromStatus, $actor, ['reason' => $reason]);
    }

    private function scheduleEnd(BusinessSubscription $subscription, User $actor): void
    {
        $fromStatus = $subscription->status->value;
        $subscription->cancel_at_period_end = true;
        $subscription->canceled_at = now();
        $subscription->scheduled_plan_id = null;
        $subscription->scheduled_billing_period = null;
        $subscription->saveOrFail();
        $this->log($subscription, SubscriptionEventType::Canceled, $subscription->plan_id, $fromStatus, $actor, [
            'ends_at' => $subscription->current_period_end?->toIso8601String(),
        ]);
    }

    private function expire(BusinessSubscription $subscription, ?User $actor, string $reason): void
    {
        $fromPlan = (int) $subscription->plan_id;
        $fromStatus = $subscription->status->value;
        $subscription->status = SubscriptionStatus::Expired;
        $subscription->ended_at = now();
        $subscription->cancel_at_period_end = false;
        $subscription->scheduled_plan_id = null;
        $subscription->scheduled_billing_period = null;
        $subscription->saveOrFail();
        $this->log($subscription, SubscriptionEventType::Expired, $fromPlan, $fromStatus, $actor, ['reason' => $reason]);
    }

    private function clearPaidPeriod(BusinessSubscription $subscription): void
    {
        $subscription->forceFill([
            'billing_period' => null,
            'currency' => null,
            'amount_minor' => null,
            'current_period_start' => null,
            'current_period_end' => null,
            'grace_ends_at' => null,
            'cancel_at_period_end' => false,
            'canceled_at' => null,
            'scheduled_plan_id' => null,
            'scheduled_billing_period' => null,
        ]);
    }

    private function number(BillingInvoice $invoice): void
    {
        if ($invoice->number === null) {
            $invoice->number = sprintf('DORY-%s-%06d', $invoice->issued_at->format('Y'), $invoice->getKey());
            $invoice->saveOrFail();
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function log(
        BusinessSubscription $subscription,
        SubscriptionEventType $type,
        ?int $fromPlan,
        ?string $fromStatus,
        ?User $actor,
        array $metadata = [],
    ): void {
        SubscriptionEvent::query()->create([
            'business_id' => $subscription->business_id,
            'subscription_id' => $subscription->getKey(),
            'type' => $type,
            'from_plan_id' => $fromPlan,
            'to_plan_id' => $subscription->plan_id,
            'from_status' => $fromStatus,
            'to_status' => $subscription->status->value,
            'actor_id' => $actor?->getKey(),
            'metadata' => $metadata === [] ? null : $metadata,
            'occurred_at' => now(),
        ]);
    }
}
