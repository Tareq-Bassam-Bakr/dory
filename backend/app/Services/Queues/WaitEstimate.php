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

    public const CONFIDENCE_HIGH = 'high';

    public const CONFIDENCE_MEDIUM = 'medium';

    public const CONFIDENCE_LOW = 'low';

    /**
     * @param  ?int  $lowSeconds  Likely range (smart estimate only): with quicker
     *                            and slower recent service times.
     * @param  ?string  $confidence  high, medium or low (smart estimate only).
     */
    public function __construct(
        public int $seconds,
        public string $basis,
        public int $secondsPerCustomer,
        public int $servers,
        public ?int $lowSeconds = null,
        public ?int $highSeconds = null,
        public ?string $confidence = null,
    ) {}

    public function minutes(): int
    {
        return (int) ceil($this->seconds / 60);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $estimate = [
            'seconds' => $this->seconds,
            'minutes' => $this->minutes(),
            'basis' => $this->basis,
        ];
        if ($this->confidence !== null) {
            $estimate['confidence'] = $this->confidence;
            $estimate['range'] = $this->lowSeconds === null || $this->highSeconds === null ? null : [
                'low_minutes' => (int) floor($this->lowSeconds / 60),
                'high_minutes' => (int) ceil($this->highSeconds / 60),
            ];
        }

        return $estimate;
    }
}
