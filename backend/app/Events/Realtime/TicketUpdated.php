<?php

namespace App\Events\Realtime;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A signed-in customer's ticket changed. Sent on the customer's own channel,
 * private-tickets.{ticket}, which only the ticket's owner may join.
 *
 * The payload says what happened, not the ticket's details: the app reads
 * the ticket from the API, which stays the source of truth.
 */
final class TicketUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $ticketId,
        public readonly string $status,
        public readonly string $event,
        public readonly string $occurredAt,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('tickets.'.$this->ticketId)];
    }

    public function broadcastAs(): string
    {
        return 'ticket.updated';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return [
            'ticket' => $this->ticketId,
            'status' => $this->status,
            'event' => $this->event,
            'occurred_at' => $this->occurredAt,
        ];
    }

    public function broadcastQueue(): string
    {
        return (string) config('dory.realtime.queue');
    }
}
