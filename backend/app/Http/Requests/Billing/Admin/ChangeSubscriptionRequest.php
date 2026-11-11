<?php

namespace App\Http\Requests\Billing\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Platform admin changes of one business's subscription: extend the trial,
 * assign a plan, or end it (docs/SUBSCRIPTIONS.md, "Admin API").
 */
class ChangeSubscriptionRequest extends FormRequest
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
        return match ($this->route()?->getName()) {
            'api.v1.admin.billing.businesses.trial' => [
                'days' => ['required', 'integer', 'min:1', 'max:90'],
                'plan' => ['nullable', 'string', 'max:40'],
            ],
            'api.v1.admin.billing.businesses.plan' => [
                'plan' => ['required', 'string', 'max:40'],
                'until' => ['nullable', 'date', 'after:now'],
                'note' => ['nullable', 'string', 'max:255'],
            ],
            default => [
                'reason' => ['nullable', 'string', 'max:120'],
            ],
        };
    }
}
