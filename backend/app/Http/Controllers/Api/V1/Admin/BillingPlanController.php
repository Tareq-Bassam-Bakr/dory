<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\Admin\SavePlanRequest;
use App\Http\Resources\Billing\AdminPlanResource;
use App\Models\SubscriptionPlan;
use App\Services\Admin\BillingAdministration;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Plans and prices, managed by platform admins without a deployment
 * (docs/SUBSCRIPTIONS.md, "Admin API"). Changing a plan's limits applies to
 * every business on it at once; nothing is removed from a business that is
 * over a lowered limit, it just cannot add more.
 */
class BillingPlanController extends Controller
{
    public function __construct(private readonly BillingAdministration $billing) {}

    public function index(Request $request): JsonResponse
    {
        $plans = SubscriptionPlan::query()->with('prices')->orderBy('sort_order')->orderBy('id')->get();

        return ApiResponse::success(AdminPlanResource::collection($plans)->resolve($request), __('api.billing.plans'));
    }

    public function store(SavePlanRequest $request): JsonResponse
    {
        $plan = $this->billing->createPlan(
            $request->safe()->except('prices'),
            array_values((array) $request->validated('prices', [])),
            $request->user(),
        );

        return ApiResponse::created(new AdminPlanResource($plan), __('api.billing.plan_saved'));
    }

    public function update(SavePlanRequest $request, SubscriptionPlan $billingPlan): JsonResponse
    {
        $plan = $this->billing->updatePlan(
            $billingPlan,
            $request->safe()->except('prices'),
            array_values((array) $request->validated('prices', [])),
            $request->user(),
        );

        return ApiResponse::success(new AdminPlanResource($plan), __('api.billing.plan_saved'));
    }
}
