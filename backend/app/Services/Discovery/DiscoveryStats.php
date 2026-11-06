<?php

namespace App\Services\Discovery;

/**
 * What discovery computed about one business or branch for the current
 * request, attached to the model as the "discovery" relation.
 */
final readonly class DiscoveryStats
{
    public function __construct(
        public int $branchesCount = 0,
        public int $servicesCount = 0,
        public ?string $city = null,
        public ?string $country = null,
        public ?float $distanceKm = null,
        public int $openQueues = 0,
        public int $acceptingQueues = 0,
        public ?int $lowestWaitMinutes = null,
        public bool $isFavorite = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function queueSummary(): array
    {
        return [
            'open_queues_count' => $this->openQueues,
            'accepting_queues_count' => $this->acceptingQueues,
            'lowest_wait_minutes' => $this->lowestWaitMinutes,
        ];
    }

    public function roundedDistance(): ?float
    {
        return $this->distanceKm === null ? null : round($this->distanceKm, 2);
    }
}
