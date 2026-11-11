<?php

namespace App\Exceptions;

use App\Enums\PlanFeature;
use App\Enums\PlanLimit;
use App\Models\SubscriptionPlan;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Subscription and payment errors (docs/SUBSCRIPTIONS.md). Plan limits carry
 * their numbers in "details", so the app can explain and offer an upgrade.
 */
final class BillingException extends ApiException
{
    /**
     * @param  array<string, mixed>|null  $details
     */
    private function __construct(
        private readonly int $httpStatus,
        private readonly string $errorCode,
        string $message,
        private readonly ?array $details = null,
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->httpStatus;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function details(): ?array
    {
        return $this->details;
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), $this->httpStatus, $this->errorCode, details: $this->details);
    }

    public static function limitReached(PlanLimit $limit, int $max, int $used, SubscriptionPlan $plan): self
    {
        return new self(403, 'plan_limit_reached', trans_choice('api.billing.plan_limit_reached.'.$limit->value, $max, ['max' => $max]), [
            'limit' => $limit->value,
            'max' => $max,
            'used' => $used,
            'plan' => $plan->code,
        ]);
    }

    public static function featureNotInPlan(PlanFeature $feature, SubscriptionPlan $plan): self
    {
        return new self(403, 'feature_not_in_plan', __('api.billing.feature_not_in_plan'), [
            'feature' => $feature->value,
            'plan' => $plan->code,
        ]);
    }

    public static function paymentsUnavailable(): self
    {
        return new self(503, 'payments_unavailable', __('api.billing.payments_unavailable'));
    }

    public static function planNotAvailable(): self
    {
        return new self(422, 'plan_not_available', __('api.billing.plan_not_available'));
    }

    /**
     * @param  string  $reason  e.g. nothing_to_cancel, use_checkout, same_plan.
     */
    public static function changeNotAllowed(string $reason): self
    {
        return new self(409, 'subscription_change_not_allowed', __('api.billing.change_not_allowed.'.$reason), [
            'reason' => $reason,
        ]);
    }

    public static function checkoutNotFound(): self
    {
        return new self(404, 'checkout_not_found', __('api.billing.checkout_not_found'));
    }

    public static function providerError(): self
    {
        return new self(502, 'payment_provider_error', __('api.billing.provider_error'));
    }

    public static function invalidNotification(): self
    {
        return new self(401, 'invalid_notification', __('api.billing.invalid_notification'));
    }
}
