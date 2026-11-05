<?php

namespace App\Exceptions;

/**
 * Email/password combination did not match. Deliberately does not say which
 * part was wrong, so accounts cannot be enumerated through login.
 */
class InvalidCredentialsException extends ApiException
{
    public function __construct()
    {
        parent::__construct(__('api.auth.invalid_credentials'));
    }

    public function status(): int
    {
        return 401;
    }

    public function errorCode(): string
    {
        return 'invalid_credentials';
    }
}
