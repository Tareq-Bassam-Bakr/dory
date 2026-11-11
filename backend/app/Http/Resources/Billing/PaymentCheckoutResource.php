<?php

namespace App\Http\Resources\Billing;

use App\Models\PaymentCheckout;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An online payment attempt. The provider session and its secrets are never
 * returned.
 *
 * @mixin PaymentCheckout
 */
class PaymentCheckoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PaymentCheckout $checkout */
        $checkout = $this->resource;

        return [
            'id' => $checkout->public_id,
            'status' => $checkout->status->value,
            'plan' => $this->whenLoaded('plan', fn () => $checkout->plan->code),
            'billing_period' => $checkout->billing_period->value,
            'currency' => $checkout->currency,
            'amount_minor' => $checkout->amount_minor,
            'amount' => Money::decimal($checkout->amount_minor, $checkout->currency),
            'provider' => $checkout->provider,
            'expires_at' => $checkout->expires_at->toIso8601String(),
            'completed_at' => $checkout->completed_at?->toIso8601String(),
            'failure_reason' => $checkout->failure_reason,
        ];
    }
}
