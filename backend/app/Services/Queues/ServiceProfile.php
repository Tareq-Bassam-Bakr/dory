<?php

namespace App\Services\Queues;

/**
 * How long one customer takes at a queue, from its recent history
 * (docs/WAIT_TIME_ESTIMATION.md).
 *
 * - secondsPerCustomer: the average of recent service times, or the
 *   configured duration while there is too little history (basis).
 * - lowSeconds / highSeconds: the 25th and 75th percentile of the same recent
 *   service times; null without history.
 * - samples: how many completed tickets the figures come from.
 * - assignedServers: staff assigned to the queue, at least 1.
 */
final readonly class ServiceProfile
{
    public function __construct(
        public int $secondsPerCustomer,
        public string $basis,
        public int $assignedServers,
        public int $samples,
        public ?int $lowSeconds,
        public ?int $highSeconds,
    ) {}

    public function fromHistory(): bool
    {
        return $this->basis === WaitEstimate::BASIS_HISTORY;
    }
}
