<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\BillingPeriod;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Exceptions\BillingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\Admin\ChangeSubscriptionRequest;
use App\Http\Requests\Billing\Admin\RecordPaymentRequest;
use App\Http\Resources\Billing\BillingInvoiceResource;
use App\Http\Resources\Billing\PaymentCheckoutResource;
use App\Http\Resources\Billing\SubscriptionResource;
use App\Models\BillingInvoice;
use App\Models\Business;
use App\Models\PaymentCheckout;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Admin\BillingAdministration;
use App\Services\Billing\Entitlements;
use App\Services\Billing\PlanCatalog;
use App\Services\Billing\SubscriptionResolver;
use App\Services\Payments\CheckoutService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Business subscriptions for platform admins (docs/SUBSCRIPTIONS.md,
 * "Admin API"): look up any business, record payments received outside
 * DORY (approved separately, BillingPaymentController), extend trials,
 * assign, cancel, resume or end plans. Every change is in the business's
 * subscription history and the platform audit log, with the admin as actor.
 */
class BillingSubscriptionController extends Controller
{
    public function __construct(
        private readonly BillingAdministration $billing,
        private readonly Entitlements $entitlements,
        private readonly PlanCatalog $catalog,
        private readonly CheckoutService $checkouts,
    ) {}

    public function index(Request $request, SubscriptionResolver $resolver): JsonResponse
    {
        $status = $request->query('status');
        $plan = $request->query('plan');
        $search = trim((string) $request->query('search', ''));

        $businesses = Business::query()
            ->withTrashed()
            ->with(['subscription.plan.prices', 'subscription.scheduledPlan.prices'])
            ->when(is_string($status) && SubscriptionStatus::tryFrom($status) !== null,
                fn ($query) => $query->whereHas('subscription', fn ($s) => $s->where('status', $status)))
            ->when(is_string($plan) && $plan !== '',
                fn ($query) => $query->whereHas('subscription.plan', fn ($p) => $p->where('code', $plan)))
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', '%'.addcslashes($search, '%_\\').'%')
                ->orWhere('slug', 'like', '%'.addcslashes($search, '%_\\').'%')
                ->orWhere('public_id', $search)))
            ->orderByDesc('id')
            ->paginate(min(100, max(1, (int) $request->query('per_page', 25))));

        $rows = collect($businesses->items())->map(function (Business $business) use ($resolver): array {
            $state = $resolver->resolve($business->subscription);

            return [
                'business' => $this->businessSummary($business),
                'status' => $state->status,
                'plan' => $state->plan->code,
                'subscribed_plan' => $state->subscribedPlan->code,
                'trial_ends_at' => $state->status === 'trialing' ? $state->trialEndsAt?->toIso8601String() : null,
                'current_period_end' => $state->periodEnd?->toIso8601String(),
                'grace_ends_at' => $state->graceEndsAt?->toIso8601String(),
                'cancel_at_period_end' => $state->cancelAtPeriodEnd,
                'stored_status' => $business->subscription?->status->value,
            ];
        })->all();

        return ApiResponse::success($rows, __('api.billing.subscription'), meta: [
            'pagination' => [
                'current_page' => $businesses->currentPage(),
                'per_page' => $businesses->perPage(),
                'total' => $businesses->total(),
                'last_page' => $businesses->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, Business $billingBusiness): JsonResponse
    {
        return ApiResponse::success($this->detail($request, $billingBusiness), __('api.billing.subscription'));
    }

    public function recordPayment(RecordPaymentRequest $request, Business $billingBusiness): JsonResponse
    {
        $plan = $this->paidPlan((string) $request->validated('plan'));
        $period = BillingPeriod::from((string) $request->validated('billing_period'));
        $currency = strtoupper((string) ($request->validated('currency') ?? config('billing.currency')));
        $amount = $request->validated('amount_minor') ?? $plan->priceFor($period, $currency)?->amount_minor;
        if ($amount === null) {
            throw BillingException::planNotAvailable();
        }

        // Recorded only: it counts, and the plan changes, once an admin
        // approved it (POST /admin/billing/payments/{payment}/approve).
        $invoice = $this->billing->recordPayment($billingBusiness, [
            'plan' => $plan,
            'billing_period' => $period,
            'currency' => $currency,
            'amount_minor' => (int) $amount,
            'method' => PaymentMethod::from((string) $request->validated('method')),
            'reference' => $request->validated('reference'),
            'received_at' => $request->validated('received_at') === null ? null : Carbon::parse($request->validated('received_at')),
            'notes' => $request->validated('notes'),
        ], $request->user());

        return ApiResponse::created([
            'invoice' => new BillingInvoiceResource($invoice->load('plan')),
            'subscription' => $this->detail($request, $billingBusiness),
        ], __('api.admin.payment_recorded'));
    }

    public function extendTrial(ChangeSubscriptionRequest $request, Business $billingBusiness): JsonResponse
    {
        $code = $request->validated('plan');
        $this->billing->extendTrial(
            $billingBusiness,
            (int) $request->validated('days'),
            is_string($code) ? $this->paidPlan($code) : null,
            $request->user(),
        );

        return ApiResponse::success($this->detail($request, $billingBusiness), __('api.billing.trial_extended'));
    }

    public function assignPlan(ChangeSubscriptionRequest $request, Business $billingBusiness): JsonResponse
    {
        $plan = $this->catalog->find((string) $request->validated('plan')) ?? throw BillingException::planNotAvailable();
        $until = $request->validated('until');
        $this->billing->assignPlan(
            $billingBusiness,
            $plan,
            is_string($until) ? Carbon::parse($until) : null,
            $request->user(),
            $request->validated('note'),
        );

        return ApiResponse::success($this->detail($request, $billingBusiness), __('api.billing.plan_assigned'));
    }

    public function expire(ChangeSubscriptionRequest $request, Business $billingBusiness): JsonResponse
    {
        $this->billing->endSubscription($billingBusiness, $request->user(), $request->validated('reason'));

        return ApiResponse::success($this->detail($request, $billingBusiness), __('api.billing.expired'));
    }

    /**
     * On the owner's behalf: a paid plan stops at the end of its period; a
     * trial or an overdue period ends now.
     */
    public function cancel(Request $request, Business $billingBusiness): JsonResponse
    {
        $this->billing->cancel($billingBusiness, $request->user());

        return ApiResponse::success($this->detail($request, $billingBusiness), __('api.billing.canceled'));
    }

    public function resume(Request $request, Business $billingBusiness): JsonResponse
    {
        $this->billing->resume($billingBusiness, $request->user());

        return ApiResponse::success($this->detail($request, $billingBusiness), __('api.billing.resumed'));
    }

    private function paidPlan(string $code): SubscriptionPlan
    {
        $plan = $this->catalog->find($code);
        if ($plan === null || $plan->is_free) {
            throw BillingException::planNotAvailable();
        }

        return $plan;
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Request $request, Business $business): array
    {
        $events = SubscriptionEvent::query()
            ->where('business_id', $business->getKey())
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->limit(50)
            ->get();
        $plans = SubscriptionPlan::query()->get(['id', 'code'])->pluck('code', 'id');
        $actors = User::query()
            ->whereIn('id', $events->pluck('actor_id')->filter()->unique()->values())
            ->get(['id', 'public_id', 'name', 'is_platform_admin'])
            ->keyBy('id');

        return [
            'business' => $this->businessSummary($business),
            'subscription' => (new SubscriptionResource(
                $this->entitlements->state($business),
                $this->entitlements->usage($business),
                null,
                $this->checkouts->available(),
                $this->checkouts->provider(),
            ))->toArray($request),
            'events' => $events->map(fn (SubscriptionEvent $event): array => [
                'type' => $event->type->value,
                'from_plan' => $event->from_plan_id === null ? null : $plans[$event->from_plan_id] ?? null,
                'to_plan' => $event->to_plan_id === null ? null : $plans[$event->to_plan_id] ?? null,
                'from_status' => $event->from_status,
                'to_status' => $event->to_status,
                'actor' => $event->actor_id === null || ! $actors->has($event->actor_id) ? null : [
                    'id' => $actors[$event->actor_id]->public_id,
                    'name' => $actors[$event->actor_id]->name,
                    'is_platform_admin' => $actors[$event->actor_id]->is_platform_admin,
                ],
                'metadata' => $event->metadata,
                'occurred_at' => $event->occurred_at->toIso8601String(),
            ])->all(),
            'invoices' => BillingInvoiceResource::collection(
                BillingInvoice::query()->with('plan')->where('business_id', $business->getKey())
                    ->orderByDesc('issued_at')->orderByDesc('id')->limit(50)->get(),
            )->resolve($request),
            'checkouts' => PaymentCheckoutResource::collection(
                PaymentCheckout::query()->with('plan')->where('business_id', $business->getKey())
                    ->orderByDesc('id')->limit(20)->get(),
            )->resolve($request),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function businessSummary(Business $business): array
    {
        return [
            'id' => $business->public_id,
            'name' => $business->name,
            'slug' => $business->slug,
            'status' => $business->status->value,
            'deleted' => $business->trashed(),
        ];
    }
}
