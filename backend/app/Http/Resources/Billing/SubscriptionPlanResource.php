<?php

namespace App\Http\Resources\Billing;

use App\Enums\PlanLimit;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPrice;
use App\Services\Notifications\NotificationTexts;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A plan as businesses see it (docs/SUBSCRIPTIONS.md). Limits are null when
 * unlimited. Amounts are in minor units, with the decimal text alongside.
 *
 * @mixin SubscriptionPlan
 */
class SubscriptionPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SubscriptionPlan $plan */
        $plan = $this->resource;
        $locale = self::locale($request);
        $limits = [];
        foreach (PlanLimit::cases() as $limit) {
            $limits[$limit->value] = $plan->limit($limit);
        }

        return [
            'code' => $plan->code,
            'name' => $plan->name($locale),
            'names' => ['en' => $plan->name_en, 'ar' => $plan->name_ar],
            'description' => $plan->description($locale),
            'descriptions' => ['en' => $plan->description_en, 'ar' => $plan->description_ar],
            'is_free' => $plan->is_free,
            'limits' => $limits,
            'features' => array_values($plan->features ?? []),
            'prices' => $plan->relationLoaded('prices')
                ? $plan->prices
                    ->filter(fn (SubscriptionPlanPrice $price): bool => $price->is_active)
                    ->map(fn (SubscriptionPlanPrice $price): array => self::price($price))
                    ->values()
                    ->all()
                : [],
            'sort_order' => $plan->sort_order,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function price(SubscriptionPlanPrice $price): array
    {
        return [
            'billing_period' => $price->billing_period->value,
            'currency' => $price->currency,
            'amount_minor' => $price->amount_minor,
            'amount' => Money::decimal($price->amount_minor, $price->currency),
        ];
    }

    public static function locale(Request $request): string
    {
        return NotificationTexts::requestLocale(
            $request->header('Accept-Language'),
            (string) ($request->user()?->locale ?? 'en'),
        );
    }
}
