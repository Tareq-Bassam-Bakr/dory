<?php

namespace App\Services\Billing;

use App\Enums\PlanFeature;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Collection;

/**
 * The plans in the database (docs/SUBSCRIPTIONS.md), cached for one request.
 */
final class PlanCatalog
{
    /** @var array<string, ?SubscriptionPlan> */
    private array $byCode = [];

    public function find(string $code): ?SubscriptionPlan
    {
        if (! array_key_exists($code, $this->byCode)) {
            $this->byCode[$code] = SubscriptionPlan::query()->with('prices')->where('code', $code)->first();
        }

        return $this->byCode[$code];
    }

    public function findById(int $id): ?SubscriptionPlan
    {
        foreach ($this->byCode as $plan) {
            if ($plan?->getKey() === $id) {
                return $plan;
            }
        }
        $plan = SubscriptionPlan::query()->with('prices')->find($id);
        if ($plan !== null) {
            $this->byCode[$plan->code] = $plan;
        }

        return $plan;
    }

    /**
     * The plan of a business without a paid plan or trial in force.
     */
    public function default(): SubscriptionPlan
    {
        return $this->find((string) config('billing.default_plan'))
            ?? SubscriptionPlan::query()->with('prices')->where('is_free', true)->orderBy('sort_order')->first()
            ?? $this->unlimited();
    }

    /**
     * The trial plan for new businesses, or null when trials are off.
     */
    public function trial(): ?SubscriptionPlan
    {
        if ((int) config('billing.trial.days') <= 0) {
            return null;
        }

        return $this->find((string) config('billing.trial.plan'));
    }

    /**
     * Plans a business can choose, in display order.
     *
     * @return Collection<int, SubscriptionPlan>
     */
    public function offered(): Collection
    {
        return SubscriptionPlan::query()
            ->with('prices')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Everything without limits: what every business gets while billing is
     * switched off. Never stored.
     */
    public function unlimited(): SubscriptionPlan
    {
        $plan = new SubscriptionPlan([
            'code' => 'unlimited',
            'name_en' => 'All features',
            'name_ar' => 'جميع الميزات',
            'max_branches' => null,
            'max_queues' => null,
            'max_members' => null,
            'features' => PlanFeature::values(),
            'is_free' => true,
            'is_active' => false,
            'sort_order' => 0,
        ]);
        $plan->setRelation('prices', new Collection);

        return $plan;
    }

    public function forget(): void
    {
        $this->byCode = [];
    }
}
