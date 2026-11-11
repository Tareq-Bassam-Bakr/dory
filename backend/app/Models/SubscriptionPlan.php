<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use App\Enums\PlanFeature;
use App\Enums\PlanLimit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A subscription plan (docs/SUBSCRIPTIONS.md). Limits of null are unlimited.
 *
 * @property int $id
 * @property string $code
 * @property string $name_en
 * @property string $name_ar
 * @property ?string $description_en
 * @property ?string $description_ar
 * @property ?int $max_branches
 * @property ?int $max_queues
 * @property ?int $max_members
 * @property list<string> $features
 * @property bool $is_free
 * @property bool $is_active
 * @property int $sort_order
 */
class SubscriptionPlan extends Model
{
    protected $fillable = [
        'code', 'name_en', 'name_ar', 'description_en', 'description_ar',
        'max_branches', 'max_queues', 'max_members', 'features',
        'is_free', 'is_active', 'sort_order',
    ];

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_free' => 'boolean',
            'is_active' => 'boolean',
            'max_branches' => 'integer',
            'max_queues' => 'integer',
            'max_members' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<SubscriptionPlanPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(SubscriptionPlanPrice::class, 'plan_id')->orderBy('billing_period')->orderBy('currency');
    }

    public function limit(PlanLimit $limit): ?int
    {
        return $this->{$limit->column()};
    }

    public function hasFeature(PlanFeature $feature): bool
    {
        return in_array($feature->value, $this->features ?? [], true);
    }

    public function priceFor(BillingPeriod $period, string $currency): ?SubscriptionPlanPrice
    {
        return $this->prices->first(fn (SubscriptionPlanPrice $price): bool => $price->is_active
            && $price->billing_period === $period
            && $price->currency === strtoupper($currency));
    }

    public function name(string $locale): string
    {
        return $locale === 'ar' ? $this->name_ar : $this->name_en;
    }

    public function description(string $locale): ?string
    {
        return $locale === 'ar' ? $this->description_ar : $this->description_en;
    }

    /**
     * Higher means more: used to tell upgrades from downgrades.
     */
    public function rank(): int
    {
        return $this->is_free ? -1 : $this->sort_order;
    }
}
