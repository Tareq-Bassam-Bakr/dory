<?php

namespace App\Exceptions;

/**
 * The signed-in account is not the one the invitation was sent to.
 */
class InvitationEmailMismatchException extends ApiException
{
    public function __construct()
    {
        parent::__construct(__('api.invitation.email_mismatch'));
    }

    public function status(): int
    {
        return 403;
    }

    public function errorCode(): string
    {
        return 'invitation_email_mismatch';
    }
}
