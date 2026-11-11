<?php

namespace App\Support;

/**
 * Amounts are stored in minor units (cents). Providers want decimals.
 */
final class Money
{
    /** ISO 4217 currencies without minor units that DORY may plausibly meet. */
    private const ZERO_DECIMAL = ['JPY', 'KRW', 'VND', 'CLP', 'ISK', 'UGX', 'XAF', 'XOF'];

    /** And with three. */
    private const THREE_DECIMAL = ['BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND'];

    public static function exponent(string $currency): int
    {
        $currency = strtoupper($currency);

        return match (true) {
            in_array($currency, self::ZERO_DECIMAL, true) => 0,
            in_array($currency, self::THREE_DECIMAL, true) => 3,
            default => 2,
        };
    }

    /**
     * 1500 USD → "15.00"; 2500 IQD → "2.500".
     */
    public static function decimal(int $minor, string $currency): string
    {
        $exponent = self::exponent($currency);
        if ($exponent === 0) {
            return (string) $minor;
        }
        $divisor = 10 ** $exponent;

        return sprintf('%d.%0'.$exponent.'d', intdiv($minor, $divisor), $minor % $divisor);
    }

    /**
     * "15.00" USD → 1500, or null when the text is not an amount.
     */
    public static function minor(string|int|float $decimal, string $currency): ?int
    {
        $text = trim((string) $decimal);
        if (! preg_match('/^\d+(\.\d+)?$/', $text)) {
            return null;
        }

        return (int) round(((float) $text) * (10 ** self::exponent($currency)));
    }
}
