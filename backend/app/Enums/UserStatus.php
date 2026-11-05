<?php

namespace App\Enums;

/**
 * Lifecycle state of a user account.
 */
enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Deactivated = 'deactivated';

    /**
     * Only active accounts may sign in or use their tokens.
     */
    public function canAuthenticate(): bool
    {
        return $this === self::Active;
    }
}
