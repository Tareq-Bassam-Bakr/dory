<?php

namespace App\Exceptions;

class InvitationExpiredException extends ApiException
{
    public function __construct()
    {
        parent::__construct(__('api.invitation.expired'));
    }

    public function status(): int
    {
        return 410;
    }

    public function errorCode(): string
    {
        return 'invitation_expired';
    }
}
