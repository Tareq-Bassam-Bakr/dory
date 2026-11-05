<?php

namespace App\Enums;

/**
 * Lifecycle of a business invitation. Only pending invitations can be
 * accepted, declined or revoked, and each of those ends it for good.
 */
enum InvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Revoked = 'revoked';
    case Expired = 'expired';
}
