<?php

namespace App\Services\Invitations;

/**
 * Outcome of trying to deliver an invitation link.
 *
 * - sent:           a real mail transport accepted the message. This does not
 *                   prove it reached the inbox.
 * - not_configured: no real mail transport is configured, or invitation
 *                   emails are disabled. Nothing was sent.
 * - failed:         the transport rejected the message. Nothing was sent.
 */
final readonly class InvitationDelivery
{
    public const SENT = 'sent';

    public const NOT_CONFIGURED = 'not_configured';

    public const FAILED = 'failed';

    private function __construct(public string $status, public string $channel = 'email') {}

    public static function sent(): self
    {
        return new self(self::SENT);
    }

    public static function notConfigured(): self
    {
        return new self(self::NOT_CONFIGURED);
    }

    public static function failed(): self
    {
        return new self(self::FAILED);
    }

    /**
     * @return array{channel: string, status: string}
     */
    public function toArray(): array
    {
        return ['channel' => $this->channel, 'status' => $this->status];
    }
}
