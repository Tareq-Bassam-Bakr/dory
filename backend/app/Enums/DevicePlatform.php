<?php

namespace App\Enums;

/**
 * Platforms that can receive push notifications.
 */
enum DevicePlatform: string
{
    case Android = 'android';
    case Ios = 'ios';
}
