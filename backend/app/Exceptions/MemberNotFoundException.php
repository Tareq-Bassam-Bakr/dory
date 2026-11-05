<?php

namespace App\Exceptions;

class MemberNotFoundException extends ResourceNotFoundException
{
    public function __construct()
    {
        parent::__construct(__('api.member.not_found'));
    }

    public function errorCode(): string
    {
        return 'member_not_found';
    }
}
