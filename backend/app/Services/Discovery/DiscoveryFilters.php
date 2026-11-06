<?php

namespace App\Services\Discovery;

/**
 * Validated search parameters of the discovery endpoints
 * (docs/SEARCH_AND_FILTERS.md).
 */
final readonly class DiscoveryFilters
{
    public const SORT_RELEVANCE = 'relevance';

    public const SORT_NEAREST = 'nearest';

    public const SORT_WAIT = 'wait';

    public const SORT_RATING = 'rating';

    public const SORTS = [self::SORT_RELEVANCE, self::SORT_NEAREST, self::SORT_WAIT, self::SORT_RATING];

    public function __construct(
        public ?string $q = null,
        public ?string $category = null,
        public ?string $country = null,
        public ?string $city = null,
        public ?GeoCircle $circle = null,
        public bool $openQueue = false,
        public bool $accepting = false,
        public ?int $maxWaitMinutes = null,
        public ?float $minRating = null,
        public ?string $service = null,
        public string $sort = self::SORT_RELEVANCE,
    ) {}

    /**
     * Results depend on estimated waits, which are computed in PHP.
     */
    public function needsWaitEstimates(): bool
    {
        return $this->sort === self::SORT_WAIT || $this->maxWaitMinutes !== null;
    }
}
