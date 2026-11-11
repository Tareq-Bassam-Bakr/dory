<?php

namespace App\Http\Requests\Billing;

use App\Enums\BillingPeriod;
use App\Exceptions\BillingException;
use App\Models\Business;
use App\Models\SubscriptionPlan;
use App\Services\Billing\PlanCatalog;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * POST /businesses/{business}/subscription/downgrade: a smaller plan from
 * the end of the paid period, or the free plan.
 */
class DowngradeRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::forUser($this->user())->inspect('manageBilling', $this->business());
    }

    public function business(): Business
    {
        /** @var Business */
        return $this->route('business');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan' => ['required', 'string', 'max:40'],
            'billing_period' => ['nullable', 'string', Rule::in(BillingPeriod::values())],
        ];
    }

    public function plan(): SubscriptionPlan
    {
        $plan = app(PlanCatalog::class)->find((string) $this->validated('plan'));
        if ($plan === null || (! $plan->is_active && ! $plan->is_free)) {
            throw BillingException::planNotAvailable();
        }

        return $plan;
    }

    public function period(): ?BillingPeriod
    {
        $period = $this->validated('billing_period');

        return is_string($period) ? BillingPeriod::from($period) : null;
    }
}
