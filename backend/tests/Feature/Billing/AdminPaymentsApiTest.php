<?php

namespace Tests\Feature\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\PlatformAuditAction;
use App\Models\BillingInvoice;
use App\Models\Business;
use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsSubscriptions;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * The admin billing API's payment approval and subscription actions added
 * in Phase 09 (docs/SUBSCRIPTIONS.md, "Admin API").
 */
class AdminPaymentsApiTest extends TestCase
{
    use BuildsSubscriptions, BuildsTenants, RefreshDatabase;

    private User $admin;

    private User $owner;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-12 09:00:00', 'UTC'));
        $this->admin = User::factory()->platformAdmin()->create();
        $this->owner = User::factory()->create();
        $this->business = $this->businessOwnedBy($this->owner);
    }

    private function recordPayment(array $overrides = [], ?User $admin = null): string
    {
        $response = $this->actingAsUser($admin ?? $this->admin, 'POST', '/api/v1/admin/billing/businesses/'.$this->business->public_id.'/payments', $overrides + [
            'plan' => 'pro',
            'billing_period' => 'monthly',
            'currency' => 'usd',
            'method' => 'cash',
            'reference' => 'RCPT-'.uniqid(),
        ]);
        $this->assertApiSuccess($response, 201)->assertJsonPath('data.invoice.status', 'open');

        return (string) $response->json('data.invoice.id');
    }

    public function test_admins_list_payments_awaiting_approval(): void
    {
        $waiting = $this->recordPayment();
        $approved = $this->recordPayment();
        $this->actingAsUser($this->admin, 'POST', '/api/v1/admin/billing/payments/'.$approved.'/approve')->assertOk();

        $this->actingAsUser($this->admin, 'GET', '/api/v1/admin/billing/payments?status=awaiting_approval')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $waiting);
        $this->actingAsUser($this->admin, 'GET', '/api/v1/admin/billing/payments?status=paid')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $approved);
        $this->assertApiError($this->actingAsUser($this->admin, 'GET', '/api/v1/admin/billing/payments?status=bogus'), 422, 'validation_failed');
    }

    public function test_approval_rules_hold_through_the_api(): void
    {
        config(['billing.manual_payments.four_eyes' => true]);
        $payment = $this->recordPayment();

        $this->assertApiError($this->actingAsUser($this->admin, 'POST', '/api/v1/admin/billing/payments/'.$payment.'/approve'), 403, 'forbidden')
            ->assertJsonPath('details.reason', 'needs_another_admin');
        $other = User::factory()->platformAdmin()->create();
        $this->assertApiSuccess($this->actingAsUser($other, 'POST', '/api/v1/admin/billing/payments/'.$payment.'/approve'))
            ->assertJsonPath('data.status', 'paid');
        $this->assertApiError($this->actingAsUser($other, 'POST', '/api/v1/admin/billing/payments/'.$payment.'/approve'), 409, 'admin_action_not_allowed')
            ->assertJsonPath('details.reason', 'not_awaiting_approval');
        $this->assertSame('pro', $this->subscriptionOf($this->business)->plan->code);
        $this->assertSame(1, PlatformAuditLog::query()->where('action', PlatformAuditAction::PaymentApproved->value)->count());
    }

    public function test_rejection_needs_a_reason_and_changes_nothing(): void
    {
        $payment = $this->recordPayment();

        $this->assertApiError($this->actingAsUser($this->admin, 'POST', '/api/v1/admin/billing/payments/'.$payment.'/reject'), 422, 'validation_failed');
        $this->assertApiSuccess($this->actingAsUser($this->admin, 'POST', '/api/v1/admin/billing/payments/'.$payment.'/reject', ['reason' => 'Bounced']))
            ->assertJsonPath('data.status', 'void')
            ->assertJsonPath('data.failure_reason', 'Bounced');
        $this->assertSame(InvoiceStatus::Void, BillingInvoice::query()->where('public_id', $payment)->value('status'));
        $this->assertSame('trialing', $this->subscriptionOf($this->business)->status->value);
    }

    public function test_nobody_else_sees_or_approves_payments(): void
    {
        $payment = $this->recordPayment();

        foreach ([$this->owner, User::factory()->create()] as $user) {
            $this->assertApiError($this->actingAsUser($user, 'GET', '/api/v1/admin/billing/payments'), 403, 'forbidden');
            $this->assertApiError($this->actingAsUser($user, 'POST', '/api/v1/admin/billing/payments/'.$payment.'/approve'), 403, 'forbidden');
            $this->assertApiError($this->actingAsUser($user, 'POST', '/api/v1/admin/billing/businesses/'.$this->business->public_id.'/cancel'), 403, 'forbidden');
        }
        $this->assertSame(InvoiceStatus::Open, BillingInvoice::query()->where('public_id', $payment)->value('status'));
    }

    public function test_admins_cancel_and_resume_on_the_owners_behalf(): void
    {
        $this->travel(15)->days();
        $this->paidPlan($this->business, 'pro');
        $uri = '/api/v1/admin/billing/businesses/'.$this->business->public_id;

        $this->assertApiSuccess($this->actingAsUser($this->admin, 'POST', $uri.'/cancel'))
            ->assertJsonPath('data.subscription.cancel_at_period_end', true);
        $this->assertApiError($this->actingAsUser($this->admin, 'POST', $uri.'/cancel'), 409, 'subscription_change_not_allowed')
            ->assertJsonPath('details.reason', 'already_canceled');
        $this->assertApiSuccess($this->actingAsUser($this->admin, 'POST', $uri.'/resume'))
            ->assertJsonPath('data.subscription.cancel_at_period_end', false);
        $this->assertSame(1, PlatformAuditLog::query()->where('action', PlatformAuditAction::SubscriptionCanceled->value)->count());
        $this->assertSame(1, PlatformAuditLog::query()->where('action', PlatformAuditAction::SubscriptionResumed->value)->count());
    }

    public function test_recorded_payments_are_in_the_audit_log_with_the_amount(): void
    {
        $this->recordPayment(['amount_minor' => 1200, 'reference' => 'RCPT-77']);

        $entry = PlatformAuditLog::query()->where('action', PlatformAuditAction::PaymentRecorded->value)->sole();
        $this->assertSame('12.00 USD', $entry->properties['amount']);
        $this->assertSame('RCPT-77', $entry->properties['reference']);
        $this->assertSame($this->business->getKey(), $entry->business_id);
    }
}
