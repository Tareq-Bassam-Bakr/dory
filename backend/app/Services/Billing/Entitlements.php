<?php

namespace App\Services\Billing;

use App\Enums\InvitationStatus;
use App\Enums\MembershipStatus;
use App\Enums\PlanFeature;
use App\Enums\PlanLimit;
use App\Exceptions\BillingException;
use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\BusinessMember;
use App\Models\BusinessSubscription;
use App\Models\Queue;

/**
 * The one place that decides what a business may add and use
 * (docs/SUBSCRIPTIONS.md, "Entitlements"). Every check runs on the server.
 *
 * - Limits apply to adding: a business over its limit after a downgrade
 *   keeps every branch, queue and member working, and cannot add more.
 * - Customers are never checked: joining queues and discovery are free.
 * - Seats are active members plus pending invitations, so invitations cannot
 *   be used to go past the limit.
 * - Nothing is cached: every check reads the subscription as it is now
 *   (one indexed query), so no long-lived instance can serve a stale plan.
 */
final class Entitlements
{
    public function __construct(private readonly SubscriptionResolver $resolver) {}

    public function state(Business $business): SubscriptionState
    {
        return $this->resolver->resolve(
            BusinessSubscription::query()
                ->with(['plan.prices', 'scheduledPlan.prices'])
                ->where('business_id', $business->getKey())
                ->first(),
        );
    }

    public function allows(Business $business, PlanFeature $feature): bool
    {
        return $this->state($business)->plan->hasFeature($feature);
    }

    public function ensureFeature(Business $business, PlanFeature $feature): void
    {
        $state = $this->state($business);
        if (! $state->plan->hasFeature($feature)) {
            throw BillingException::featureNotInPlan($feature, $state->plan);
        }
    }

    /**
     * Refuses adding one more of $limit when the plan's limit is reached.
     * Call inside the transaction that adds it: the business row is locked
     * so two requests cannot both pass with one place left.
     *
     * @param  bool  $countInvitations  Seats: also count pending invitations.
     */
    public function ensureCanAdd(Business $business, PlanLimit $limit, bool $countInvitations = true): void
    {
        Business::query()->withTrashed()->whereKey($business->getKey())->lockForUpdate()->value('id');

        $plan = $this->state($business)->plan;
        $max = $plan->limit($limit);
        if ($max === null) {
            return;
        }
        $used = $this->count($business, $limit, $countInvitations);
        if ($used + 1 > $max) {
            throw BillingException::limitReached($limit, $max, $used, $plan);
        }
    }

    /**
     * @return array<string, int>
     */
    public function usage(Business $business): array
    {
        $usage = [];
        foreach (PlanLimit::cases() as $limit) {
            $usage[$limit->value] = $this->count($business, $limit, true);
        }

        return $usage;
    }

    private function count(Business $business, PlanLimit $limit, bool $countInvitations): int
    {
        $id = $business->getKey();

        return match ($limit) {
            PlanLimit::Branches => Branch::query()->where('business_id', $id)->count(),
            PlanLimit::Queues => Queue::query()
                ->whereHas('branch', fn ($branches) => $branches->where('business_id', $id))
                ->count(),
            PlanLimit::Members => BusinessMember::query()
                ->where('business_id', $id)
                ->where('status', MembershipStatus::Active->value)
                ->count()
                + ($countInvitations ? BusinessInvitation::query()
                    ->where('business_id', $id)
                    ->where('status', InvitationStatus::Pending->value)
                    ->where('expires_at', '>', now())
                    ->count() : 0),
        };
    }
}
