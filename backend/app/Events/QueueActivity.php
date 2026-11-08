<?php

namespace App\Events;

use App\Enums\QueueEventType;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Something happened in a queue. Dispatched only after the database
 * transaction commits.
 *
 * QueueActivityRelay listens: it broadcasts the change through Laravel
 * Reverb and queues the notifications (docs/REALTIME_ARCHITECTURE.md).
 * It carries public ids, plus the internal id of the recorded history entry
 * for the relay; it is never broadcast itself.
 */
final class QueueActivity implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $queueId,
        public readonly QueueEventType $type,
        public readonly ?string $sessionId = null,
        public readonly ?string $ticketId = null,
        public readonly ?int $eventId = null,
    ) {}
}
