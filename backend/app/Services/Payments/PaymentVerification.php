<?php

namespace App\Services\Payments;

/**
 * What the provider says about an order.
 *
 * - paid: the full amount was captured (amount and currency are checked
 *   against the checkout before anything is activated).
 * - failed: the payer cannot complete this order any more.
 * - pending: nothing final yet (not paid, or still processing).
 */
final readonly class PaymentVerification
{
    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const PENDING = 'pending';

    private function __construct(
        public string $status,
        public ?int $amountMinor = null,
        public ?string $currency = null,
        public ?string $reference = null,
        public ?string $reason = null,
    ) {}

    public static function paid(int $amountMinor, string $currency, string $reference): self
    {
        return new self(self::PAID, $amountMinor, strtoupper($currency), $reference);
    }

    public static function failed(string $reason, ?string $reference = null): self
    {
        return new self(self::FAILED, reference: $reference, reason: $reason);
    }

    public static function pending(?string $reason = null): self
    {
        return new self(self::PENDING, reason: $reason);
    }
}
