<?php

namespace App\Services\Queues;

use App\Enums\QueueEventType;
use App\Enums\TicketStatus;
use App\Events\QueueActivity;
use App\Models\Queue;
use App\Models\QueueEvent;
use App\Models\QueueSession;
use App\Models\QueueTicket;
use App\Models\User;

/**
 * Writes the append-only queue history, in the caller's transaction, and
 * announces the change after the transaction commits (QueueActivity is a
 * ShouldDispatchAfterCommit event: nothing is announced for a change that
 * was rolled back). QueueActivityRelay then broadcasts it and queues the
 * notifications.
 */
final class QueueEventRecorder
{
    /**
     * @param  array<string, mixed>  $metadata  Non-sensitive details only (no tokens, phone numbers).
     */
    public function record(
        Queue $queue,
        QueueEventType $type,
        ?User $actor,
        ?QueueSession $session = null,
        ?QueueTicket $ticket = null,
        ?TicketStatus $previous = null,
        ?TicketStatus $new = null,
        array $metadata = [],
    ): QueueEvent {
        $event = new QueueEvent;
        $event->queue_id = $queue->getKey();
        $event->queue_session_id = $session?->getKey() ?? $ticket?->queue_session_id;
        $event->ticket_id = $ticket?->getKey();
        $event->actor_id = $actor?->getKey();
        $event->event_type = $type;
        $event->previous_status = $previous;
        $event->new_status = $new;
        $event->metadata = ($actor === null ? ['actor' => $ticket?->isGuest() ? 'guest' : 'system'] : []) + $metadata;
        $event->created_at = now();
        $event->saveOrFail();

        event(new QueueActivity(
            $queue->public_id,
            $type,
            $session?->public_id,
            $ticket?->public_id,
            $event->getKey(),
        ));

        return $event;
    }
}
