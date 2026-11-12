<?php

namespace Tests\Feature\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\PlatformAuditAction;
use App\Models\BillingInvoice;
use App\Models\Business;
use App\Models\PlatformAuditLog;
use App\Models\User;
use App\Services\Admin\PlatformStatistics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsSubscriptions;
use Tests\Concerns\BuildsTenants;
use Tests\Concerns\UsesAdminDashboard;
use Tests\TestCase;

/**
 * Payments received outside DORY (docs/ADMIN_DASHBOARD.md, "Payments"):
 * recorded first, counted only once approved, never confused with payments
 * verified online.
 */
class AdminPaymentsTest extends TestCase
{
    use BuildsSubscriptions, BuildsTenants, RefreshDatabase, UsesAdminDashboard;

    private User $admin;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-12 09:00:00', 'UTC'));
        $this->admin = $this->platformAdmin(['name' => 'Rana Admin']);
        $this->business = $this->businessOwnedBy(User::factory()->create(), ['name' => 'Shifa Clinic']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function record(array $overrides = [], ?User $admin = null): BillingInvoice
    {
        $this->asAdmin($admin ?? $this->admin)->post('/admin/payments', array_replace([
            'business' => $this->business->public_id,
            'plan' => 'pro',
            'billing_period' => 'monthly',
            'currency' => 'USD',
            'amount' => '',
            'method' => 'bank_transfer',
            'reference' => 'TRX-0001',
            'received_at' => '2026-11-11',
            'notes' => 'Seen on the statement',
        ], $overrides))->assertRedirect();

        return BillingInvoice::query()->latest('id')->firstOrFail();
    }

    public function test_a_recorded_payment_changes_nothing_until_approved(): void
    {
        $invoice = $this->record();

        $this->assertSame(InvoiceStatus::Open, $invoice->status);
        $this->assertSame('manual', $invoice->provider);
        $this->assertNull($invoice->number);
        // The plan price, as no amount was typed.
        $this->assertSame(1500, $invoice->amount_minor);
        $this->assertSame('2026-11-11', $invoice->received_at->toDateString());
        $this->assertSame($this->admin->getKey(), $invoice->recorded_by);
        $this->assertSame('trialing', $this->subscriptionOf($this->business)->status->value);
        $this->assertSame(0, app(PlatformStatistics::class)->revenue(1)['totals']['USD']['total'] ?? 0);
        $this->assertTrue(PlatformAuditLog::query()->where('action', PlatformAuditAction::PaymentRecorded->value)->where('subject_id', $invoice->getKey())->exists());

        $this->asAdmin($this->admin)->get('/admin/payments')->assertOk()
            ->assertSee('Shifa Clinic')->assertSee(__('admin.payments.statuses.awaiting'))->assertSee(__('admin.payments.sources.manual'));
        $this->asAdmin($this->admin)->get('/admin/payments/'.$invoice->public_id)->assertOk()
            ->assertSee(__('admin.payments.approve'))->assertSee('TRX-0001');
    }

    public function test_approving_starts_the_plan_issues_the_invoice_and_counts_as_revenue(): void
    {
        $this->travel(15)->days();
        $invoice = $this->record(['plan' => 'business', 'billing_period' => 'yearly', 'received_at' => '2026-11-26']);

        $this->asAdmin($this->admin)->post('/admin/payments/'.$invoice->public_id.'/approve')->assertRedirect();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertMatchesRegularExpression('/^DORY-2026-\d{6}$/', (string) $invoice->number);
        $this->assertSame($this->admin->getKey(), $invoice->reviewed_by);
        $this->assertSame('2026-11-26', $invoice->paid_at->toDateString());
        $subscription = $this->subscriptionOf($this->business);
        $this->assertSame('business', $subscription->plan->code);
        $this->assertSame('active', $subscription->status->value);
        $this->assertSame(49000, app(PlatformStatistics::class)->revenue(1)['totals']['USD']['manual']);
        $this->assertSame(0, app(PlatformStatistics::class)->revenue(1)['totals']['USD']['gateway']);
        $this->assertTrue(PlatformAuditLog::query()->where('action', PlatformAuditAction::PaymentApproved->value)->exists());

        // The printable invoice: issued when approved, paid when received.
        $this->asAdmin($this->admin)->get('/admin/payments/'.$invoice->public_id)->assertOk()
            ->assertSee($invoice->number)->assertSee(__('admin.payments.stamp_manual'))->assertSee('490.00 USD')
            ->assertSee(__('admin.payments.issued', ['date' => '27 Nov 2026']))
            ->assertSee(__('admin.payments.paid_by', ['method' => __('admin.methods.bank_transfer'), 'date' => '26 Nov 2026']));

        // Approving twice does nothing more.
        $this->asAdmin($this->admin)->post('/admin/payments/'.$invoice->public_id.'/approve')
            ->assertSessionHas('error', __('admin.errors.not_awaiting_approval'));
        $this->assertSame(1, BillingInvoice::query()->where('status', InvoiceStatus::Paid->value)->count());
    }

    public function test_a_rejected_payment_never_counts(): void
    {
        $invoice = $this->record();

        $this->asAdmin($this->admin)->post('/admin/payments/'.$invoice->public_id.'/reject', ['reason' => ''])->assertSessionHasErrors('reason');
        $this->asAdmin($this->admin)->post('/admin/payments/'.$invoice->public_id.'/reject', ['reason' => 'Not on the statement'])
            ->assertSessionHas('status', __('admin.payments.rejected'));

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Void, $invoice->status);
        $this->assertSame('Not on the statement', $invoice->failure_reason);
        $this->assertSame('trialing', $this->subscriptionOf($this->business)->status->value);
        $this->assertSame([], app(PlatformStatistics::class)->revenue(1)['totals']);
        $this->asAdmin($this->admin)->post('/admin/payments/'.$invoice->public_id.'/approve')
            ->assertSessionHas('error', __('admin.errors.not_awaiting_approval'));
        $this->assertSame(InvoiceStatus::Void, $invoice->fresh()->status);
    }

    public function test_with_four_eyes_on_another_admin_must_approve(): void
    {
        config(['billing.manual_payments.four_eyes' => true]);
        $invoice = $this->record();

        $this->asAdmin($this->admin)->get('/admin/payments/'.$invoice->public_id)->assertOk()
            ->assertSee(__('admin.payments.four_eyes_yours'));
        $this->asAdmin($this->admin)->post('/admin/payments/'.$invoice->public_id.'/approve')
            ->assertSessionHas('error', __('admin.errors.needs_another_admin'));
        $this->assertSame(InvoiceStatus::Open, $invoice->fresh()->status);

        $this->asAdmin($this->platformAdmin())->post('/admin/payments/'.$invoice->public_id.'/approve');
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_recording_is_checked(): void
    {
        $this->record(['reference' => 'TRX-0042']);

        $post = fn (array $overrides) => $this->asAdmin($this->admin)->post('/admin/payments', array_replace([
            'business' => $this->business->public_id, 'plan' => 'pro', 'billing_period' => 'monthly', 'currency' => 'USD',
            'method' => 'cash', 'received_at' => '2026-11-11',
        ], $overrides));

        // A reference is recorded once.
        $post(['reference' => 'TRX-0042'])->assertSessionHasErrors('reference');
        // Card payments only come from the gateway.
        $post(['method' => 'card'])->assertSessionHasErrors('method');
        $post(['received_at' => '2026-12-01'])->assertSessionHasErrors('received_at');
        $post(['currency' => 'EUR'])->assertSessionHasErrors('currency');
        $post(['plan' => 'free'])->assertSessionHasErrors('plan');
        $post(['business' => 'no-such-business'])->assertSessionHasErrors('business');
        $post(['amount' => '15.001'])->assertSessionHasErrors('amount');
        $post(['amount' => '0'])->assertSessionHasErrors('amount');
        $this->assertSame(1, BillingInvoice::query()->count());

        // By slug, with a typed amount.
        $post(['business' => $this->business->slug, 'amount' => '12.50'])->assertRedirect();
        $this->assertSame(1250, BillingInvoice::query()->latest('id')->firstOrFail()->amount_minor);
    }

    public function test_recorded_and_online_payments_are_told_apart(): void
    {
        $manual = $this->record();
        $this->asAdmin($this->admin)->post('/admin/payments/'.$manual->public_id.'/approve');
        $gateway = BillingInvoice::query()->create([
            'business_id' => $this->business->getKey(),
            'plan_id' => $this->plan('pro')->getKey(),
            'billing_period' => 'monthly',
            'currency' => 'USD',
            'amount_minor' => 1500,
            'status' => InvoiceStatus::Paid,
            'issued_at' => now(),
            'paid_at' => now(),
            'provider' => 'mastercard',
            'payment_method' => 'card',
            'provider_reference' => 'ORDER-1',
        ]);

        $this->assertTrue($gateway->isGatewayVerified());
        $this->assertFalse($manual->fresh()->isGatewayVerified());
        $revenue = app(PlatformStatistics::class)->revenue(1)['totals']['USD'];
        $this->assertSame(['manual' => 1500, 'gateway' => 1500, 'total' => 3000, 'count' => 2], $revenue);

        $this->asAdmin($this->admin)->get('/admin/payments?tab=paid&source=gateway')->assertOk()
            ->assertSee('ORDER-1')->assertDontSee('TRX-0001');
        $this->asAdmin($this->admin)->get('/admin/payments?tab=paid&source=manual')->assertOk()
            ->assertSee('TRX-0001')->assertDontSee('ORDER-1');
        $this->asAdmin($this->admin)->get('/admin/revenue')->assertOk()
            ->assertSee('30.00 USD')->assertSee(__('admin.revenue.manual'))->assertSee(__('admin.revenue.gateway'));
    }

    public function test_the_revenue_page_shows_waiting_payments_apart(): void
    {
        $this->record();

        $this->asAdmin($this->admin)->get('/admin/revenue')->assertOk()
            ->assertSee(__('admin.revenue.not_counted'))
            ->assertSee('15.00 USD');
        $this->assertSame([], app(PlatformStatistics::class)->revenue(12)['totals']);
        $this->asAdmin($this->admin)->get('/admin/revenue?months=7')->assertSessionHasErrors('months');
    }

    public function test_no_payment_for_an_archived_business(): void
    {
        $invoice = $this->record();
        $this->business->delete();

        $this->asAdmin($this->admin)->post('/admin/payments/'.$invoice->public_id.'/approve')
            ->assertSessionHas('error', __('admin.errors.business_archived'));
        $this->assertSame(InvoiceStatus::Open, $invoice->fresh()->status);
        $this->asAdmin($this->admin)->post('/admin/payments', [
            'business' => $this->business->public_id, 'plan' => 'pro', 'billing_period' => 'monthly', 'currency' => 'USD',
            'method' => 'cash', 'received_at' => '2026-11-11',
        ])->assertSessionHasErrors('business');
    }
}
