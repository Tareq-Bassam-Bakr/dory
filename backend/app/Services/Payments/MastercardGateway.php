<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Exceptions\BillingException;
use App\Models\PaymentCheckout;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Mastercard Gateway (MPGS) Hosted Checkout, through an acquiring bank
 * (docs/PAYMENTS.md). Card details are entered on the gateway's page only.
 *
 * 1. start(): INITIATE_CHECKOUT opens a session for the order (the
 *    checkout's public id) and returns a success indicator, kept hashed.
 * 2. The payer pays on the gateway's page and is sent back with a
 *    resultIndicator (acceptsReturn() compares it).
 * 3. verify(): Retrieve Order. Only a CAPTURED order with the exact amount and
 *    currency is a payment.
 * 4. Webhook notifications carry the merchant's notification secret in
 *    X-Notification-Secret and an id in X-Notification-Id; they only trigger
 *    verify().
 *
 * Not verified against a live gateway yet: it needs a merchant account from
 * an acquiring bank (docs/PAYMENTS.md, "Required actions").
 */
final class MastercardGateway implements PaymentGateway
{
    private const FAILED_STATUSES = ['FAILED', 'CANCELLED', 'AUTHENTICATION_UNSUCCESSFUL'];

    private const REVERSED_STATUSES = ['REFUNDED', 'PARTIALLY_REFUNDED', 'EXCESSIVELY_REFUNDED', 'CHARGEBACK_PROCESSED', 'REFUND_REQUESTED'];

    /**
     * @param  array{gateway_url?: ?string, api_version?: ?string, merchant_id?: ?string, api_password?: ?string, notification_secret?: ?string, merchant_name?: ?string, timeout?: ?int}  $config
     */
    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'mastercard';
    }

    public function isConfigured(): bool
    {
        return str_starts_with($this->gatewayUrl(), 'https://')
            && $this->merchantId() !== ''
            && (string) ($this->config['api_password'] ?? '') !== '';
    }

    public function start(PaymentCheckout $checkout, string $returnUrl): CheckoutSession
    {
        if (! $this->isConfigured()) {
            throw BillingException::paymentsUnavailable();
        }

        $response = $this->send(fn (PendingRequest $http) => $http->post($this->api('session'), [
            'apiOperation' => 'INITIATE_CHECKOUT',
            'interaction' => [
                'operation' => 'PURCHASE',
                'returnUrl' => $returnUrl,
                'merchant' => ['name' => (string) ($this->config['merchant_name'] ?? 'DORY')],
                'displayControl' => ['billingAddress' => 'HIDE', 'shipping' => 'HIDE'],
            ],
            'order' => [
                'id' => $checkout->public_id,
                'amount' => Money::decimal($checkout->amount_minor, $checkout->currency),
                'currency' => $checkout->currency,
                'description' => $this->description($checkout),
            ],
        ]), 'initiate_checkout');

        $sessionId = $response->json('session.id');
        if ($response->json('result') !== 'SUCCESS' || ! is_string($sessionId) || $sessionId === '') {
            $this->logFailure('initiate_checkout', $response);

            throw BillingException::providerError();
        }

        $indicator = $response->json('successIndicator');

        return new CheckoutSession($sessionId, is_string($indicator) && $indicator !== '' ? $indicator : null);
    }

    public function verify(PaymentCheckout $checkout): PaymentVerification
    {
        if (! $this->isConfigured()) {
            return PaymentVerification::pending('provider_not_configured');
        }

        $response = $this->send(
            fn (PendingRequest $http) => $http->get($this->api('order/'.rawurlencode($checkout->public_id))),
            'retrieve_order',
            acceptNotFound: true,
        );
        if (! $response->successful()) {
            if ($response->status() === 404 || $response->json('error.cause') === 'INVALID_REQUEST') {
                // No order yet: the payer has not paid (or not finished).
                return PaymentVerification::pending('not_paid');
            }
            $this->logFailure('retrieve_order', $response);

            throw BillingException::providerError();
        }

        $status = strtoupper((string) $response->json('status'));
        $reference = (string) ($response->json('id') ?? $checkout->public_id);

        if ($status === 'CAPTURED') {
            $captured = Money::minor((string) ($response->json('totalCapturedAmount') ?? $response->json('amount') ?? ''), $checkout->currency);
            $currency = strtoupper((string) $response->json('currency'));
            if ($captured === null || $currency === '') {
                return PaymentVerification::failed('unreadable_amount', $reference);
            }

            return PaymentVerification::paid($captured, $currency, $reference);
        }
        if (in_array($status, self::FAILED_STATUSES, true)) {
            return PaymentVerification::failed(strtolower($status), $reference);
        }
        if (in_array($status, self::REVERSED_STATUSES, true)) {
            return PaymentVerification::failed('reversed', $reference);
        }

        return PaymentVerification::pending(strtolower($status));
    }

    public function notification(Request $request): WebhookNotice
    {
        $secret = (string) ($this->config['notification_secret'] ?? '');
        $given = (string) $request->header('X-Notification-Secret', '');
        if ($secret === '' || $given === '' || ! hash_equals($secret, $given)) {
            throw BillingException::invalidNotification();
        }

        $eventId = (string) $request->header('X-Notification-Id', '');
        $orderId = $request->json('order.id');
        if ($eventId === '') {
            // Older gateway versions: fall back to the order and transaction.
            $eventId = hash('sha256', $request->getContent());
        }

        return new WebhookNotice(
            mb_substr($eventId, 0, 120),
            is_string($orderId) && $orderId !== '' ? $orderId : null,
            array_filter([
                'order_status' => $this->scalar($request->json('order.status')),
                'result' => $this->scalar($request->json('result')),
                'transaction_type' => $this->scalar($request->json('transaction.type')),
                'gateway_code' => $this->scalar($request->json('response.gatewayCode')),
                'attempt' => $this->scalar($request->header('X-Notification-Attempt')),
            ], fn ($value) => $value !== null),
        );
    }

    public function acceptsReturn(PaymentCheckout $checkout, Request $request): bool
    {
        $indicator = $request->query('resultIndicator');

        return is_string($indicator) && $indicator !== ''
            && $checkout->success_indicator_hash !== null
            && hash_equals($checkout->success_indicator_hash, hash('sha256', $indicator));
    }

    public function pageData(PaymentCheckout $checkout): array
    {
        if (! $this->isConfigured() || $checkout->provider_session_id === null) {
            throw BillingException::paymentsUnavailable();
        }

        return [
            'script' => $this->gatewayUrl().'/static/checkout/checkout.min.js',
            'origin' => $this->gatewayUrl(),
            'session' => $checkout->provider_session_id,
        ];
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     */
    private function send(callable $call, string $operation, bool $acceptNotFound = false): Response
    {
        $http = Http::withBasicAuth('merchant.'.$this->merchantId(), (string) $this->config['api_password'])
            ->acceptJson()
            ->asJson()
            ->timeout(max(5, (int) ($this->config['timeout'] ?? 20)));

        try {
            $response = $call($http);
        } catch (ConnectionException) {
            Log::warning('Payment provider unreachable', ['provider' => $this->name(), 'operation' => $operation]);

            throw BillingException::providerError();
        }

        if ($response->successful() || ($acceptNotFound && in_array($response->status(), [400, 404], true))) {
            return $response;
        }
        $this->logFailure($operation, $response);

        throw BillingException::providerError();
    }

    /**
     * Status and the gateway's error cause only: never credentials, never
     * payer data.
     */
    private function logFailure(string $operation, Response $response): void
    {
        Log::warning('Payment provider request failed', [
            'provider' => $this->name(),
            'operation' => $operation,
            'status' => $response->status(),
            'cause' => $this->scalar($response->json('error.cause')),
            'field' => $this->scalar($response->json('error.field')),
        ]);
    }

    private function api(string $path): string
    {
        return sprintf(
            '%s/api/rest/version/%s/merchant/%s/%s',
            $this->gatewayUrl(),
            rawurlencode((string) ($this->config['api_version'] ?? '100')),
            rawurlencode($this->merchantId()),
            $path,
        );
    }

    private function gatewayUrl(): string
    {
        return rtrim((string) ($this->config['gateway_url'] ?? ''), '/');
    }

    private function merchantId(): string
    {
        return (string) ($this->config['merchant_id'] ?? '');
    }

    private function description(PaymentCheckout $checkout): string
    {
        return mb_substr(sprintf('DORY %s (%s)', $checkout->plan->name_en, $checkout->billing_period->value), 0, 127);
    }

    private function scalar(mixed $value): string|int|float|bool|null
    {
        return is_scalar($value) ? (is_string($value) ? mb_substr($value, 0, 120) : $value) : null;
    }
}
