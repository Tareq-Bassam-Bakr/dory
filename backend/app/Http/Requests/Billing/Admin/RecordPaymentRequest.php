<?php

namespace App\Http\Requests\Billing\Admin;

use App\Enums\BillingPeriod;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /admin/billing/businesses/{billingBusiness}/payments: a payment
 * received outside DORY (bank transfer, cash, mobile wallet), recorded by a
 * platform admin after checking it arrived (docs/PAYMENTS.md).
 */
class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan' => ['required', 'string', 'max:40'],
            'billing_period' => ['required', 'string', Rule::in(BillingPeriod::values())],
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],
            // Defaults to the plan's price for the period and currency.
            'amount_minor' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'method' => ['required', 'string', Rule::in(array_map(fn (PaymentMethod $m) => $m->value, PaymentMethod::cases()))],
            // A bank transfer or receipt number; never card details.
            'reference' => [
                'nullable', 'string', 'max:120',
                Rule::unique('billing_invoices', 'provider_reference')->where('provider', 'manual'),
            ],
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
