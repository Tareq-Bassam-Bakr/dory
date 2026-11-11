<?php

namespace App\Services\Billing;

use App\Enums\BillingPeriod;
use App\Models\BusinessSubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Carbon;

/**
 * Where a business's subscription stands at one moment (docs/SUBSCRIPTIONS.md).
 *
 * - plan: what the business is entitled to now (limits and features).
 * - subscribedPlan: the plan on record, e.g. the Pro plan of an expired
 *   subscription, while plan is already the free plan.
 * - status: trialing, active, past_due or expired, as of this moment, even
 *   when the scheduler has not stored the change yet.
 */
final readonly class SubscriptionState
{
    public const TRIALING = 'trialing';

    public const ACTIVE = 'active';

    public const PAST_DUE = 'past_due';

    public const EXPIRED = 'expired';

    public function __construct(
        public SubscriptionPlan $plan,
        public SubscriptionPlan $subscribedPlan,
        public string $status,
        public bool $billingEnabled,
        public ?Carbon $trialEndsAt = null,
        public ?Carbon $periodStart = null,
        public ?Carbon $periodEnd = null,
        public ?Carbon $graceEndsAt = null,
        public bool $cancelAtPeriodEnd = false,
        public ?SubscriptionPlan $scheduledPlan = null,
        public ?BillingPeriod $billingPeriod = null,
        public ?string $currency = null,
        public ?int $amountMinor = null,
        public ?BusinessSubscription $subscription = null,
    ) {}

    /**
     * A paid plan in force (active or in its grace period).
     */
    public function isPaid(): bool
    {
        return ! $this->subscribedPlan->is_free
            && in_array($this->status, [self::ACTIVE, self::PAST_DUE], true)
            && $this->periodEnd !== null;
    }

    /**
     * A paid plan given by a platform admin without an end date.
     */
    public function isComplimentary(): bool
    {
        return ! $this->subscribedPlan->is_free
            && $this->status === self::ACTIVE
            && $this->periodEnd === null;
    }
}
