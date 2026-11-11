<?php

namespace App\Http\Requests\Billing\Admin;

use App\Enums\BillingPeriod;
use App\Enums\PlanFeature;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * POST /admin/billing/plans and PUT /admin/billing/plans/{billingPlan}.
 * Platform admins only (route middleware). Limits are required on create
 * (null = unlimited), so a forgotten field never means "unlimited" by
 * accident. A plan's code and whether it is free never change.
 */
class SavePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function plan(): ?SubscriptionPlan
    {
        $plan = $this->route('billingPlan');

        return $plan instanceof SubscriptionPlan ? $plan : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->plan() === null;
        $required = $creating ? 'required' : 'sometimes';
        $present = $creating ? 'present' : 'sometimes';
        $limit = [$present, 'nullable', 'integer', 'min:1', 'max:100000'];

        return [
            'code' => $creating
                ? ['required', 'string', 'regex:/^[a-z][a-z0-9_-]{1,39}$/', 'not_in:unlimited', Rule::unique('subscription_plans', 'code')]
                : ['prohibited'],
            'is_free' => $creating ? ['required', 'boolean'] : ['prohibited'],
            'name_en' => [$required, 'string', 'max:60'],
            'name_ar' => [$required, 'string', 'max:60'],
            'description_en' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description_ar' => ['sometimes', 'nullable', 'string', 'max:255'],
            'max_branches' => $limit,
            'max_queues' => $limit,
            'max_members' => $limit,
            'features' => [$present, 'array'],
            'features.*' => ['string', 'distinct', Rule::in(PlanFeature::values())],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'prices' => ['sometimes', 'array', 'max:20'],
            'prices.*.billing_period' => ['required', 'string', Rule::in(BillingPeriod::values())],
            'prices.*.currency' => ['required', 'string', 'size:3', 'alpha'],
            'prices.*.amount_minor' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'prices.*.is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $isFree = $this->plan()?->is_free ?? $this->boolean('is_free');
            if ($isFree && count((array) $this->input('prices', [])) > 0) {
                $validator->errors()->add('prices', 'A free plan has no prices.');
            }
            $seen = [];
            foreach ((array) $this->input('prices', []) as $i => $price) {
                $key = strtolower((string) ($price['billing_period'] ?? '')).'|'.strtoupper((string) ($price['currency'] ?? ''));
                if (isset($seen[$key])) {
                    $validator->errors()->add("prices.$i", 'Each billing period and currency appears once.');
                }
                $seen[$key] = true;
            }
            $plan = $this->plan();
            if ($plan !== null && $this->has('is_active') && ! $this->boolean('is_active')
                && $plan->code === config('billing.default_plan')) {
                $validator->errors()->add('is_active', 'The default plan cannot be deactivated.');
            }
        }];
    }
}
