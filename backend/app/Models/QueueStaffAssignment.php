<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A team member (existing business membership) working a queue. Ending
 * the assignment keeps the row, so the history stays available.
 *
 * @property int $id
 * @property string $public_id
 * @property int $queue_id
 * @property int $business_member_id
 * @property Carbon $assigned_at
 * @property Carbon|null $unassigned_at
 */
class QueueStaffAssignment extends Model
{
    use HasUlids;

    /**
     * @var list<string>
     */
    protected $hidden = ['id', 'queue_id', 'business_member_id', 'assigned_by', 'unassigned_by', 'active_key'];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'unassigned_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (QueueStaffAssignment $assignment): void {
            $assignment->active_key = $assignment->unassigned_at === null
                ? $assignment->queue_id.':'.$assignment->business_member_id
                : null;
        });
    }

    /**
     * @return BelongsTo<Queue, $this>
     */
    public function queue(): BelongsTo
    {
        return $this->belongsTo(Queue::class)->withTrashed();
    }

    /**
     * @return BelongsTo<BusinessMember, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(BusinessMember::class, 'business_member_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function isActive(): bool
    {
        return $this->unassigned_at === null;
    }
}
