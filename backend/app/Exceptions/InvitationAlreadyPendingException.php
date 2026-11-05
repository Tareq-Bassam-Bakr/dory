<?php

namespace App\Exceptions;

class InvitationAlreadyPendingException extends ApiException
{
    public function __construct()
    {
        parent::__construct(__('api.invitation.already_pending'));
    }

    public function status(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'invitation_pending';
    }
}
