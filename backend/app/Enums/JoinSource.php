<?php

namespace App\Enums;

/**
 * How a ticket was created.
 */
enum JoinSource: string
{
    /** Inside the DORY app: a signed-in customer, or a guest who found the queue through discovery. */
    case App = 'app';

    /** A guest, through the public link or QR code (API or web page). */
    case PublicLink = 'public_link';
}
