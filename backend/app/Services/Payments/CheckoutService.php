<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\BillingPeriod;
use App\Enums\CheckoutStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\BillingException;
use App\Models\BillingInvoice;
use App\Models\Business;
use App\Models\PaymentCheckout;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Billing\Entitlements;
use App\Services\Billing\SubscriptionService;
use App\Services\Billing\SubscriptionState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * Online payments for plans (docs/PAYMENTS.md).
 *
 * start() opens a hosted payment and returns the link of DORY's payment page.
 * finalize() asks the provider what happened and applies it exactly once;
 * the return page, webhooks, the app and the scheduler all call it, in any
 * order and as often as they like.
 */
final class CheckoutService
{
    /** Pending checkouts are given up this long after their link expired. */
    private const EXPIRY_MARGIN_MINUTES = 15;

    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly SubscriptionService $subscriptions,
        private readonly Entitlements $entitlements,
    ) {}

    public function available(): bool
    {
        return (bool) config('billing.enabled') && $this->gateway->isConfigured();
    }

    public function provider(): string
    {
        return $this->gateway->name();
    }

    /**
     * @return array{0: PaymentCheckout, 1: string} The checkout and its payment page link.
     */
    public function start(Business $business, User $owner, SubscriptionPlan $plan, BillingPeriod $period, ?string $currency, string $locale): array
    {
        if (! $this->available()) {
            throw BillingException::paymentsUnavailable();
        }
        $currency = strtoupper($currency ?: (string) config('billing.currency'));
        $price = $plan->is_active && ! $plan->is_free ? $plan->priceFor($period, $currency) : null;
        if ($price === null) {
            throw BillingException::planNotAvailable();
        }
        $state = $this->entitlements->state($business);
        if ($state->status === SubscriptionState::ACTIVE && $state->isPaid() && $plan->rank() < $state->subscribedPlan->rank()) {
            throw BillingException::changeNotAllowed('use_downgrade');
        }

        $checkout = new PaymentCheckout;
        $checkout->forceFill([
            'business_id' => $business->getKey(),
            'plan_id' => $plan->getKey(),
            'billing_period' => $period,
            'currency' => $currency,
            'amount_minor' => $price->amount_minor,
            'provider' => $this->gateway->name(),
            'status' => CheckoutStatus::Pending,
            'created_by' => $owner->getKey(),
            'expires_at' => now()->addMinutes(max(5, (int) config('billing.checkout_expires_minutes'))),
        ])->saveOrFail();
        $checkout->setRelation('plan', $plan);
        $locale = in_array($locale, ['en', 'ar'], true) ? $locale : 'en';

        try {
            $session = $this->gateway->start(
                $checkout,
                route('billing.checkout.return', ['checkout' => $checkout->public_id, 'lang' => $locale]),
            );
        } catch (BillingException $e) {
            $checkout->forceFill([
                'status' => CheckoutStatus::Failed,
                'failure_reason' => 'provider_error',
                'completed_at' => now(),
            ])->saveOrFail();

            throw $e;
        }

        $checkout->forceFill([
            'provider_session_id' => $session->sessionId,
            'success_indicator_hash' => $session->successIndicator === null ? null : hash('sha256', $session->successIndicator),
        ])->saveOrFail();

        // Signed relative to the host, so it also works behind a proxy; the
        // language is outside the signature (the page may switch it).
        $url = url(URL::temporarySignedRoute(
            'billing.checkout.pay',
            $checkout->expires_at,
            ['checkout' => $checkout->public_id],
            absolute: false,
        )).'&lang='.$locale;

        return [$checkout, $url];
    }

    /**
     * Asks the provider about the checkout and applies the answer once:
     *
     * - captured with the right amount: the plan is paid (also late, after
     *   the link expired or an earlier attempt failed: money taken is never
     *   ignored);
     * - captured with another amount: refused and logged for review;
     * - failed: a failed invoice is recorded, the subscription is unchanged;
     * - nothing yet: kept pending until the link expired, then expired.
     */
    public function finalize(PaymentCheckout $checkout): PaymentCheckout
    {
        if ($checkout->status === CheckoutStatus::Paid) {
            return $checkout;
        }
        // A network call: never inside a transaction or under a lock.
        $verification = $this->gateway->verify($checkout);

        return DB::transaction(function () use ($checkout, $verification): PaymentCheckout {
            $locked = PaymentCheckout::query()
                ->with(['plan.prices', 'business', 'invoice'])
                ->lockForUpdate()
                ->findOrFail($checkout->getKey());

            if ($locked->status === CheckoutStatus::Paid) {
                return $locked;
            }
            // Paid but not applicable: flagged once, a platform admin settles it.
            if ($locked->failure_reason === 'needs_review') {
                return $locked;
            }

            if ($verification->status === PaymentVerification::PAID) {
                $matches = $verification->amountMinor === $locked->amount_minor
                    && $verification->currency === strtoupper($locked->currency);
                if ($matches) {
                    $this->paid($locked, $verification);
                } elseif ($locked->failure_reason !== 'amount_mismatch') {
                    Log::error('Payment captured with an unexpected amount', [
                        'checkout' => $locked->public_id,
                        'provider' => $locked->provider,
                    ]);
                    $this->failed($locked, 'amount_mismatch', $verification->reference);
                }
            } elseif ($verification->status === PaymentVerification::FAILED) {
                if ($locked->status === CheckoutStatus::Pending) {
                    $this->failed($locked, $verification->reason ?? 'failed', $verification->reference);
                }
            } elseif ($locked->status === CheckoutStatus::Pending
                && $locked->expires_at->copy()->addMinutes(self::EXPIRY_MARGIN_MINUTES)->isPast()) {
                $locked->forceFill(['status' => CheckoutStatus::Expired, 'completed_at' => now()])->saveOrFail();
            }

            return $locked;
        });
    }

    /**
     * Scheduler: pending checkouts whose link expired get a last check.
     * Without a configured provider they simply expire. Returns how many.
     */
    public function expireStale(?Carbon $at = null): int
    {
        $before = ($at ?? now())->copy()->subMinutes(self::EXPIRY_MARGIN_MINUTES);
        $count = 0;

        PaymentCheckout::query()
            ->where('status', CheckoutStatus::Pending->value)
            ->where('expires_at', '<=', $before)
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->each(function (PaymentCheckout $checkout) use (&$count): void {
                try {
                    $this->finalize($checkout);
                    $count++;
                } catch (BillingException) {
                    // Provider unreachable: the next run tries again.
                }
            });

        return $count;
    }

    private function paid(PaymentCheckout $checkout, PaymentVerification $verification): void
    {
        $actor = $checkout->created_by === null ? null : User::query()->find($checkout->created_by);

        try {
            $invoice = $this->subscriptions->recordPayment(
                $checkout->business,
                $checkout->plan,
                $checkout->billing_period,
                $checkout->currency,
                $checkout->amount_minor,
                [
                    'provider' => $checkout->provider,
                    'method' => PaymentMethod::Card,
                    'reference' => $verification->reference ?? $checkout->public_id,
                ],
                $actor,
                $checkout->invoice,
            );
        } catch (BillingException $e) {
            // Paid, but the subscription changed meanwhile so that this
            // payment cannot apply (e.g. a bigger plan was bought in another
            // checkout). A platform admin settles it (docs/PAYMENTS.md).
            Log::error('Payment captured but not applicable', [
                'checkout' => $checkout->public_id,
                'reason' => $e->errorCode(),
            ]);
            $this->failed($checkout, 'needs_review', $verification->reference);

            return;
        }

        $checkout->forceFill([
            'status' => CheckoutStatus::Paid,
            'invoice_id' => $invoice->getKey(),
            'failure_reason' => null,
            'completed_at' => now(),
        ])->saveOrFail();
    }

    private function failed(PaymentCheckout $checkout, string $reason, ?string $reference): void
    {
        $invoice = $checkout->invoice ?? new BillingInvoice;
        $invoice->forceFill([
            'business_id' => $checkout->business_id,
            'plan_id' => $checkout->plan_id,
            'billing_period' => $checkout->billing_period,
            'currency' => $checkout->currency,
            'amount_minor' => $checkout->amount_minor,
            'status' => InvoiceStatus::Open,
            'issued_at' => $invoice->issued_at ?? now(),
            'provider' => $checkout->provider,
            'payment_method' => PaymentMethod::Card,
            'provider_reference' => $reference ?? $checkout->public_id,
        ]);
        $this->subscriptions->recordFailedPayment($checkout->business, $invoice, mb_substr($reason, 0, 120));

        $checkout->forceFill([
            'status' => CheckoutStatus::Failed,
            'invoice_id' => $invoice->getKey(),
            'failure_reason' => mb_substr($reason, 0, 120),
            'completed_at' => now(),
        ])->saveOrFail();
        $checkout->setRelation('invoice', $invoice);
    }
}
