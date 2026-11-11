<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Enums\InvoiceStatus;
use App\Exceptions\BillingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\DowngradeRequest;
use App\Http\Requests\Billing\StartCheckoutRequest;
use App\Http\Resources\Billing\BillingInvoiceResource;
use App\Http\Resources\Billing\PaymentCheckoutResource;
use App\Http\Resources\Billing\SubscriptionPlanResource;
use App\Http\Resources\Billing\SubscriptionResource;
use App\Models\BillingInvoice;
use App\Models\Business;
use App\Models\PaymentCheckout;
use App\Services\Billing\Entitlements;
use App\Services\Billing\SubscriptionService;
use App\Services\Business\CurrentBusinessContext;
use App\Services\Payments\CheckoutService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * A business's subscription (docs/SUBSCRIPTIONS.md). Owners manage billing;
 * managers see the plan and usage; employees and other businesses see
 * nothing. The subscription belongs to the business, never to a person.
 */
class SubscriptionController extends Controller
{
    public function __construct(
        private readonly Entitlements $entitlements,
        private readonly SubscriptionService $subscriptions,
        private readonly CheckoutService $checkouts,
        private readonly CurrentBusinessContext $context,
    ) {}

    public function show(Business $business): JsonResponse
    {
        Gate::authorize('viewSubscription', $business);

        return ApiResponse::success($this->present($business), __('api.billing.subscription'));
    }

    public function invoices(Request $request, Business $business): JsonResponse
    {
        Gate::authorize('viewBilling', $business);
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));

        $invoices = BillingInvoice::query()
            ->with('plan')
            ->where('business_id', $business->getKey())
            ->where('status', '!=', InvoiceStatus::Open->value)
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return ApiResponse::paginated($invoices, BillingInvoiceResource::class, __('api.billing.invoices'));
    }

    public function checkout(StartCheckoutRequest $request, Business $business): JsonResponse
    {
        [$checkout, $url] = $this->checkouts->start(
            $business,
            $request->user(),
            $request->plan(),
            $request->period(),
            $request->currency(),
            SubscriptionPlanResource::locale($request),
        );

        return ApiResponse::created([
            'checkout' => new PaymentCheckoutResource($checkout),
            'payment_url' => $url,
        ], __('api.billing.checkout_started'));
    }

    /**
     * The app asks after the payer came back from the payment page. Asks the
     * provider when the checkout is not settled yet.
     */
    public function checkoutStatus(Business $business, string $checkout): JsonResponse
    {
        Gate::authorize('viewBilling', $business);
        $found = PaymentCheckout::query()
            ->with('plan')
            ->where('public_id', $checkout)
            ->where('business_id', $business->getKey())
            ->first() ?? throw BillingException::checkoutNotFound();

        try {
            $found = $this->checkouts->finalize($found)->load('plan');
        } catch (BillingException $e) {
            if ($e->errorCode() !== 'payment_provider_error') {
                throw $e;
            }
            // Provider unreachable: report what is known; the webhook or the
            // scheduler settles it later.
        }

        return ApiResponse::success([
            'checkout' => new PaymentCheckoutResource($found),
            'subscription' => $this->present($business),
        ]);
    }

    public function downgrade(DowngradeRequest $request, Business $business): JsonResponse
    {
        $this->subscriptions->downgrade($business, $request->plan(), $request->period(), $request->user());

        return ApiResponse::success($this->present($business), __('api.billing.downgrade_scheduled'));
    }

    public function cancel(Request $request, Business $business): JsonResponse
    {
        Gate::authorize('manageBilling', $business);
        $this->subscriptions->cancel($business, $request->user());

        return ApiResponse::success($this->present($business), __('api.billing.canceled'));
    }

    public function resume(Request $request, Business $business): JsonResponse
    {
        Gate::authorize('manageBilling', $business);
        $this->subscriptions->resume($business, $request->user());

        return ApiResponse::success($this->present($business), __('api.billing.resumed'));
    }

    private function present(Business $business): SubscriptionResource
    {

        return new SubscriptionResource(
            $this->entitlements->state($business),
            $this->entitlements->usage($business),
            $this->context->isResolved() ? $this->context->abilities() : null,
            $this->checkouts->available(),
            $this->checkouts->provider(),
        );
    }
}
