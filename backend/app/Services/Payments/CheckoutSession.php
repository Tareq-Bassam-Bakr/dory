<?php

namespace App\Services\Payments;

/**
 * A hosted payment opened with the provider. The success indicator is a
 * secret: only its SHA-256 hash is stored.
 */
final readonly class CheckoutSession
{
    public function __construct(
        public string $sessionId,
        public ?string $successIndicator = null,
    ) {}
}
