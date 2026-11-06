<?php

namespace App\Services\Queues;

use App\Enums\QueueEventType;
use App\Enums\TicketStatus;
use App\Exceptions\QueueException;
use App\Models\QueueSession;
use App\Models\QueueTicket;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The only code that changes a ticket's status (docs/TICKET_STATE_MACHINE.md).
 *
 * Callers lock the ticket row (SELECT … FOR UPDATE) inside a transaction
 * and pass the locked copy. The machine rejects transitions the table in
 * TicketStatus does not allow, sets the matching timestamps and staff
 * fields, saves, and records the event in the same transaction.
 */
final class TicketStateMachine
{
    public function __construct(private readonly QueueEventRecorder $events) {}

    /**
     * @param  array<string, mixed>  $metadata  Stored on the event (non-sensitive).
     */
    public function transition(
        QueueTicket $ticket,
        TicketStatus $to,
        QueueEventType $event,
        ?User $actor,
        array $metadata = [],
        ?QueueSession $session = null,
    ): QueueTicket {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Ticket transitions must run inside a transaction.');
        }

        $from = $ticket->status;
        if (! $from->canTransitionTo($to)) {
            throw QueueException::invalidTransition($from, $to);
        }

        $now = now();
        $ticket->status = $to;

        match ($to) {
            TicketStatus::Called => [$ticket->called_at = $now, $ticket->served_by = $actor?->getKey()],
            TicketStatus::Serving => $ticket->started_at = $now,
            TicketStatus::Completed => $ticket->completed_at = $now,
            TicketStatus::Cancelled => [
                $ticket->cancelled_at = $now,
                $ticket->cancel_reason = $metadata['reason'] ?? null,
            ],
            // Recall: back to the waiting list at its original place.
            TicketStatus::Waiting => [
                $ticket->served_by = null,
                $ticket->called_at = null,
                $ticket->recall_count = $ticket->recall_count + 1,
            ],
            default => null,
        };

        if ($to->isTerminal()) {
            $ticket->ended_at = $now;
        }

        try {
            $ticket->saveOrFail();
        } catch (UniqueConstraintViolationException) {
            // active_staff_key: this staff member already has a customer.
            throw QueueException::staffBusy();
        }

        $this->events->record(
            $ticket->queue,
            $event,
            $actor,
            $session,
            $ticket,
            $from,
            $to,
            $metadata,
        );

        return $ticket;
    }
}
