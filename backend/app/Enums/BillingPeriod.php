<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

/**
 * How long a paid subscription period lasts (docs/SUBSCRIPTIONS.md).
 */
enum BillingPeriod: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    /**
     * End of a period starting at $start (31 January + 1 month = 28/29 February).
     */
    public function endFrom(Carbon $start): Carbon
    {
        return match ($this) {
            self::Monthly => $start->copy()->addMonthNoOverflow(),
            self::Yearly => $start->copy()->addYearNoOverflow(),
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $period): string => $period->value, self::cases());
    }
}
