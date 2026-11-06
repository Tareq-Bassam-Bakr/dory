<?php

namespace App\Enums;

/**
 * Whether a queue configuration is in use. Inactive queues accept no
 * tickets; existing tickets can still be served.
 */
enum QueueStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
