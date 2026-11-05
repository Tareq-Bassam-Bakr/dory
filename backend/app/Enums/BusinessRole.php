<?php

namespace App\Enums;

/**
 * Role a user holds inside one specific business.
 *
 * Roles are always scoped to a business membership. They never grant
 * platform-wide privileges (see users.is_platform_admin for that).
 */
enum BusinessRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Employee = 'employee';

    /**
     * May change the business profile, branches and services.
     */
    public function canManageBusiness(): bool
    {
        return $this === self::Owner || $this === self::Manager;
    }

    /**
     * May add, remove or change the role of members.
     */
    public function canManageMembers(): bool
    {
        return $this === self::Owner;
    }
}
