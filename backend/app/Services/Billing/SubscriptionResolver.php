<?php

namespace App\Services\Billing;

use App\Enums\SubscriptionStatus;
use App\Models\BusinessSubscription;
use Illuminate\Support\Carbon;

/**
 * Turns a stored subscription into its state at a given moment
 * (docs/SUBSCRIPTIONS.md, "Lifecycle"). The scheduler stores the same
 * transitions (SubscriptionService::sweep), but entitlements never wait for
 * it: a trial that ended a minute ago already gives the free plan.
 *
 *   trialing ── trial ends ───────────────────────────────► expired (free)
 *   active (paid) ── period ends, cancelled ───────────────► expired (free)
 *              └──── period ends, unpaid ──► past_due ──────► expired (free)
 *                    (scheduled downgrade      (plan kept until
 *                     applies here)             grace ends)
 */
final class SubscriptionResolver
{
    public function __construct(private readonly PlanCatalog $catalog) {}

    public function resolve(?BusinessSubscription $subscription, ?Carbon $at = null): SubscriptionState
    {
        $now = $at ?? now();
        $enabled = (bool) config('billing.enabled');
        $free = $this->catalog->default();

        if ($subscription === null) {
            return new SubscriptionState(
                plan: $enabled ? $free : $this->catalog->unlimited(),
                subscribedPlan: $free,
                status: SubscriptionState::ACTIVE,
                billingEnabled: $enabled,
            );
        }

        $plan = $subscription->plan;
        $scheduled = $subscription->scheduledPlan;
        $graceDays = max(0, (int) config('billing.grace_days'));
        $periodEnd = $subscription->current_period_end;

        [$status, $effective, $grace] = match ($subscription->status) {
            SubscriptionStatus::Trialing => $subscription->trial_ends_at !== null && $subscription->trial_ends_at->gt($now)
                ? [SubscriptionState::TRIALING, $plan, null]
                : [SubscriptionState::EXPIRED, $free, null],

            SubscriptionStatus::Active => match (true) {
                // The free plan, or a plan given by a platform admin without an end.
                $plan->is_free || $periodEnd === null => [SubscriptionState::ACTIVE, $plan, null],
                $periodEnd->gt($now) => [SubscriptionState::ACTIVE, $plan, null],
                $subscription->cancel_at_period_end || ($scheduled?->is_free ?? false) => [SubscriptionState::EXPIRED, $free, null],
                $periodEnd->copy()->addDays($graceDays)->gt($now) => [
                    SubscriptionState::PAST_DUE,
                    $scheduled ?? $plan,
                    $periodEnd->copy()->addDays($graceDays),
                ],
                default => [SubscriptionState::EXPIRED, $free, null],
            },

            SubscriptionStatus::PastDue => $subscription->grace_ends_at !== null && $subscription->grace_ends_at->gt($now)
                ? [SubscriptionState::PAST_DUE, $plan, $subscription->grace_ends_at]
                : [SubscriptionState::EXPIRED, $free, null],

            SubscriptionStatus::Expired => [SubscriptionState::EXPIRED, $free, null],
        };

        return new SubscriptionState(
            plan: $enabled ? $effective : $this->catalog->unlimited(),
            subscribedPlan: $status === SubscriptionState::PAST_DUE ? $effective : $plan,
            status: $status,
            billingEnabled: $enabled,
            trialEndsAt: $subscription->trial_ends_at,
            periodStart: $subscription->current_period_start,
            periodEnd: $periodEnd,
            graceEndsAt: $grace,
            cancelAtPeriodEnd: $subscription->cancel_at_period_end,
            scheduledPlan: $status === SubscriptionState::ACTIVE ? $scheduled : null,
            billingPeriod: $subscription->billing_period,
            currency: $subscription->currency,
            amountMinor: $subscription->amount_minor,
            subscription: $subscription,
        );
    }
}
