<?php

namespace App\Enums;

/**
 * Lifecycle state of a business (tenant).
 */
enum BusinessStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';
}
