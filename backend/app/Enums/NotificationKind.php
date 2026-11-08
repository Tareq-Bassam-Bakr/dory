<?php

namespace App\Enums;

/**
 * Notifications DORY sends (docs/NOTIFICATION_EVENTS.md). Each kind belongs to
 * one preference, so users can turn whole groups off.
 */
enum NotificationKind: string
{
    // Customers, about their own ticket.
    case TicketAlmostTurn = 'ticket_almost_turn';
    case TicketCalled = 'ticket_called';
    case TicketServiceStarted = 'ticket_service_started';
    case TicketCompleted = 'ticket_completed';
    case TicketCancelled = 'ticket_cancelled';
    case TicketSkipped = 'ticket_skipped';
    case TicketNoShow = 'ticket_no_show';
    case QueuePaused = 'queue_paused';
    case QueueClosed = 'queue_closed';

    // Staff who run the queue.
    case StaffTicketJoined = 'staff_ticket_joined';
    case StaffTicketCancelled = 'staff_ticket_cancelled';

    /**
     * The notification_preferences column that turns this kind on or off.
     */
    public function preference(): string
    {
        return match ($this) {
            self::TicketAlmostTurn => 'almost_turn',
            self::QueuePaused, self::QueueClosed => 'queue_updates',
            self::StaffTicketJoined => 'staff_new_tickets',
            self::StaffTicketCancelled => 'staff_cancellations',
            default => 'ticket_updates',
        };
    }

    public function forStaff(): bool
    {
        return $this === self::StaffTicketJoined || $this === self::StaffTicketCancelled;
    }

    /**
     * Time-critical: delivered with high priority.
     */
    public function isUrgent(): bool
    {
        return $this === self::TicketCalled || $this === self::TicketAlmostTurn;
    }
}
