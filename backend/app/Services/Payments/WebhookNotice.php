<?php

namespace App\Services\Payments;

/**
 * An authenticated webhook notification: its id (for de-duplication), the
 * order it is about, and a few non-sensitive fields kept for support.
 */
final readonly class WebhookNotice
{
    /**
     * @param  array<string, scalar|null>  $summary
     */
    public function __construct(
        public string $eventId,
        public ?string $orderId,
        public array $summary = [],
    ) {}
}
