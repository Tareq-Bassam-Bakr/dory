<?php

namespace App\Services\Queues;

use App\Enums\QueueEventType;
use App\Enums\QueueSessionStatus;
use App\Enums\TicketStatus;
use App\Exceptions\QueueException;
use App\Models\Queue;
use App\Models\QueueSession;
use App\Models\QueueTicket;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Opens, pauses, resumes and closes queue sessions
 * (docs/QUEUE_LIFECYCLE.md). At most one session per queue is open or
 * paused (database-enforced by queue_sessions.active_key).
 *
 * Closing policy:
 * - WAITING tickets become CANCELLED with reason "queue_closed".
 * - CALLED and SERVING tickets are kept: staff can still start, complete or
 *   mark them no-show, so no customer in service is silently dropped.
 * - Every affected ticket gets its own event, in the same transaction.
 *
 * A session left open from an earlier business date is closed when a new
 * session is opened: its WAITING tickets become EXPIRED (reason "day_ended").
 */
final class QueueSessionService
{
    public function __construct(
        private readonly TicketStateMachine $machine,
        private readonly QueueEventRecorder $events,
    ) {}

    public function current(Queue $queue): ?QueueSession
    {
        return $queue->activeSession()->first();
    }

    public function open(Queue $queue, User $actor): QueueSession
    {
        if (! $queue->isActive()) {
            throw QueueException::queueInactive();
        }

        try {
            return DB::transaction(function () use ($queue, $actor): QueueSession {
                // Serializes concurrent "open" requests for the queue.
                Queue::query()->whereKey($queue->getKey())->lockForUpdate()->first();
                $today = BusinessDay::today($queue->branch);

                $active = $this->lockActive($queue);
                if ($active !== null) {
                    if ($active->business_date->toDateString() === $today->toDateString()) {
                        throw QueueException::sessionAlreadyActive();
                    }
                    $this->closeSession($active, null, 'day_ended', TicketStatus::Expired);
                }

                $session = new QueueSession;
                $session->queue_id = $queue->getKey();
                $session->business_date = $today;
                $session->session_number = $this->nextSessionNumber($queue, $today);
                $session->status = QueueSessionStatus::Open;
                $session->opened_at = now();
                $session->opened_by = $actor->getKey();
                $session->saveOrFail();
                $session->setRelation('queue', $queue);

                $this->events->record($queue, QueueEventType::SessionOpened, $actor, $session, metadata: [
                    'business_date' => $today->toDateString(),
                    'session_number' => $session->session_number,
                ]);

                return $session;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw QueueException::sessionAlreadyActive();
        }
    }

    public function pause(Queue $queue, User $actor): QueueSession
    {
        return DB::transaction(function () use ($queue, $actor): QueueSession {
            $session = $this->lockActive($queue) ?? throw QueueException::noActiveSession();
            if ($session->status !== QueueSessionStatus::Open) {
                throw QueueException::sessionNotOpen();
            }

            $session->status = QueueSessionStatus::Paused;
            $session->paused_at = now();
            $session->saveOrFail();
            $this->events->record($queue, QueueEventType::SessionPaused, $actor, $session);

            return $session;
        }, 3);
    }

    public function resume(Queue $queue, User $actor): QueueSession
    {
        return DB::transaction(function () use ($queue, $actor): QueueSession {
            $session = $this->lockActive($queue) ?? throw QueueException::noActiveSession();
            if ($session->status !== QueueSessionStatus::Paused) {
                throw QueueException::sessionNotPaused();
            }

            $session->status = QueueSessionStatus::Open;
            $session->saveOrFail();
            $this->events->record($queue, QueueEventType::SessionResumed, $actor, $session);

            return $session;
        }, 3);
    }

    public function close(Queue $queue, User $actor): QueueSession
    {
        return DB::transaction(function () use ($queue, $actor): QueueSession {
            $session = $this->lockActive($queue) ?? throw QueueException::noActiveSession();

            return $this->closeSession($session, $actor, 'closed_by_staff', TicketStatus::Cancelled);
        }, 3);
    }

    /**
     * Must run in a transaction with the session locked.
     */
    private function closeSession(QueueSession $session, ?User $actor, string $reason, TicketStatus $waitingTo): QueueSession
    {
        $waiting = QueueTicket::query()
            ->where('queue_session_id', $session->getKey())
            ->where('status', TicketStatus::Waiting->value)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $queue = $session->queue;
        foreach ($waiting as $ticket) {
            $ticket->setRelation('queue', $queue);
            $this->machine->transition(
                $ticket,
                $waitingTo,
                $waitingTo === TicketStatus::Expired ? QueueEventType::Expired : QueueEventType::Cancelled,
                $actor,
                ['reason' => $waitingTo === TicketStatus::Expired ? $reason : 'queue_closed'],
                $session,
            );
        }

        $session->status = QueueSessionStatus::Closed;
        $session->closed_at = now();
        $session->closed_by = $actor?->getKey();
        $session->close_reason = $reason;
        $session->saveOrFail();

        $this->events->record($queue, QueueEventType::SessionClosed, $actor, $session, metadata: [
            'reason' => $reason,
            'waiting_tickets_closed' => $waiting->count(),
        ]);

        return $session;
    }

    private function lockActive(Queue $queue): ?QueueSession
    {
        $session = QueueSession::query()
            ->where('queue_id', $queue->getKey())
            ->whereNotNull('active_key')
            ->lockForUpdate()
            ->first();

        return $session?->setRelation('queue', $queue);
    }

    private function nextSessionNumber(Queue $queue, CarbonImmutable $day): int
    {
        return (int) QueueSession::query()
            ->where('queue_id', $queue->getKey())
            ->whereDate('business_date', $day->toDateString())
            ->max('session_number') + 1;
    }
}
