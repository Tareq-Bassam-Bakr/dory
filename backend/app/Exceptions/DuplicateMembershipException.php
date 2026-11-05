<?php

namespace App\Exceptions;

/**
 * The user already has a membership (in any state) in this business.
 */
class DuplicateMembershipException extends ApiException
{
    public function __construct()
    {
        parent::__construct(__('api.business.duplicate_membership'));
    }

    public function status(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'membership_exists';
    }
}
