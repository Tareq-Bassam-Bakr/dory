<?php

namespace App\Realtime;

use App\Enums\QueueEventType;
use App\Events\QueueActivity;
use App\Events\Realtime\QueueSignal;
use App\Events\Realtime\StaffQueueUpdated;
use App\Events\Realtime\TicketUpdated;
use App\Jobs\ProcessQueueNotifications;
use App\Models\QueueEvent;
use App\Models\QueueTicket;

/**
 * Turns a committed queue change into real-time updates and notifications
 * (docs/REALTIME_ARCHITECTURE.md). QueueActivity is dispatched only after
 * the transaction commits, so nothing is announced for a rolled-back change.
 *
 * 1. Broadcasts, queued on the "realtime" queue and sent by the worker to
 *    Reverb:
 *    - queue-updates.{session} (public): a bare "changed" signal;
 *    - private-tickets.{ticket}: the signed-in customer's own ticket;
 *    - private-queue-sessions.{session} and private-businesses.{business}: staff.
 * 2. One ProcessQueueNotifications job for the notifications.
 *
 * Queuing can fail without affecting the change, which is already saved:
 * apps that miss an update still refresh by polling.
 */
final class QueueActivityRelay
{
    public function handle(QueueActivity $activity): void
    {
        if ($activity->eventId === null) {
            return;
        }

        $event = QueueEvent::query()
            ->with(['queue.branch.business', 'session', 'ticket'])
            ->find($activity->eventId);
        if ($event === null || $event->queue === null) {
            return;
        }

        $occurredAt = ($event->created_at ?? now())->toIso8601String();
        $session = $event->session;
        $ticket = $event->ticket;

        if ($session !== null) {
            broadcast(new QueueSignal($session->public_id, $session->status->value, $occurredAt));
        }

        if ($ticket !== null && $ticket->user_id !== null) {
            broadcast(new TicketUpdated($ticket->public_id, $ticket->status->value, $event->event_type->value, $occurredAt));
        }

        broadcast(new StaffQueueUpdated(
            $this->staffKind($event, $ticket),
            $event->queue->branch->business->public_id,
            $event->queue->public_id,
            $session?->public_id,
            $ticket?->public_id,
            $ticket?->status->value ?? $session?->status->value,
            $occurredAt,
        ));

        ProcessQueueNotifications::dispatch($event->getKey());
    }

    private function staffKind(QueueEvent $event, ?QueueTicket $ticket): string
    {
        return match ($event->event_type) {
            QueueEventType::Joined => 'ticket_joined',
            QueueEventType::SessionOpened,
            QueueEventType::SessionPaused,
            QueueEventType::SessionResumed,
            QueueEventType::SessionClosed => 'session_changed',
            QueueEventType::Cancelled => $ticket !== null && $this->byCustomer($event, $ticket)
                ? 'customer_cancelled'
                : 'ticket_status_changed',
            default => 'ticket_status_changed',
        };
    }

    private function byCustomer(QueueEvent $event, QueueTicket $ticket): bool
    {
        if ($event->actor_id !== null) {
            return $ticket->user_id !== null && $event->actor_id === $ticket->user_id;
        }

        return ($event->metadata['actor'] ?? null) === 'guest';
    }
}
