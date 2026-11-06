<?php

namespace App\Services\Queues;

use App\Enums\QueueEventType;
use App\Enums\TicketStatus;
use App\Exceptions\QueueException;
use App\Models\QueueSession;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A customer or guest leaves the queue. Only WAITING tickets can be
 * cancelled by their holder: once called, staff decide the outcome
 * (serve, no-show or recall).
 */
final class TicketCancellationService
{
    public function __construct(private readonly TicketStateMachine $machine) {}

    public function cancelByHolder(QueueTicket $ticket, ?User $holder): QueueTicket
    {
        return DB::transaction(function () use ($ticket, $holder): QueueTicket {
            // Lock order: session → ticket (same as joins and staff actions).
            $session = QueueSession::query()->whereKey($ticket->queue_session_id)->lockForUpdate()->firstOrFail();
            $locked = QueueTicket::query()->whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== TicketStatus::Waiting) {
                throw QueueException::cancellationNotAllowed();
            }

            $locked->setRelation('queue', $ticket->queue)->setRelation('session', $session);

            return $this->machine->transition(
                $locked,
                TicketStatus::Cancelled,
                QueueEventType::Cancelled,
                $holder,
                ['reason' => $holder === null ? 'guest_cancelled' : 'customer_cancelled'],
                $session,
            );
        }, 3);
    }
}
