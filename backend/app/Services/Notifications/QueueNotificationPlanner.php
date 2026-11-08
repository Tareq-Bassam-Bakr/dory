<?php

namespace App\Services\Notifications;

use App\Enums\NotificationKind;
use App\Enums\QueueEventType;
use App\Enums\QueueSessionStatus;
use App\Enums\TicketStatus;
use App\Models\QueueEvent;
use App\Models\QueueTicket;

/**
 * Decides who is told what about one recorded queue change
 * (docs/NOTIFICATION_EVENTS.md). Runs in a queued job, after the change
 * was committed. Everything comes from the recorded history entry, never
 * from the client.
 *
 * - The ticket holder: called, service started, completed, skipped,
 *   no-show, cancelled by the business, queue closed.
 * - Everyone still in a paused session: queue paused.
 * - Staff who run the queue: a customer joined, a customer cancelled.
 * - Waiting tickets that moved within N places of the counter: "almost your
 *   turn", once per ticket.
 *
 * Position changes alone notify nobody: apps refresh on the real-time signal.
 */
final class QueueNotificationPlanner
{
    /**
     * Events after which waiting tickets moved closer to the counter.
     */
    private const LINE_MOVED = [
        QueueEventType::Called,
        QueueEventType::Cancelled,
        QueueEventType::Skipped,
    ];

    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly QueueStaffRecipients $staff,
    ) {}

    public function handle(int $queueEventId): void
    {
        $event = QueueEvent::query()
            ->with(['queue.branch.business', 'queue.service', 'session', 'ticket.user', 'actor'])
            ->find($queueEventId);
        if ($event === null || $event->queue === null) {
            return;
        }

        $ticket = $event->ticket;
        $ticket?->setRelation('queue', $event->queue);
        $key = 'event:'.$event->getKey();

        if ($ticket !== null) {
            $kind = $this->holderKind($event, $ticket);
            if ($kind !== null) {
                $this->dispatcher->toTicketHolder($ticket, $kind, $key.':holder');
            }

            $staffKind = $this->staffKind($event, $ticket);
            if ($staffKind !== null) {
                foreach ($this->staff->for($event->queue) as $user) {
                    if ($user->getKey() !== $event->actor_id) {
                        $this->dispatcher->toStaff($user, $ticket, $staffKind, $key.':user:'.$user->getKey());
                    }
                }
            }
        }

        if ($event->event_type === QueueEventType::SessionPaused && $event->session !== null) {
            foreach ($this->activeTickets($event) as $active) {
                $this->dispatcher->toTicketHolder($active, NotificationKind::QueuePaused, $key.':ticket:'.$active->getKey());
            }
        }

        if (in_array($event->event_type, self::LINE_MOVED, true)
            && ($event->event_type !== QueueEventType::Cancelled || $event->previous_status === TicketStatus::Waiting)) {
            $this->almostTurn($event);
        }
    }

    private function holderKind(QueueEvent $event, QueueTicket $ticket): ?NotificationKind
    {
        return match ($event->event_type) {
            QueueEventType::Called => NotificationKind::TicketCalled,
            QueueEventType::ServiceStarted => NotificationKind::TicketServiceStarted,
            QueueEventType::ServiceCompleted => NotificationKind::TicketCompleted,
            QueueEventType::Skipped => NotificationKind::TicketSkipped,
            QueueEventType::NoShow => NotificationKind::TicketNoShow,
            QueueEventType::Expired => NotificationKind::QueueClosed,
            QueueEventType::Cancelled => match (true) {
                // Customers are not told about what they just did themselves.
                $this->byCustomer($event, $ticket) => null,
                ($event->metadata['reason'] ?? null) === 'queue_closed' => NotificationKind::QueueClosed,
                default => NotificationKind::TicketCancelled,
            },
            default => null,
        };
    }

    private function staffKind(QueueEvent $event, QueueTicket $ticket): ?NotificationKind
    {
        return match (true) {
            $event->event_type === QueueEventType::Joined => NotificationKind::StaffTicketJoined,
            $event->event_type === QueueEventType::Cancelled && $this->byCustomer($event, $ticket) => NotificationKind::StaffTicketCancelled,
            default => null,
        };
    }

    /**
     * The customer acted: the signed-in owner of the ticket, or a guest
     * (recorded without an actor, as "guest").
     */
    private function byCustomer(QueueEvent $event, QueueTicket $ticket): bool
    {
        if ($event->actor_id !== null) {
            return $ticket->user_id !== null && $event->actor_id === $ticket->user_id;
        }

        return ($event->metadata['actor'] ?? null) === 'guest';
    }

    /**
     * @return iterable<QueueTicket>
     */
    private function activeTickets(QueueEvent $event): iterable
    {
        return QueueTicket::query()
            ->with('user')
            ->where('queue_session_id', $event->queue_session_id)
            ->whereIn('status', TicketStatus::activeValues())
            ->orderBy('id')
            ->get()
            ->each(fn (QueueTicket $ticket) => $ticket->setRelation('queue', $event->queue));
    }

    /**
     * The first waiting tickets, in serving order: those within the
     * threshold are told once (key per ticket, not per event).
     */
    private function almostTurn(QueueEvent $event): void
    {
        $session = $event->session;
        if ($session === null || $session->status !== QueueSessionStatus::Open) {
            return;
        }

        $limit = max(0, (int) config('dory.notifications.almost_turn_people_ahead'));
        $waiting = QueueTicket::query()
            ->with('user')
            ->where('queue_session_id', $session->getKey())
            ->where('status', TicketStatus::Waiting->value)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->limit($limit + 1)
            ->get();

        foreach ($waiting->values() as $ahead => $ticket) {
            $ticket->setRelation('queue', $event->queue);
            $this->dispatcher->toTicketHolder(
                $ticket,
                NotificationKind::TicketAlmostTurn,
                'ticket:'.$ticket->getKey().':almost_turn',
                ['people_ahead' => $ahead],
            );
        }
    }
}
