<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Pagination conventions for list endpoints: `?page=` and `?per_page=`.
 */
final class Pagination
{
    public const DEFAULT_PER_PAGE = 15;

    public const MAX_PER_PAGE = 100;

    /**
     * Reads `per_page` from the request, clamped to 1..MAX_PER_PAGE.
     */
    public static function perPage(Request $request): int
    {
        $perPage = filter_var($request->query('per_page'), FILTER_VALIDATE_INT);

        if ($perPage === false) {
            return self::DEFAULT_PER_PAGE;
        }

        return max(1, min(self::MAX_PER_PAGE, $perPage));
    }
}
