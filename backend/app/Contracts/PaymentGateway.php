<?php

namespace App\Contracts;

use App\Exceptions\BillingException;
use App\Models\PaymentCheckout;
use App\Services\Payments\CheckoutSession;
use App\Services\Payments\PaymentVerification;
use App\Services\Payments\WebhookNotice;
use Illuminate\Http\Request;

/**
 * An online payment provider (docs/PAYMENTS.md). DORY never sees card
 * details: the payer pays on the provider's hosted page, and a payment counts
 * only after the server asked the provider itself (verify()). The browser's
 * return and webhook notifications are only reasons to ask.
 */
interface PaymentGateway
{
    /**
     * Stored with checkouts, invoices and webhook events, e.g. "mastercard".
     */
    public function name(): string;

    /**
     * All credentials are present: online payment can be offered.
     */
    public function isConfigured(): bool;

    /**
     * Opens a hosted payment for the checkout (its public id is the order id).
     *
     * @throws BillingException payments_unavailable or payment_provider_error
     */
    public function start(PaymentCheckout $checkout, string $returnUrl): CheckoutSession;

    /**
     * The order's real state, from the provider's API.
     *
     * @throws BillingException payment_provider_error when the provider cannot be reached
     */
    public function verify(PaymentCheckout $checkout): PaymentVerification;

    /**
     * Authenticates a webhook notification and reads which order it is about.
     *
     * @throws BillingException invalid_notification when it is not authentic
     */
    public function notification(Request $request): WebhookNotice;

    /**
     * The payer's browser came back with the result indicator that belongs
     * to this checkout. A hint only: verify() decides.
     */
    public function acceptsReturn(PaymentCheckout $checkout, Request $request): bool;

    /**
     * What the hosted payment page needs (script URL, session id).
     *
     * @return array<string, string>
     */
    public function pageData(PaymentCheckout $checkout): array;
}
