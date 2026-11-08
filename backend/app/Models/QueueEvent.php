<?php

namespace App\Models;

use App\Enums\QueueEventType;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One entry of the append-only queue history. Written only by
 * QueueEventRecorder, in the same transaction as the change it records.
 * Updating or deleting an event throws: history cannot be rewritten
 * through the application.
 *
 * @property QueueEventType $event_type
 * @property TicketStatus|null $previous_status
 * @property TicketStatus|null $new_status
 * @property array<string, mixed>|null $metadata
 */
class QueueEvent extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $hidden = ['id', 'queue_id', 'queue_session_id', 'ticket_id', 'actor_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => QueueEventType::class,
            'previous_status' => TicketStatus::class,
            'new_status' => TicketStatus::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Queue events are append-only.'));
        static::deleting(fn () => throw new LogicException('Queue events are append-only.'));
    }

    /**
     * @return BelongsTo<Queue, $this>
     */
    public function queue(): BelongsTo
    {
        return $this->belongsTo(Queue::class);
    }

    /**
     * @return BelongsTo<QueueSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(QueueSession::class, 'queue_session_id');
    }

    /**
     * @return BelongsTo<QueueTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(QueueTicket::class, 'ticket_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
