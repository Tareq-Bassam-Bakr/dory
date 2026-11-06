<?php

namespace App\Enums;

/**
 * How a ticket was created.
 */
enum JoinSource: string
{
    /** A signed-in customer, through the authenticated API. */
    case App = 'app';

    /** A guest, through the public link or QR code (API or web page). */
    case PublicLink = 'public_link';
}
