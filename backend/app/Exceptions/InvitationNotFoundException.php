<?php

namespace App\Exceptions;

/**
 * Unknown token or invitation id. Never says whether a token ever existed.
 */
class InvitationNotFoundException extends ResourceNotFoundException
{
    public function __construct()
    {
        parent::__construct(__('api.invitation.not_found'));
    }

    public function errorCode(): string
    {
        return 'invitation_not_found';
    }
}
