<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BillingPeriod;
use App\Enums\PlanFeature;
use App\Models\SubscriptionPlan;
use App\Services\Admin\BillingAdministration;
use App\Services\Admin\PlatformStatistics;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Plans and prices (docs/ADMIN_DASHBOARD.md, "Plans"). Changes apply to
 * every business on the plan at once and are in the platform audit log.
 *
 * - A plan's code and whether it is free never change.
 * - Limits are a number or explicitly unlimited; an empty field is an
 *   error, never "unlimited" by accident.
 * - Prices are per billing period and currency, only in the currencies
 *   DORY accepts (Settings). A price is never deleted (invoices refer to
 *   it): it is no longer offered.
 * - The default plan cannot be switched off.
 */
class PlanController extends AdminController
{
    private const LIMITS = ['max_branches', 'max_queues', 'max_members'];

    public function __construct(private readonly BillingAdministration $billing) {}

    public function index(PlatformStatistics $statistics): View
    {
        return view('admin.plans.index', [
            'plans' => SubscriptionPlan::query()->with('prices')->orderBy('sort_order')->orderBy('id')->get(),
            'subscribers' => $statistics->subscriptions()['plans'],
            'defaultPlan' => (string) config('billing.default_plan'),
            'trialPlan' => (int) config('billing.trial.days') > 0 ? (string) config('billing.trial.plan') : null,
        ]);
    }

    public function create(): View
    {
        return view('admin.plans.form', $this->formData(null, 0));
    }

    public function store(Request $request): RedirectResponse
    {
        [$attributes, $prices] = $this->validated($request, null);
        $plan = $this->billing->createPlan($attributes, $prices, $this->admin($request));

        return redirect()->route('admin.plans.index')
            ->with('status', __('admin.plans.created', ['plan' => $plan->name(app()->getLocale())]));
    }

    public function edit(SubscriptionPlan $adminPlan, PlatformStatistics $statistics): View
    {
        return view('admin.plans.form', $this->formData($adminPlan, $statistics->subscriptions()['plans'][$adminPlan->code] ?? 0));
    }

    public function update(Request $request, SubscriptionPlan $adminPlan): RedirectResponse
    {
        [$attributes, $prices] = $this->validated($request, $adminPlan);
        $plan = $this->billing->updatePlan($adminPlan, $attributes, $prices, $this->admin($request));

        return redirect()->route('admin.plans.index')
            ->with('status', __('admin.plans.saved', ['plan' => $plan->name(app()->getLocale())]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?SubscriptionPlan $plan, int $subscribers): array
    {
        $currencies = $this->currencies();
        $prices = [];
        $otherPrices = [];
        foreach ($plan?->prices ?? [] as $price) {
            if (in_array($price->currency, $currencies, true)) {
                $prices[$price->billing_period->value.'_'.$price->currency] = [
                    'amount' => Money::decimal($price->amount_minor, $price->currency),
                    'active' => $price->is_active,
                ];
            } else {
                $otherPrices[] = __('admin.periods.'.$price->billing_period->value).': '
                    .Money::decimal($price->amount_minor, $price->currency).' '.$price->currency
                    .($price->is_active ? '' : ' ('.__('admin.plans.inactive').')');
            }
        }

        return [
            'plan' => $plan,
            'subscribers' => $subscribers,
            'currencies' => $currencies,
            'periods' => BillingPeriod::values(),
            'prices' => $prices,
            'otherPrices' => $otherPrices,
            'features' => PlanFeature::values(),
            'defaultPlan' => (string) config('billing.default_plan'),
        ];
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<array{billing_period: string, currency: string, amount_minor: int, is_active: bool}>}
     */
    private function validated(Request $request, ?SubscriptionPlan $plan): array
    {
        $rules = [
            'name_en' => ['required', 'string', 'max:60'],
            'name_ar' => ['required', 'string', 'max:60'],
            'description_en' => ['nullable', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string', 'max:255'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'distinct', Rule::in(PlanFeature::values())],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'prices' => ['nullable', 'array'],
            'prices.*.amount' => ['nullable', 'string', 'max:20'],
            'prices.*.active' => ['nullable', 'boolean'],
            'prices.*.existing' => ['nullable', 'boolean'],
        ];
        foreach (self::LIMITS as $column) {
            $rules[$column.'_unlimited'] = ['nullable', 'boolean'];
            $rules[$column] = ['nullable', 'required_unless:'.$column.'_unlimited,1', 'integer', 'min:1', 'max:100000'];
        }
        if ($plan === null) {
            $rules['code'] = ['required', 'string', 'regex:/^[a-z][a-z0-9_-]{1,39}$/', 'not_in:unlimited', Rule::unique('subscription_plans', 'code')];
            $rules['is_free'] = ['required', 'boolean'];
        }
        $validated = $request->validate($rules, [], $this->attributeNames());

        $isFree = $plan?->is_free ?? (bool) $validated['is_free'];
        $isActive = (bool) ($validated['is_active'] ?? false);
        if ($plan !== null && ! $isActive && $plan->code === config('billing.default_plan')) {
            throw ValidationException::withMessages(['is_active' => __('admin.plans.default_always_active')]);
        }

        $attributes = [
            'name_en' => trim($validated['name_en']),
            'name_ar' => trim($validated['name_ar']),
            'description_en' => $this->nullIfBlank($validated['description_en'] ?? null),
            'description_ar' => $this->nullIfBlank($validated['description_ar'] ?? null),
            'features' => array_values(array_unique((array) ($validated['features'] ?? []))),
            'sort_order' => (int) $validated['sort_order'],
            'is_active' => $isActive,
        ];
        foreach (self::LIMITS as $column) {
            $attributes[$column] = (bool) ($validated[$column.'_unlimited'] ?? false) ? null : (int) $validated[$column];
        }
        if ($plan === null) {
            $attributes['code'] = $validated['code'];
            $attributes['is_free'] = $isFree;
        }

        return [$attributes, $this->prices((array) ($validated['prices'] ?? []), $plan, $isFree)];
    }

    /**
     * The price grid: "monthly_USD" => amount typed as a decimal.
     *
     * @param  array<string, array{amount?: ?string, active?: mixed, existing?: mixed}>  $input
     * @return list<array{billing_period: string, currency: string, amount_minor: int, is_active: bool}>
     */
    private function prices(array $input, ?SubscriptionPlan $plan, bool $isFree): array
    {
        $prices = [];
        $errors = [];
        foreach (BillingPeriod::values() as $period) {
            foreach ($this->currencies() as $currency) {
                $key = $period.'_'.$currency;
                $amount = trim((string) ($input[$key]['amount'] ?? ''));
                $existing = $plan?->prices->first(fn ($price) => $price->billing_period->value === $period && $price->currency === $currency);

                if ($amount === '') {
                    if ($existing !== null && ! $isFree) {
                        $errors['prices.'.$key.'.amount'] = __('admin.plans.price_required');
                    }

                    continue;
                }
                if ($isFree) {
                    $errors['prices'] = __('admin.plans.free_no_prices');

                    continue;
                }
                $exponent = Money::exponent($currency);
                $pattern = $exponent === 0 ? '/^\d{1,10}$/' : '/^\d{1,10}(\.\d{1,'.$exponent.'})?$/';
                $minor = preg_match($pattern, $amount) ? Money::minor($amount, $currency) : null;
                if ($minor === null || $minor > 1_000_000_000) {
                    $errors['prices.'.$key.'.amount'] = trans_choice('admin.plans.price_invalid', $exponent, ['decimals' => $exponent]);

                    continue;
                }
                $prices[] = [
                    'billing_period' => $period,
                    'currency' => $currency,
                    'amount_minor' => $minor,
                    // A new price is offered at once; an existing one as ticked.
                    'is_active' => $existing === null ? true : (bool) ($input[$key]['active'] ?? false),
                ];
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $prices;
    }

    /**
     * @return list<string>
     */
    private function currencies(): array
    {
        return array_values(array_unique(array_map('strtoupper', (array) config('billing.currencies'))));
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, string>
     */
    private function attributeNames(): array
    {
        return [
            'code' => __('admin.plans.code'),
            'name_en' => __('admin.plans.name_en'),
            'name_ar' => __('admin.plans.name_ar'),
            'max_branches' => __('admin.limits.branches'),
            'max_queues' => __('admin.limits.queues'),
            'max_members' => __('admin.limits.members'),
            'sort_order' => __('admin.plans.sort_order'),
        ];
    }
}
