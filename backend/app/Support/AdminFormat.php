<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Formatting for the admin dashboard views: amounts, dates in the reader's
 * language (times in UTC), and the colour of each status.
 */
final class AdminFormat
{
    public static function money(int $minor, string $currency): string
    {
        return Money::decimal($minor, $currency).' '.$currency;
    }

    public static function date(?CarbonInterface $value): string
    {
        return $value === null ? '—' : $value->copy()->utc()->locale(app()->getLocale())->translatedFormat('j M Y');
    }

    public static function dateTime(?CarbonInterface $value): string
    {
        return $value === null ? '—' : $value->copy()->utc()->locale(app()->getLocale())->translatedFormat('j M Y, H:i');
    }

    public static function month(string $yearMonth): string
    {
        return Carbon::parse($yearMonth.'-01', 'UTC')->locale(app()->getLocale())->translatedFormat('M Y');
    }

    public static function number(int|float $value): string
    {
        return number_format($value);
    }

    /**
     * A name stored per language (['en' => ..., 'ar' => ...]) in the reader's language.
     *
     * @param  array<string, string>|null  $names
     */
    public static function translated(?array $names): string
    {
        if ($names === null || $names === []) {
            return '—';
        }

        return $names[app()->getLocale()] ?? $names['en'] ?? (string) reset($names);
    }

    /**
     * Badge tone of a status or state key.
     */
    public static function tone(?string $status): string
    {
        return match ($status) {
            'active', 'paid', 'approved', 'complimentary' => 'success',
            'trialing', 'pending', 'open', 'not_reviewed' => 'info',
            'past_due', 'inactive', 'expired_link' => 'warning',
            'suspended', 'failed', 'void', 'deactivated' => 'danger',
            default => '',
        };
    }
}
