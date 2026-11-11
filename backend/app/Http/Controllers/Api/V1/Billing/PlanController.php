<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Billing\SubscriptionPlanResource;
use App\Services\Billing\PlanCatalog;
use App\Services\Payments\CheckoutService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /billing/plans: the plans a business can choose (public, like a price
 * page). Customers never need any of them.
 */
class PlanController extends Controller
{
    public function index(Request $request, PlanCatalog $catalog, CheckoutService $checkouts): JsonResponse
    {
        $trial = config('billing.enabled') ? $catalog->trial() : null;

        return ApiResponse::success([
            'plans' => SubscriptionPlanResource::collection($catalog->offered())->resolve($request),
            'billing_enabled' => (bool) config('billing.enabled'),
            'default_currency' => strtoupper((string) config('billing.currency')),
            'trial' => $trial === null ? null : [
                'plan' => $trial->code,
                'days' => (int) config('billing.trial.days'),
            ],
            'grace_days' => max(0, (int) config('billing.grace_days')),
            'payments' => [
                'online' => $checkouts->available(),
                'provider' => $checkouts->provider(),
            ],
        ], __('api.billing.plans'));
    }
}
