<?php

namespace App\Models;

use App\Enums\SubscriptionEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property int $subscription_id
 * @property SubscriptionEventType $type
 * @property ?int $from_plan_id
 * @property ?int $to_plan_id
 * @property ?string $from_status
 * @property ?string $to_status
 * @property ?int $actor_id
 * @property ?array<string, mixed> $metadata
 * @property Carbon $occurred_at
 */
class SubscriptionEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => SubscriptionEventType::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SubscriptionPlan, $this>
     */
    public function fromPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'from_plan_id');
    }

    /**
     * @return BelongsTo<SubscriptionPlan, $this>
     */
    public function toPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'to_plan_id');
    }
}
