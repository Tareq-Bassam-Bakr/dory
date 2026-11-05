<?php

namespace App\Exceptions;

/**
 * The invitation was already accepted, declined or revoked. Invitations are
 * single-use.
 */
class InvitationNotPendingException extends ApiException
{
    public function __construct()
    {
        parent::__construct(__('api.invitation.not_pending'));
    }

    public function status(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'invitation_not_pending';
    }
}
