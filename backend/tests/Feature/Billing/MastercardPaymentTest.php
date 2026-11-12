<?php

namespace Tests\Feature\Billing;

use App\Enums\CheckoutStatus;
use App\Enums\InvoiceStatus;
use App\Models\BillingInvoice;
use App\Models\Business;
use App\Models\PaymentCheckout;
use App\Models\PaymentWebhookEvent;
use App\Models\SubscriptionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsSubscriptions;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * Online payment through Mastercard Gateway Hosted Checkout
 * (docs/PAYMENTS.md), against a faked gateway: no request leaves the test.
 * A plan is paid only after the server itself retrieved a CAPTURED order
 * with the right amount; browser returns and webhooks only trigger that.
 */
class MastercardPaymentTest extends TestCase
{
    use BuildsSubscriptions, BuildsTenants, RefreshDatabase;

    private const GATEWAY = 'https://gateway.test.example';

    private const MERCHANT = 'TESTDORY01';

    private const PASSWORD = 'api-password-for-tests';

    private const SECRET = 'notification-secret-for-tests';

    private User $owner;

    private Business $business;

    /** What the faked gateway answers for Retrieve Order (null: no such order). */
    private ?array $order = null;

    private int $orderStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-11 10:00:00', 'UTC'));
        config([
            'billing.provider' => 'mastercard',
            // Live online payments are off by default (Phase 09); these tests
            // switch them on deliberately, against the faked gateway below.
            'billing.online_payments' => true,
            'billing.providers.mastercard' => [
                'gateway_url' => self::GATEWAY,
                'api_version' => '100',
                'merchant_id' => self::MERCHANT,
                'api_password' => self::PASSWORD,
                'notification_secret' => self::SECRET,
                'merchant_name' => 'DORY',
                'timeout' => 20,
            ],
        ]);
        Http::preventStrayRequests();
        Http::fake(function (HttpRequest $request) {
            if (str_ends_with($request->url(), '/merchant/'.self::MERCHANT.'/session')) {
                return Http::response([
                    'result' => 'SUCCESS',
                    'merchant' => self::MERCHANT,
                    'session' => ['id' => 'SESSION0002345', 'updateStatus' => 'NO_UPDATE', 'version' => 'a1b2'],
                    'successIndicator' => 'indicator-4f6a',
                ]);
            }
            if (str_contains($request->url(), '/merchant/'.self::MERCHANT.'/order/')) {
                return $this->order === null
                    ? Http::response(['result' => 'ERROR', 'error' => ['cause' => 'INVALID_REQUEST', 'explanation' => 'Unable to find order', 'field' => 'order.id']], 400)
                    : Http::response($this->order, $this->orderStatus);
            }

            return null;
        });
        $this->owner = User::factory()->create();
        $this->business = $this->businessOwnedBy($this->owner);
    }

    private function startCheckout(string $plan = 'pro', string $period = 'monthly'): TestResponse
    {
        return $this->actingAsUser($this->owner, 'POST', '/api/v1/businesses/'.$this->business->public_id.'/subscription/checkout', [
            'plan' => $plan,
            'billing_period' => $period,
        ]);
    }

    private function checkout(): PaymentCheckout
    {
        return PaymentCheckout::query()->latest('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function captured(PaymentCheckout $checkout, float $amount = 15.0, string $currency = 'USD', array $extra = []): void
    {
        $this->order = $extra + [
            'id' => $checkout->public_id,
            'status' => 'CAPTURED',
            'result' => 'SUCCESS',
            'amount' => $amount,
            'currency' => $currency,
            'totalCapturedAmount' => $amount,
        ];
    }

    private function returnUri(PaymentCheckout $checkout, string $indicator = 'indicator-4f6a'): string
    {
        return '/billing/checkout/'.$checkout->public_id.'/return?lang=en&resultIndicator='.$indicator.'&sessionVersion=a1b2';
    }

    private function notify(PaymentCheckout|string $order, string $id = 'notification-1', ?string $secret = self::SECRET): TestResponse
    {
        return $this->postJson('/api/v1/billing/webhooks/mastercard', [
            'order' => ['id' => $order instanceof PaymentCheckout ? $order->public_id : $order, 'status' => 'CAPTURED'],
            'result' => 'SUCCESS',
            'transaction' => ['type' => 'PAYMENT'],
            'sourceOfFunds' => ['provided' => ['card' => ['number' => '512345xxxxxx0008']]],
        ], array_filter([
            'X-Notification-Secret' => $secret,
            'X-Notification-Id' => $id,
            'X-Notification-Attempt' => '1',
        ]));
    }

    private function planCode(): string
    {
        return $this->actingAsUser($this->owner, 'GET', '/api/v1/businesses/'.$this->business->public_id.'/subscription')
            ->json('data.plan.code').'/'.$this->actingAsUser($this->owner, 'GET', '/api/v1/businesses/'.$this->business->public_id.'/subscription')
            ->json('data.status');
    }

    // ----- Starting ------------------------------------------------------------

    public function test_live_payments_stay_off_until_switched_on_even_with_credentials(): void
    {
        config(['billing.online_payments' => false]);

        $this->assertApiError($this->startCheckout(), 503, 'payments_unavailable');
        $this->actingAsUser($this->owner, 'GET', '/api/v1/businesses/'.$this->business->public_id.'/subscription')
            ->assertJsonPath('data.payments.online', false);
        $this->assertSame(0, PaymentCheckout::query()->count());
        Http::assertNothingSent();
    }

    public function test_the_owner_starts_a_hosted_checkout(): void
    {
        $response = $this->startCheckout();

        $this->assertApiSuccess($response, 201)
            ->assertJsonPath('data.checkout.status', 'pending')
            ->assertJsonPath('data.checkout.amount', '15.00')
            ->assertJsonPath('data.checkout.currency', 'USD')
            ->assertJsonPath('data.checkout.provider', 'mastercard');
        $checkout = $this->checkout();
        $this->assertStringContainsString('/billing/checkout/'.$checkout->public_id.'/pay?', $response->json('data.payment_url'));
        $this->assertStringContainsString('signature=', $response->json('data.payment_url'));

        Http::assertSent(function (HttpRequest $request) use ($checkout): bool {
            return $request->method() === 'POST'
                && $request->url() === self::GATEWAY.'/api/rest/version/100/merchant/'.self::MERCHANT.'/session'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('merchant.'.self::MERCHANT.':'.self::PASSWORD))
                && $request['apiOperation'] === 'INITIATE_CHECKOUT'
                && $request['interaction']['operation'] === 'PURCHASE'
                && $request['interaction']['returnUrl'] === route('billing.checkout.return', ['checkout' => $checkout->public_id, 'lang' => 'en'])
                && $request['order'] === [
                    'id' => $checkout->public_id,
                    'amount' => '15.00',
                    'currency' => 'USD',
                    'description' => 'DORY Pro (monthly)',
                ];
        });

        // The session is kept; the success indicator only as a hash; neither
        // is ever returned.
        $this->assertSame('SESSION0002345', $checkout->provider_session_id);
        $this->assertSame(hash('sha256', 'indicator-4f6a'), $checkout->success_indicator_hash);
        $this->assertStringNotContainsString('SESSION0002345', $response->getContent());
        $this->assertStringNotContainsString('indicator-4f6a', $response->getContent());
    }

    public function test_the_payment_page_opens_only_through_its_signed_link(): void
    {
        $url = $this->startCheckout()->json('data.payment_url');
        $path = (string) parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);
        $this->forgetAuthenticatedUser();

        $page = $this->get($path);
        $page->assertOk()
            ->assertSee(self::GATEWAY.'/static/checkout/checkout.min.js', false)
            ->assertSee('"SESSION0002345"', false)
            ->assertSee('15.00 USD');
        $this->assertStringContainsString('no-store', (string) $page->headers->get('Cache-Control'));
        // The language can be switched without breaking the signature.
        $this->get(str_replace('lang=en', 'lang=ar', $path))->assertOk()->assertSee('dir="rtl"', false);
        // Tampered or expired links are refused.
        $this->get(str_replace('signature=', 'signature=0', $path))->assertForbidden();
        $this->travel(61)->minutes();
        $this->get($path)->assertForbidden();
    }

    public function test_the_provider_being_unreachable_is_a_clear_error(): void
    {
        Http::fake(fn () => Http::failedConnection());
        Http::preventStrayRequests();
        $logged = [];
        Log::listen(function (MessageLogged $message) use (&$logged): void {
            $logged[] = $message->message.' '.json_encode($message->context);
        });

        $this->assertApiError($this->startCheckout(), 502, 'payment_provider_error');
        $this->assertSame(CheckoutStatus::Failed, $this->checkout()->status);
        $this->assertSame('provider_error', $this->checkout()->failure_reason);
        $this->assertNotEmpty($logged);
        foreach ($logged as $line) {
            $this->assertStringNotContainsString(self::PASSWORD, $line);
            $this->assertStringNotContainsString(self::SECRET, $line);
        }
    }

    // ----- Completing ------------------------------------------------------------

    public function test_a_captured_payment_activates_the_plan_exactly_once(): void
    {
        $this->startCheckout();
        $checkout = $this->checkout();
        $this->captured($checkout);
        $this->forgetAuthenticatedUser();

        $this->get($this->returnUri($checkout))->assertOk()->assertSee(__('billing.result.paid.title'));
        $this->get($this->returnUri($checkout))->assertOk();
        $this->actingAsUser($this->owner, 'GET', '/api/v1/businesses/'.$this->business->public_id.'/subscription/checkouts/'.$checkout->public_id)
            ->assertOk()
            ->assertJsonPath('data.checkout.status', 'paid')
            ->assertJsonPath('data.subscription.plan.code', 'pro')
            ->assertJsonPath('data.subscription.status', 'active');

        $invoices = BillingInvoice::query()->where('business_id', $this->business->getKey())->get();
        $this->assertCount(1, $invoices);
        $this->assertSame(InvoiceStatus::Paid, $invoices[0]->status);
        $this->assertSame('mastercard', $invoices[0]->provider);
        $this->assertSame('card', $invoices[0]->payment_method->value);
        $this->assertSame(CheckoutStatus::Paid, $checkout->fresh()->status);
    }

    public function test_the_return_alone_never_activates_anything(): void
    {
        $this->startCheckout();
        $checkout = $this->checkout();
        $this->forgetAuthenticatedUser();

        // A matching indicator, but the gateway has no captured order.
        $this->get($this->returnUri($checkout))->assertOk()->assertSee(__('billing.result.confirming.title'));
        $this->get($this->returnUri($checkout, 'forged'))->assertOk()->assertSee(__('billing.result.pending.title'));
        $this->assertSame(CheckoutStatus::Pending, $checkout->fresh()->status);
        $this->assertSame('pro/trialing', $this->planCode());
    }

    public function test_a_captured_amount_that_differs_is_refused(): void
    {
        $this->startCheckout();
        $checkout = $this->checkout();
        $this->captured($checkout, 1.0);

        $this->get($this->returnUri($checkout))->assertOk()->assertSee(__('billing.result.failed.title'));

        $this->assertSame('amount_mismatch', $checkout->fresh()->failure_reason);
        $this->assertSame(InvoiceStatus::Failed, BillingInvoice::query()->sole()->status);
        $this->assertSame('pro/trialing', $this->planCode());
    }

    public function test_a_payment_that_can_no_longer_apply_waits_for_an_admin(): void
    {
        // A Pro checkout is started during the trial, then a bank transfer for
        // Business is recorded before the card payment is captured.
        $this->startCheckout();
        $checkout = $this->checkout();
        $this->paidPlan($this->business, 'business');
        $this->captured($checkout);

        $this->get($this->returnUri($checkout))->assertOk()->assertSee(__('billing.result.failed.title'));
        $this->get($this->returnUri($checkout))->assertOk();

        $this->assertSame('needs_review', $checkout->fresh()->failure_reason);
        $this->assertSame('business/active', $this->planCode());
        // Flagged once, however often it is checked.
        $this->assertSame(1, SubscriptionEvent::query()->where('type', 'payment_failed')->count());
        $this->assertSame(1, BillingInvoice::query()->where('status', 'failed')->count());
    }

    public function test_a_declined_payment_is_recorded_and_changes_nothing(): void
    {
        $this->travel(15)->days();
        $this->startCheckout('business', 'yearly');
        $checkout = $this->checkout();
        $this->order = ['id' => $checkout->public_id, 'status' => 'FAILED', 'result' => 'FAILURE', 'amount' => 490.0, 'currency' => 'USD'];

        $this->get($this->returnUri($checkout))->assertOk()->assertSee(__('billing.result.failed.title'));

        $this->assertSame('free/expired', $this->planCode());
        $this->actingAsUser($this->owner, 'GET', '/api/v1/businesses/'.$this->business->public_id.'/invoices')
            ->assertJsonPath('data.0.status', 'failed')
            ->assertJsonPath('data.0.failure_reason', 'failed')
            ->assertJsonPath('data.0.amount', '490.00');
    }

    public function test_an_unpaid_checkout_expires_and_a_late_payment_still_counts(): void
    {
        $this->startCheckout();
        $checkout = $this->checkout();

        $this->travel(80)->minutes();
        $this->artisan('billing:sweep')->assertSuccessful();
        $this->assertSame(CheckoutStatus::Expired, $checkout->fresh()->status);

        // The bank captured it at the last minute and notifies now.
        $this->captured($checkout);
        $this->notify($checkout)->assertOk()->assertJsonPath('data.result', 'processed');
        $this->assertSame(CheckoutStatus::Paid, $checkout->fresh()->status);
        $this->assertSame('pro/active', $this->planCode());
    }

    // ----- Webhooks ------------------------------------------------------------

    public function test_webhooks_need_the_shared_secret(): void
    {
        $this->startCheckout();
        $checkout = $this->checkout();
        $this->captured($checkout);
        $this->forgetAuthenticatedUser();

        $this->assertApiError($this->notify($checkout, secret: 'wrong'), 401, 'invalid_notification');
        $this->assertApiError($this->notify($checkout, secret: null), 401, 'invalid_notification');
        $this->assertApiError($this->postJson('/api/v1/billing/webhooks/stripe', [], ['X-Notification-Secret' => self::SECRET]), 401, 'invalid_notification');
        $this->assertSame(CheckoutStatus::Pending, $checkout->fresh()->status);
        $this->assertSame(0, PaymentWebhookEvent::query()->count());
    }

    public function test_a_webhook_is_verified_with_the_gateway_and_processed_once(): void
    {
        $this->startCheckout();
        $checkout = $this->checkout();
        $this->captured($checkout);
        $this->forgetAuthenticatedUser();

        $this->notify($checkout)->assertOk()->assertJsonPath('data.result', 'processed');
        $this->notify($checkout)->assertOk()->assertJsonPath('data.result', 'duplicate');

        $this->assertSame(1, BillingInvoice::query()->count());
        Http::assertSentCount(2); // the session, then one Retrieve Order
        $event = PaymentWebhookEvent::query()->sole();
        $this->assertSame('processed', $event->status);
        $this->assertSame('CAPTURED', $event->summary['order_status']);
        // No card data is kept from the notification.
        $this->assertStringNotContainsString('512345', json_encode($event->summary));
    }

    public function test_a_webhook_for_an_unknown_order_is_acknowledged_and_ignored(): void
    {
        $this->notify('01JZZZZZZZZZZZZZZZZZZZZZZZ', 'notification-9')->assertOk()->assertJsonPath('data.result', 'ignored');

        $this->assertSame('unknown_order', PaymentWebhookEvent::query()->sole()->error);
    }

    public function test_a_webhook_that_cannot_be_verified_is_retried_by_the_provider(): void
    {
        $this->startCheckout();
        $checkout = $this->checkout();
        $this->captured($checkout);
        $this->orderStatus = 500;
        $this->forgetAuthenticatedUser();

        $this->assertApiError($this->notify($checkout), 502, 'payment_provider_error');
        $this->assertSame('failed', PaymentWebhookEvent::query()->sole()->status);

        $this->orderStatus = 200;
        $this->notify($checkout)->assertOk()->assertJsonPath('data.result', 'processed');
        $this->assertSame(CheckoutStatus::Paid, $checkout->fresh()->status);
    }
}
