<?php

namespace App\Exceptions;

/**
 * The account exists but is suspended or deactivated.
 */
class AccountInactiveException extends ApiException
{
    public function __construct()
    {
        parent::__construct(__('api.auth.account_inactive'));
    }

    public function status(): int
    {
        return 403;
    }

    public function errorCode(): string
    {
        return 'account_inactive';
    }
}
