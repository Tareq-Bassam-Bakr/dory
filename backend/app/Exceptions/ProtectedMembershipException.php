<?php

namespace App\Exceptions;

/**
 * The owner membership, and the acting user's own membership, cannot be
 * changed or removed through team management. Ownership transfer is not
 * available through the API.
 */
class ProtectedMembershipException extends ApiException
{
    public function __construct()
    {
        parent::__construct(__('api.member.protected'));
    }

    public function status(): int
    {
        return 403;
    }

    public function errorCode(): string
    {
        return 'membership_protected';
    }
}
