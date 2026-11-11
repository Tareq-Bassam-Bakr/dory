<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The subscription of one business (docs/SUBSCRIPTIONS.md). Exactly one row
 * per business; its history is in subscription_events.
 *
 * @property int $id
 * @property string $public_id
 * @property int $business_id
 * @property int $plan_id
 * @property SubscriptionStatus $status
 * @property ?BillingPeriod $billing_period
 * @property ?string $currency
 * @property ?int $amount_minor
 * @property ?Carbon $trial_ends_at
 * @property ?Carbon $current_period_start
 * @property ?Carbon $current_period_end
 * @property ?Carbon $grace_ends_at
 * @property bool $cancel_at_period_end
 * @property ?Carbon $canceled_at
 * @property ?Carbon $ended_at
 * @property ?int $scheduled_plan_id
 * @property ?BillingPeriod $scheduled_billing_period
 * @property ?string $provider
 * @property-read SubscriptionPlan $plan
 * @property-read ?SubscriptionPlan $scheduledPlan
 * @property-read Business $business
 */
class BusinessSubscription extends Model
{
    use HasUlids;

    protected $hidden = ['id', 'business_id', 'plan_id', 'scheduled_plan_id'];

    /**
     * Defaults of new records, as the table has them.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'cancel_at_period_end' => false,
    ];

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'billing_period' => BillingPeriod::class,
            'scheduled_billing_period' => BillingPeriod::class,
            'amount_minor' => 'integer',
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'grace_ends_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'canceled_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class)->withTrashed();
    }

    /**
     * @return BelongsTo<SubscriptionPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /**
     * @return BelongsTo<SubscriptionPlan, $this>
     */
    public function scheduledPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'scheduled_plan_id');
    }
}
