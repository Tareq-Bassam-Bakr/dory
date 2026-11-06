<?php

namespace App\Enums;

/**
 * Ticket lifecycle (docs/TICKET_STATE_MACHINE.md).
 *
 *   waiting → called | cancelled | skipped | expired
 *   called  → serving | no_show | waiting (authorized recall)
 *   serving → completed | cancelled (authorized exceptional cancellation)
 *
 * completed, cancelled, skipped, no_show and expired are terminal: no
 * ordinary API can reopen them.
 */
enum TicketStatus: string
{
    case Waiting = 'waiting';
    case Called = 'called';
    case Serving = 'serving';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Skipped = 'skipped';
    case NoShow = 'no_show';
    case Expired = 'expired';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Waiting => [self::Called, self::Cancelled, self::Skipped, self::Expired],
            self::Called => [self::Serving, self::NoShow, self::Waiting],
            self::Serving => [self::Completed, self::Cancelled],
            default => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * Waiting, called or serving: the customer still holds a place.
     */
    public function isActive(): bool
    {
        return ! $this->isTerminal();
    }

    /**
     * Called or serving: a staff member is busy with the ticket.
     */
    public function occupiesStaff(): bool
    {
        return $this === self::Called || $this === self::Serving;
    }

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return [self::Waiting->value, self::Called->value, self::Serving->value];
    }
}
