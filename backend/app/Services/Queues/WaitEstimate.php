<?php

namespace App\Services\Queues;

/**
 * An estimated wait. "basis" says where the per-customer duration came from:
 * "history" (recent completed tickets of this queue) or "configured" (the
 * queue's or service's estimated duration, used until there is enough
 * history). Estimates are approximate and are labelled as such to customers.
 */
final readonly class WaitEstimate
{
    public const BASIS_HISTORY = 'history';

    public const BASIS_CONFIGURED = 'configured';

    public function __construct(
        public int $seconds,
        public string $basis,
        public int $secondsPerCustomer,
        public int $servers,
    ) {}

    public function minutes(): int
    {
        return (int) ceil($this->seconds / 60);
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'seconds' => $this->seconds,
            'minutes' => $this->minutes(),
            'basis' => $this->basis,
        ];
    }
}
