<?php

namespace App\Services\Discovery;

/**
 * Portable LIKE patterns. "%" and "_" typed by a customer are literal text.
 *
 * "!" is the escape character because a backslash is written differently in
 * MySQL and SQLite string literals. Patterns are always bound, never
 * concatenated into SQL. Use with: `column like ? escape '!'`.
 */
final class SearchTerm
{
    public const LIKE = " like ? escape '!'";

    public static function contains(string $term): string
    {
        return '%'.self::escape($term).'%';
    }

    public static function prefix(string $term): string
    {
        return self::escape($term).'%';
    }

    private static function escape(string $term): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
    }
}
