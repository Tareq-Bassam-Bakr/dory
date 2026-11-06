<?php

namespace App\Exceptions;

use App\Enums\TicketStatus;

/**
 * Expected queue-engine errors, each with a stable code
 * (docs/QUEUE_API_REFERENCE.md#error-codes).
 */
final class QueueException extends ApiException
{
    private function __construct(private readonly int $httpStatus, private readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->httpStatus;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    private static function make(int $status, string $code, array $replace = []): self
    {
        return new self($status, $code, __('api.queue.'.$code, $replace));
    }

    public static function notFound(string $what): self
    {
        return self::make(404, $what.'_not_found');
    }

    public static function queueInactive(): self
    {
        return self::make(409, 'queue_inactive');
    }

    public static function queueClosed(): self
    {
        return self::make(409, 'queue_closed');
    }

    public static function queuePaused(): self
    {
        return self::make(409, 'queue_paused');
    }

    public static function queueFull(): self
    {
        return self::make(409, 'queue_full');
    }

    /**
     * The queue's business, branch or service cannot take customers.
     * $reason comes from Queue::unavailabilityReason().
     */
    public static function unavailable(string $reason): self
    {
        return self::make(409, $reason);
    }

    public static function ticketNotReviewable(): self
    {
        return self::make(409, 'ticket_not_reviewable');
    }

    public static function alreadyReviewed(): self
    {
        return self::make(409, 'already_reviewed');
    }

    public static function guestJoinDisabled(): self
    {
        return self::make(403, 'guest_join_disabled');
    }

    public static function duplicateActiveTicket(): self
    {
        return self::make(409, 'duplicate_active_ticket');
    }

    public static function invalidTransition(TicketStatus $from, TicketStatus $to): self
    {
        return self::make(409, 'invalid_ticket_transition', ['from' => $from->value, 'to' => $to->value]);
    }

    public static function ticketChanged(): self
    {
        return self::make(409, 'ticket_state_changed');
    }

    public static function noWaitingTickets(): self
    {
        return self::make(409, 'no_waiting_tickets');
    }

    public static function staffBusy(): self
    {
        return self::make(409, 'staff_busy');
    }

    public static function notYourTicket(): self
    {
        return self::make(403, 'ticket_handled_by_other_staff');
    }

    public static function gracePeriodActive(int $seconds): self
    {
        return self::make(409, 'grace_period_active', ['seconds' => $seconds]);
    }

    public static function sessionAlreadyActive(): self
    {
        return self::make(409, 'session_already_active');
    }

    public static function noActiveSession(): self
    {
        return self::make(409, 'no_active_session');
    }

    public static function sessionNotOpen(): self
    {
        return self::make(409, 'session_not_open');
    }

    public static function sessionNotPaused(): self
    {
        return self::make(409, 'session_not_paused');
    }

    public static function queueHasActiveSession(): self
    {
        return self::make(409, 'queue_has_active_session');
    }

    public static function cancellationNotAllowed(): self
    {
        return self::make(409, 'cancellation_not_allowed');
    }
}
