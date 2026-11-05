<?php

namespace App\Enums;

/**
 * State of a user's membership in a business.
 */
enum MembershipStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Suspended = 'suspended';

    /**
     * Only active memberships grant access to business resources.
     */
    public function grantsAccess(): bool
    {
        return $this === self::Active;
    }
}
