<?php

namespace App\Events;

use App\Enums\QueueEventType;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Something happened in a queue. Dispatched only after the database
 * transaction commits.
 *
 * Phase 04 has no listeners: this is the hook for Phase 06, where a
 * listener will broadcast it with Laravel Reverb (and queue notifications).
 * It carries public ids only, never personal data or tokens.
 */
final class QueueActivity implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $queueId,
        public readonly QueueEventType $type,
        public readonly ?string $sessionId = null,
        public readonly ?string $ticketId = null,
    ) {}
}
