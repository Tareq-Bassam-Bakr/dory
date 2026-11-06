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
 * Staff actions on tickets (docs/TICKET_STATE_MACHINE.md,
 * docs/CONCURRENCY_STRATEGY.md). Authorization (operate / manage) is
 * checked by the controller; this service enforces the queue rules:
 *
 * - call next: FIFO (priority, then join order) from an open or paused
 *   session; the session row is locked so two staff members never get the
 *   same customer.
 * - one customer at a time per staff member (also a unique index).
 * - start / complete / no-show: by the staff member who called the ticket,
 *   or a manager. No-show only after the queue's grace period.
 * - every action locks session → ticket, re-reads the ticket and fails with
 *   "ticket_state_changed" if someone else changed it first.
 */
final class QueueOperationsService
{
    public function __construct(private readonly TicketStateMachine $machine) {}

    public function callNext(QueueSession $session, User $staff): QueueTicket
    {
        return DB::transaction(function () use ($session, $staff): QueueTicket {
            $locked = QueueSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            $locked->setRelation('queue', $session->queue);

            if (! $locked->acceptsCalls()) {
                throw QueueException::queueClosed();
            }
            $this->ensureFree($staff);

            $next = QueueTicket::query()
                ->where('queue_session_id', $locked->getKey())
                ->where('status', TicketStatus::Waiting->value)
                ->orderByDesc('priority')
                ->orderBy('id')
                ->lockForUpdate()
                ->first()
                ?? throw QueueException::noWaitingTickets();

            return $this->apply($next, $locked, TicketStatus::Called, QueueEventType::Called, $staff, ['method' => 'next']);
        }, 3);
    }

    /**
     * Calls a specific waiting ticket out of order (managers).
     */
    public function call(QueueTicket $ticket, User $staff): QueueTicket
    {
        return $this->onTicket($ticket, function (QueueTicket $locked, QueueSession $session) use ($staff): QueueTicket {
            if (! $session->acceptsCalls()) {
                throw QueueException::queueClosed();
            }
            $this->ensureFree($staff);

            return $this->apply($locked, $session, TicketStatus::Called, QueueEventType::Called, $staff, ['method' => 'specific']);
        });
    }

    public function start(QueueTicket $ticket, User $staff, bool $isManager): QueueTicket
    {
        return $this->onTicket($ticket, function (QueueTicket $locked, QueueSession $session) use ($staff, $isManager): QueueTicket {
            $this->ensureHandler($locked, $staff, $isManager);

            return $this->apply($locked, $session, TicketStatus::Serving, QueueEventType::ServiceStarted, $staff);
        });
    }

    public function complete(QueueTicket $ticket, User $staff, bool $isManager): QueueTicket
    {
        return $this->onTicket($ticket, function (QueueTicket $locked, QueueSession $session) use ($staff, $isManager): QueueTicket {
            $this->ensureHandler($locked, $staff, $isManager);

            return $this->apply($locked, $session, TicketStatus::Completed, QueueEventType::ServiceCompleted, $staff, [
                'service_seconds' => $locked->started_at === null ? null : (int) $locked->started_at->diffInSeconds(now(), true),
            ]);
        });
    }

    /**
     * Takes a waiting customer off the line (e.g. they left).
     */
    public function skip(QueueTicket $ticket, User $staff): QueueTicket
    {
        return $this->onTicket($ticket, fn (QueueTicket $locked, QueueSession $session): QueueTicket => $this->apply(
            $locked,
            $session,
            TicketStatus::Skipped,
            QueueEventType::Skipped,
            $staff,
        ));
    }

    /**
     * The called customer did not come within the grace period.
     */
    public function noShow(QueueTicket $ticket, User $staff, bool $isManager): QueueTicket
    {
        return $this->onTicket($ticket, function (QueueTicket $locked, QueueSession $session) use ($staff, $isManager): QueueTicket {
            $this->ensureHandler($locked, $staff, $isManager);

            if ($locked->status === TicketStatus::Called && $locked->called_at !== null) {
                $deadline = $locked->called_at->copy()->addSeconds($session->queue->grace_period_seconds);
                if (now()->lessThan($deadline)) {
                    throw QueueException::gracePeriodActive((int) ceil(now()->diffInSeconds($deadline, true)));
                }
            }

            return $this->apply($locked, $session, TicketStatus::NoShow, QueueEventType::NoShow, $staff);
        });
    }

    /**
     * Puts a called ticket back on the waiting list at its original place,
     * so it is the next one called again (e.g. the customer asked for a
     * minute). The staff member is free to call someone else.
     */
    public function recall(QueueTicket $ticket, User $staff, bool $isManager): QueueTicket
    {
        return $this->onTicket($ticket, function (QueueTicket $locked, QueueSession $session) use ($staff, $isManager): QueueTicket {
            $this->ensureHandler($locked, $staff, $isManager);

            return $this->apply($locked, $session, TicketStatus::Waiting, QueueEventType::Recalled, $staff);
        });
    }

    /**
     * Locks session → ticket and runs the action on the locked copy.
     *
     * @param  callable(QueueTicket, QueueSession): QueueTicket  $action
     */
    private function onTicket(QueueTicket $ticket, callable $action): QueueTicket
    {
        return DB::transaction(function () use ($ticket, $action): QueueTicket {
            $session = QueueSession::query()->whereKey($ticket->queue_session_id)->lockForUpdate()->firstOrFail();
            $locked = QueueTicket::query()->whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();
            $session->setRelation('queue', $ticket->queue);
            $locked->setRelation('queue', $ticket->queue)->setRelation('session', $session);

            // Someone else acted on the ticket since the client loaded it.
            if ($locked->status !== $ticket->status) {
                throw QueueException::ticketChanged();
            }

            return $action($locked, $session);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function apply(
        QueueTicket $ticket,
        QueueSession $session,
        TicketStatus $to,
        QueueEventType $event,
        User $staff,
        array $metadata = [],
    ): QueueTicket {
        $ticket->setRelation('queue', $session->queue)->setRelation('session', $session);

        return $this->machine->transition($ticket, $to, $event, $staff, $metadata, $session);
    }

    private function ensureFree(User $staff): void
    {
        $busy = QueueTicket::query()
            ->where('served_by', $staff->getKey())
            ->whereIn('status', [TicketStatus::Called->value, TicketStatus::Serving->value])
            ->exists();

        if ($busy) {
            throw QueueException::staffBusy();
        }
    }

    private function ensureHandler(QueueTicket $ticket, User $staff, bool $isManager): void
    {
        if (! $isManager && $ticket->served_by !== null && $ticket->served_by !== $staff->getKey()) {
            throw QueueException::notYourTicket();
        }
    }
}
