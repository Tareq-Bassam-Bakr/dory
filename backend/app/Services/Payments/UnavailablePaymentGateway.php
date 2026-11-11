<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Exceptions\BillingException;
use App\Models\PaymentCheckout;
use Illuminate\Http\Request;

/**
 * No online payment provider is set up (PAYMENT_PROVIDER=none, or missing
 * credentials). Plans can still be paid by bank transfer, cash or mobile
 * wallet and recorded by a platform admin (docs/PAYMENTS.md).
 */
final class UnavailablePaymentGateway implements PaymentGateway
{
    public function __construct(private readonly string $provider = 'none') {}

    public function name(): string
    {
        return $this->provider;
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function start(PaymentCheckout $checkout, string $returnUrl): CheckoutSession
    {
        throw BillingException::paymentsUnavailable();
    }

    public function verify(PaymentCheckout $checkout): PaymentVerification
    {
        return PaymentVerification::pending('provider_not_configured');
    }

    public function notification(Request $request): WebhookNotice
    {
        throw BillingException::invalidNotification();
    }

    public function acceptsReturn(PaymentCheckout $checkout, Request $request): bool
    {
        return false;
    }

    public function pageData(PaymentCheckout $checkout): array
    {
        throw BillingException::paymentsUnavailable();
    }
}
