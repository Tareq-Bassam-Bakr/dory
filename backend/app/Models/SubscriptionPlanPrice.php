<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $plan_id
 * @property BillingPeriod $billing_period
 * @property string $currency
 * @property int $amount_minor
 * @property bool $is_active
 */
class SubscriptionPlanPrice extends Model
{
    protected $fillable = ['billing_period', 'currency', 'amount_minor', 'is_active'];

    protected function casts(): array
    {
        return [
            'billing_period' => BillingPeriod::class,
            'amount_minor' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<SubscriptionPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }
}
