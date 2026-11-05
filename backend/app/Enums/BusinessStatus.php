<?php

namespace App\Enums;

/**
 * Lifecycle state of a business (tenant).
 *
 * - active:    operating normally.
 * - inactive:  temporarily deactivated by its owner. Members can still manage it.
 * - suspended: blocked by the platform. Members can read but not change it.
 * - archived:  deleted by its owner (the row is soft-deleted).
 */
enum BusinessStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
    case Archived = 'archived';

    /**
     * Statuses an owner may set through the API. Suspension and archiving
     * are never client-settable.
     *
     * @return list<string>
     */
    public static function ownerSettable(): array
    {
        return [self::Active->value, self::Inactive->value];
    }

    /**
     * Whether members may change the business, its branches, services and team.
     */
    public function acceptsChanges(): bool
    {
        return $this === self::Active || $this === self::Inactive;
    }
}
