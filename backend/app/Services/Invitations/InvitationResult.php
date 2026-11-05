<?php

namespace App\Services\Invitations;

use App\Models\BusinessInvitation;

/**
 * A newly created invitation. The plain token exists only here, inside
 * $invitationUrl, and is never stored.
 */
final readonly class InvitationResult
{
    public function __construct(
        public BusinessInvitation $invitation,
        public string $invitationUrl,
        public InvitationDelivery $delivery,
    ) {}
}
