<?php

namespace App\Models;

use App\Enums\QueueSessionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An operating period of a queue. Created and changed only by
 * QueueSessionService; never deleted.
 *
 * @property int $id
 * @property string $public_id
 * @property int $queue_id
 * @property Carbon $business_date
 * @property int $session_number
 * @property QueueSessionStatus $status
 * @property Carbon $opened_at
 * @property Carbon|null $paused_at
 * @property Carbon|null $closed_at
 */
class QueueSession extends Model
{
    use HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
    ];

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
            'business_date' => 'date',
            'status' => QueueSessionStatus::class,
            'opened_at' => 'datetime',
            'paused_at' => 'datetime',
            'closed_at' => 'datetime',
            'session_number' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (QueueSession $session): void {
            $session->active_key = $session->status->isActive() ? (string) $session->queue_id : null;
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
     * @return HasMany<QueueTicket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(QueueTicket::class);
    }

    /**
     * @return HasMany<QueueEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(QueueEvent::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function acceptsJoins(): bool
    {
        return $this->status === QueueSessionStatus::Open;
    }

    /**
     * Staff may call the next customer while open or paused.
     */
    public function acceptsCalls(): bool
    {
        return $this->status->isActive();
    }
}
