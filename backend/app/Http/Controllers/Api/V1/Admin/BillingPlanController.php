<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\Admin\SavePlanRequest;
use App\Http\Resources\Billing\AdminPlanResource;
use App\Models\SubscriptionPlan;
use App\Services\Billing\PlanCatalog;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Plans and prices, managed by platform admins without a deployment
 * (docs/SUBSCRIPTIONS.md, "Admin API"). Changing a plan's limits applies to
 * every business on it at once; nothing is removed from a business that is
 * over a lowered limit, it just cannot add more.
 */
class BillingPlanController extends Controller
{
    public function __construct(private readonly PlanCatalog $catalog) {}

    public function index(Request $request): JsonResponse
    {
        $plans = SubscriptionPlan::query()->with('prices')->orderBy('sort_order')->orderBy('id')->get();

        return ApiResponse::success(AdminPlanResource::collection($plans)->resolve($request), __('api.billing.plans'));
    }

    public function store(SavePlanRequest $request): JsonResponse
    {
        $plan = DB::transaction(function () use ($request): SubscriptionPlan {
            $plan = new SubscriptionPlan;
            $plan->forceFill($request->safe()->except('prices') + ['is_active' => true, 'sort_order' => 0])->saveOrFail();
            $this->savePrices($plan, (array) $request->validated('prices', []));

            return $plan;
        });
        $this->catalog->forget();

        return ApiResponse::created(new AdminPlanResource($plan->load('prices')), __('api.billing.plan_saved'));
    }

    public function update(SavePlanRequest $request, SubscriptionPlan $billingPlan): JsonResponse
    {
        DB::transaction(function () use ($request, $billingPlan): void {
            $billingPlan->forceFill($request->safe()->except('prices'))->saveOrFail();
            $this->savePrices($billingPlan, (array) $request->validated('prices', []));
        });
        $this->catalog->forget();

        return ApiResponse::success(new AdminPlanResource($billingPlan->refresh()->load('prices')), __('api.billing.plan_saved'));
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
}
