<?php

namespace App\Support;

/**
 * Normalization and format rules for contact details (emails, phone
 * numbers, country codes, websites) shared by every request that accepts them.
 */
final class ContactInput
{
    /**
     * E.164: a plus sign followed by 7 to 15 digits, the first not zero.
     */
    public const PHONE_RULE = 'regex:/^\+[1-9]\d{6,14}$/';

    /**
     * ISO 3166-1 alpha-2, after upper-casing.
     */
    public const COUNTRY_RULE = 'regex:/^[A-Z]{2}$/';

    /**
     * Lower-cases and trims emails, strips spaces, dashes, dots and
     * parentheses from phone numbers, upper-cases country codes and trims
     * websites. Empty strings become null. Other keys are left untouched.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        if (is_string($data['email'] ?? null)) {
            $data['email'] = self::nullIfEmpty(mb_strtolower(trim($data['email'])));
        }

        if (is_string($data['phone'] ?? null)) {
            $data['phone'] = self::nullIfEmpty((string) preg_replace('/[\s\-.()]/', '', $data['phone']));
        }

        if (is_string($data['country'] ?? null)) {
            $data['country'] = self::nullIfEmpty(mb_strtoupper(trim($data['country'])));
        }

        if (is_string($data['website'] ?? null)) {
            $data['website'] = self::nullIfEmpty(trim($data['website']));
        }

        return $data;
    }

    private static function nullIfEmpty(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
