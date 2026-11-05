<?php

namespace App\Exceptions;

/**
 * The business does not exist OR the user has no active membership in it.
 *
 * Both cases return the same 404 so a user can never probe which businesses
 * exist in other tenants.
 */
class BusinessNotFoundException extends ApiException
{
    public function __construct()
    {
        parent::__construct(__('api.business.not_found'));
    }

    public function status(): int
    {
        return 404;
    }

    public function errorCode(): string
    {
        return 'business_not_found';
    }
}
