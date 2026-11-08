<?php

namespace App\Events\Realtime;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * "Something changed in this queue session", on the public channel
 * queue-updates.{session}. Guests have no account, so they cannot join a
 * private channel: they listen here and then read their own ticket through
 * the ticket token API (docs/BROADCAST_CHANNELS.md).
 *
 * Anyone who knows the session id can listen, so the payload carries no
 * personal data, no ticket ids and no credentials: only the session id, its
 * status (already public on the queue page) and the time of the change.
 */
final class QueueSignal implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $sessionId,
        public readonly string $sessionStatus,
        public readonly string $changedAt,
    ) {}

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('queue-updates.'.$this->sessionId)];
    }

    public function broadcastAs(): string
    {
        return 'queue.changed';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return [
            'session' => $this->sessionId,
            'session_status' => $this->sessionStatus,
            'changed_at' => $this->changedAt,
        ];
    }

    public function broadcastQueue(): string
    {
        return (string) config('dory.realtime.queue');
    }
}
