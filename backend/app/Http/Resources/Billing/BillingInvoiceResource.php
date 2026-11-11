<?php

namespace App\Http\Resources\Billing;

use App\Models\BillingInvoice;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line of a business's billing history. Provider references and admin
 * notes are not shown to businesses.
 *
 * @mixin BillingInvoice
 */
class BillingInvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var BillingInvoice $invoice */
        $invoice = $this->resource;
        $locale = SubscriptionPlanResource::locale($request);

        return [
            'id' => $invoice->public_id,
            'number' => $invoice->number,
            'status' => $invoice->status->value,
            'plan' => $this->whenLoaded('plan', fn () => [
                'code' => $invoice->plan->code,
                'name' => $invoice->plan->name($locale),
                'names' => ['en' => $invoice->plan->name_en, 'ar' => $invoice->plan->name_ar],
            ]),
            'billing_period' => $invoice->billing_period->value,
            'currency' => $invoice->currency,
            'amount_minor' => $invoice->amount_minor,
            'amount' => Money::decimal($invoice->amount_minor, $invoice->currency),
            'period_start' => $invoice->period_start?->toIso8601String(),
            'period_end' => $invoice->period_end?->toIso8601String(),
            'issued_at' => $invoice->issued_at->toIso8601String(),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'failed_at' => $invoice->failed_at?->toIso8601String(),
            'failure_reason' => $invoice->failure_reason,
            'payment_method' => $invoice->payment_method?->value,
            'provider' => $invoice->provider,
        ];
    }
}
