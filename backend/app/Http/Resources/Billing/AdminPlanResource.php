<?php

namespace App\Http\Resources\Billing;

use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPrice;
use Illuminate\Http\Request;

/**
 * A plan with everything a platform admin manages: inactive plans and
 * prices too.
 *
 * @mixin SubscriptionPlan
 */
class AdminPlanResource extends SubscriptionPlanResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SubscriptionPlan $plan */
        $plan = $this->resource;

        return [
            ...parent::toArray($request),
            'is_active' => $plan->is_active,
            'prices' => $plan->prices
                ->map(fn (SubscriptionPlanPrice $price): array => self::price($price) + ['is_active' => $price->is_active])
                ->values()
                ->all(),
            'created_at' => $plan->created_at?->toIso8601String(),
            'updated_at' => $plan->updated_at?->toIso8601String(),
        ];
    }
}
