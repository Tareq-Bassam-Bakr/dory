<?php

namespace App\Exceptions;

/**
 * The Idempotency-Key belongs to a business that has since been deleted.
 * The client must generate a new key.
 */
class IdempotencyKeyReusedException extends ApiException
{
    public function __construct()
    {
        parent::__construct(__('api.business.idempotency_key_reused'));
    }

    public function status(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'idempotency_key_reused';
    }
}
