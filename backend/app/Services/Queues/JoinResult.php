<?php

namespace App\Services\Queues;

use App\Models\QueueTicket;

/**
 * The outcome of joining a queue.
 *
 * - created: false when an identical earlier request (same Idempotency-Key)
 *   already created the ticket.
 * - accessToken: guests only, the plaintext ticket access token. It exists
 *   only in this response; the database keeps its SHA-256 hash.
 */
final readonly class JoinResult
{
    public function __construct(
        public QueueTicket $ticket,
        public bool $created,
        public ?string $accessToken = null,
    ) {}
}
