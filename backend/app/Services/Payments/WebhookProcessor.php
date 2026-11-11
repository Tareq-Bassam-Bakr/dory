<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Exceptions\BillingException;
use App\Models\PaymentCheckout;
use App\Models\PaymentWebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Throwable;

/**
 * Payment provider notifications (docs/PAYMENTS.md, "Webhooks").
 *
 * - Authenticated by the gateway (shared secret); anything else is a 401.
 * - Stored once per provider event id: repeated deliveries are answered
 *   without doing anything again.
 * - The body is never trusted for the payment state: the notification only
 *   makes CheckoutService ask the provider (finalize()).
 * - If that fails, the event is kept as "failed" and the provider's retry
 *   processes it again.
 */
final class WebhookProcessor
{
    public const PROCESSED = 'processed';

    public const DUPLICATE = 'duplicate';

    public const IGNORED = 'ignored';

    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly CheckoutService $checkouts,
    ) {}

    public function handle(string $provider, Request $request): string
    {
        if ($provider !== $this->gateway->name() || ! $this->gateway->isConfigured()) {
            throw BillingException::invalidNotification();
        }
        $notice = $this->gateway->notification($request);

        try {
            $event = PaymentWebhookEvent::query()->firstOrCreate(
                ['provider' => $provider, 'event_id' => $notice->eventId],
                [
                    'status' => 'received',
                    'summary' => $notice->summary + ['order' => $notice->orderId],
                    'received_at' => now(),
                ],
            );
        } catch (UniqueConstraintViolationException) {
            // The same notification, delivered twice at the same moment.
            return self::DUPLICATE;
        }
        if (in_array($event->status, [self::PROCESSED, self::IGNORED], true)) {
            return self::DUPLICATE;
        }

        $checkout = $notice->orderId === null ? null : PaymentCheckout::query()
            ->where('public_id', $notice->orderId)
            ->where('provider', $provider)
            ->first();
        if ($checkout === null) {
            $event->forceFill(['status' => self::IGNORED, 'error' => 'unknown_order', 'processed_at' => now()])->saveOrFail();

            return self::IGNORED;
        }

        try {
            $this->checkouts->finalize($checkout);
        } catch (Throwable $e) {
            $event->forceFill(['status' => 'failed', 'error' => mb_substr(class_basename($e), 0, 255)])->saveOrFail();

            throw $e;
        }
        $event->forceFill(['status' => self::PROCESSED, 'error' => null, 'processed_at' => now()])->saveOrFail();

        return self::PROCESSED;
    }
}
