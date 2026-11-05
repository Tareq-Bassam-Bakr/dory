<?php

namespace App\Enums;

/**
 * Whether a service is offered. Archived services are soft-deleted.
 */
enum ServiceStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
