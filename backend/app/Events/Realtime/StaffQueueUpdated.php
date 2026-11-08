<?php

namespace App\Events\Realtime;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A queue changed, for the staff who run it: on private-queue-sessions.{session}
 * (managers and the employees assigned to the queue) and
 * private-businesses.{business} (owners and managers of every branch).
 *
 * kind: ticket_joined, ticket_status_changed, customer_cancelled,
 * session_changed, queue_status_changed, capacity_changed, settings_changed.
 *
 * Public ids and statuses only: no customer names, phone numbers or ticket
 * tokens. Dashboards read the details from the API.
 */
final class StaffQueueUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $kind,
        public readonly string $businessId,
        public readonly string $queueId,
        public readonly ?string $sessionId,
        public readonly ?string $ticketId,
        public readonly ?string $status,
        public readonly string $occurredAt,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('businesses.'.$this->businessId)];
        if ($this->sessionId !== null) {
            $channels[] = new PrivateChannel('queue-sessions.'.$this->sessionId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'queue.updated';
    }

    /**
     * @return array<string, string|null>
     */
    public function broadcastWith(): array
    {
        return [
            'kind' => $this->kind,
            'queue' => $this->queueId,
            'session' => $this->sessionId,
            'ticket' => $this->ticketId,
            'status' => $this->status,
            'occurred_at' => $this->occurredAt,
        ];
    }

    public function broadcastQueue(): string
    {
        return (string) config('dory.realtime.queue');
    }
}
