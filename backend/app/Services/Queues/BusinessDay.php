<?php

namespace App\Services\Queues;

use App\Models\Branch;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * The branch-local calendar date. Ticket numbers reset when it changes
 * (local midnight), whatever the server's own timezone is.
 */
final class BusinessDay
{
    public static function today(Branch $branch): CarbonImmutable
    {
        try {
            return CarbonImmutable::now($branch->timezone)->startOfDay();
        } catch (Throwable) {
            // An invalid stored zone must not stop the queue; fall back to UTC.
            return CarbonImmutable::now('UTC')->startOfDay();
        }
    }
}
