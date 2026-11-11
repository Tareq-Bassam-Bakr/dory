<?php

namespace App\Http\Resources\Billing;

use App\Enums\PlanLimit;
use App\Services\Billing\SubscriptionState;
use App\Services\Business\BusinessAbilities;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Where a business's subscription stands (docs/SUBSCRIPTIONS.md): the plan
 * in force, its limits next to what the business uses, and what the viewer
 * may do. Built from SubscriptionState, so it is right even before the
 * scheduler stored a transition.
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @param  array<string, int>  $usage
     */
    public function __construct(
        private readonly SubscriptionState $state,
        private readonly array $usage,
        private readonly ?BusinessAbilities $abilities,
        private readonly bool $paymentsAvailable,
        private readonly string $provider,
    ) {
        parent::__construct($state);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $state = $this->state;
        $locale = SubscriptionPlanResource::locale($request);
        $limits = [];
        foreach (PlanLimit::cases() as $limit) {
            $limits[$limit->value] = $state->plan->limit($limit);
        }

        return [
            'status' => $state->status,
            'billing_enabled' => $state->billingEnabled,
            // In force now (limits and features).
            'plan' => (new SubscriptionPlanResource($state->plan))->toArray($request),
            // On record: e.g. the Pro plan of a subscription that expired.
            'subscribed_plan' => [
                'code' => $state->subscribedPlan->code,
                'name' => $state->subscribedPlan->name($locale),
                'names' => ['en' => $state->subscribedPlan->name_en, 'ar' => $state->subscribedPlan->name_ar],
                'is_free' => $state->subscribedPlan->is_free,
            ],
            'is_paid' => $state->isPaid(),
            'is_complimentary' => $state->isComplimentary(),
            'trial_ends_at' => $state->status === SubscriptionState::TRIALING ? $state->trialEndsAt?->toIso8601String() : null,
            'current_period_start' => $state->isPaid() || $state->isComplimentary() ? $state->periodStart?->toIso8601String() : null,
            'current_period_end' => $state->isPaid() ? $state->periodEnd?->toIso8601String() : null,
            'grace_ends_at' => $state->graceEndsAt?->toIso8601String(),
            'cancel_at_period_end' => $state->status === SubscriptionState::ACTIVE && $state->isPaid() && $state->cancelAtPeriodEnd,
            'scheduled_plan' => $state->scheduledPlan === null ? null : [
                'code' => $state->scheduledPlan->code,
                'name' => $state->scheduledPlan->name($locale),
                'names' => ['en' => $state->scheduledPlan->name_en, 'ar' => $state->scheduledPlan->name_ar],
            ],
            'scheduled_billing_period' => $state->scheduledPlan === null ? null : $state->subscription?->scheduled_billing_period?->value,
            'billing_period' => $state->isPaid() ? $state->billingPeriod?->value : null,
            'currency' => $state->isPaid() ? $state->currency : null,
            'amount_minor' => $state->isPaid() ? $state->amountMinor : null,
            'amount' => $state->isPaid() && $state->amountMinor !== null && $state->currency !== null
                ? Money::decimal($state->amountMinor, $state->currency)
                : null,
            'limits' => $limits,
            'usage' => $this->usage,
            'features' => array_values($state->plan->features ?? []),
            'payments' => [
                'online' => $this->paymentsAvailable,
                'provider' => $this->provider,
            ],
            'permissions' => [
                'manage_billing' => $this->abilities?->canManageBilling() ?? false,
                'view_billing' => $this->abilities?->canViewBilling() ?? false,
            ],
        ];
    }
}
