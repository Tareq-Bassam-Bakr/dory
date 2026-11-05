<?php

namespace App\Contracts;

use App\Models\BusinessInvitation;
use App\Services\Invitations\InvitationDelivery;

/**
 * Delivers an invitation link to the invitee. The invitation workflow never
 * depends on delivery: an invitation exists, and can be accepted, whether
 * or not this succeeds.
 *
 * Implementations must report honestly: "sent" only when a real transport
 * accepted the message. They must never write the link (which contains the
 * token) to a log.
 */
interface InvitationNotifier
{
    public function deliver(BusinessInvitation $invitation, string $invitationUrl): InvitationDelivery;
}
