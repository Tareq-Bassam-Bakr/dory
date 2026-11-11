<?php

namespace Tests\Feature\Billing;

use App\Enums\BusinessRole;
use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\PaymentCheckout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsSubscriptions;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * The business side of billing through the API (docs/SUBSCRIPTIONS.md):
 * the subscription belongs to the business; only its owner manages billing,
 * managers see the plan and usage, nobody else sees anything.
 */
class SubscriptionApiTest extends TestCase
{
    use BuildsSubscriptions, BuildsTenants, RefreshDatabase;

    private User $owner;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-11 10:00:00', 'UTC'));
        $this->owner = User::factory()->create();
        $this->business = $this->businessOwnedBy($this->owner);
    }

    private function uri(string $suffix = '', ?Business $business = null): string
    {
        return '/api/v1/businesses/'.($business ?? $this->business)->public_id.'/'.$suffix;
    }

    // ----- Plans ------------------------------------------------------------

    public function test_anyone_can_see_the_plans_and_prices(): void
    {
        $response = $this->getJson('/api/v1/billing/plans', ['Accept-Language' => 'ar']);

        $this->assertApiSuccess($response)
            ->assertJsonPath('data.plans.0.code', 'free')
            ->assertJsonPath('data.plans.0.name', 'مجاني')
            ->assertJsonPath('data.plans.0.limits', ['branches' => 1, 'queues' => 3, 'members' => 3])
            ->assertJsonPath('data.plans.0.prices', [])
            ->assertJsonPath('data.plans.1.code', 'pro')
            ->assertJsonPath('data.plans.1.features', ['insights'])
            ->assertJsonPath('data.plans.1.prices.0', ['billing_period' => 'monthly', 'currency' => 'USD', 'amount_minor' => 1500, 'amount' => '15.00'])
            ->assertJsonPath('data.plans.2.code', 'business')
            ->assertJsonPath('data.plans.2.limits', ['branches' => null, 'queues' => null, 'members' => null])
            ->assertJsonPath('data.trial', ['plan' => 'pro', 'days' => 14])
            ->assertJsonPath('data.payments', ['online' => false, 'provider' => 'none']);
    }

    // ----- Reading ----------------------------------------------------------

    public function test_the_owner_sees_the_subscription_with_usage_and_billing_rights(): void
    {
        $this->branchOf($this->business);

        $this->assertApiSuccess($this->actingAsUser($this->owner, 'GET', $this->uri('subscription')))
            ->assertJsonPath('data.status', 'trialing')
            ->assertJsonPath('data.plan.code', 'pro')
            ->assertJsonPath('data.trial_ends_at', now()->addDays(14)->toIso8601String())
            ->assertJsonPath('data.limits', ['branches' => 3, 'queues' => 15, 'members' => 10])
            ->assertJsonPath('data.usage', ['branches' => 1, 'queues' => 0, 'members' => 1])
            ->assertJsonPath('data.features', ['insights'])
            ->assertJsonPath('data.permissions', ['manage_billing' => true, 'view_billing' => true]);
    }

    public function test_managers_see_the_plan_but_not_billing(): void
    {
        $manager = $this->memberOf($this->business, BusinessRole::Manager);

        $this->actingAsUser($manager, 'GET', $this->uri('subscription'))->assertOk()
            ->assertJsonPath('data.plan.code', 'pro')
            ->assertJsonPath('data.permissions', ['manage_billing' => false, 'view_billing' => false]);
        $this->assertApiError($this->actingAsUser($manager, 'GET', $this->uri('invoices')), 403, 'forbidden');
        foreach (['cancel', 'resume'] as $action) {
            $this->assertApiError($this->actingAsUser($manager, 'POST', $this->uri('subscription/'.$action)), 403, 'forbidden');
        }
        $this->assertApiError($this->actingAsUser($manager, 'POST', $this->uri('subscription/downgrade'), ['plan' => 'free']), 403, 'forbidden');
        $this->assertApiError(
            $this->actingAsUser($manager, 'POST', $this->uri('subscription/checkout'), ['plan' => 'pro', 'billing_period' => 'monthly']),
            403,
            'forbidden',
        );
    }

    public function test_employees_others_and_guests_see_nothing(): void
    {
        $employee = $this->memberOf($this->business, BusinessRole::Employee);
        $stranger = User::factory()->create();
        $this->businessOwnedBy($stranger);

        $this->assertApiError($this->getJson($this->uri('subscription')), 401, 'unauthenticated');
        $this->assertApiError($this->actingAsUser($employee, 'GET', $this->uri('subscription')), 403, 'forbidden');
        $this->assertApiError($this->actingAsUser($employee, 'GET', $this->uri('invoices')), 403, 'forbidden');
        foreach (['subscription', 'invoices'] as $what) {
            $this->assertApiError($this->actingAsUser($stranger, 'GET', $this->uri($what)), 404, 'business_not_found');
        }
        $this->assertApiError($this->actingAsUser($stranger, 'POST', $this->uri('subscription/cancel')), 404, 'business_not_found');
    }

    public function test_a_platform_admin_gets_no_billing_rights_in_a_business(): void
    {
        // Admins use the admin API; the business endpoints are for members.
        $admin = User::factory()->platformAdmin()->create();

        $this->assertApiError($this->actingAsUser($admin, 'POST', $this->uri('subscription/cancel')), 404, 'business_not_found');
        $this->assertApiError($this->actingAsUser($admin, 'GET', $this->uri('subscription')), 404, 'business_not_found');
    }

    public function test_the_business_permissions_tell_the_app_about_billing(): void
    {
        $manager = $this->memberOf($this->business, BusinessRole::Manager);

        $this->actingAsUser($this->owner, 'GET', '/api/v1/businesses/'.$this->business->public_id)
            ->assertJsonPath('data.permissions.view_subscription', true)
            ->assertJsonPath('data.permissions.manage_billing', true);
        $this->actingAsUser($manager, 'GET', '/api/v1/businesses/'.$this->business->public_id)
            ->assertJsonPath('data.permissions.view_subscription', true)
            ->assertJsonPath('data.permissions.manage_billing', false);
    }

    public function test_billing_history_lists_only_this_business_newest_first(): void
    {
        $this->travel(15)->days();
        $first = $this->paidPlan($this->business, 'pro');
        $this->travel(1)->days();
        $second = $this->paidPlan($this->business, 'pro');
        $other = $this->businessOwnedBy(User::factory()->create());
        $this->paidPlan($other, 'business');

        $this->assertApiSuccess($this->actingAsUser($this->owner, 'GET', $this->uri('invoices')))
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $second->public_id)
            ->assertJsonPath('data.1.id', $first->public_id)
            ->assertJsonPath('data.0.status', 'paid')
            ->assertJsonPath('data.0.amount', '15.00')
            ->assertJsonPath('data.0.plan.code', 'pro')
            ->assertJsonPath('meta.pagination.total', 2)
            ->assertJsonMissingPath('data.0.provider_reference')
            ->assertJsonMissingPath('data.0.notes');
    }

    // ----- Changing --------------------------------------------------------------

    public function test_the_owner_cancels_and_resumes_a_paid_plan(): void
    {
        $this->travel(15)->days();
        $this->paidPlan($this->business, 'pro');

        $this->assertApiSuccess($this->actingAsUser($this->owner, 'POST', $this->uri('subscription/cancel')))
            ->assertJsonPath('data.cancel_at_period_end', true)
            ->assertJsonPath('data.plan.code', 'pro');
        $this->assertApiError($this->actingAsUser($this->owner, 'POST', $this->uri('subscription/cancel')), 409, 'subscription_change_not_allowed')
            ->assertJsonPath('details.reason', 'already_canceled');
        $this->assertApiSuccess($this->actingAsUser($this->owner, 'POST', $this->uri('subscription/resume')))
            ->assertJsonPath('data.cancel_at_period_end', false);
        $this->assertApiError($this->actingAsUser($this->owner, 'POST', $this->uri('subscription/resume')), 409, 'subscription_change_not_allowed')
            ->assertJsonPath('details.reason', 'nothing_to_resume');
    }

    public function test_the_owner_schedules_a_downgrade(): void
    {
        $this->travel(15)->days();
        $this->paidPlan($this->business, 'business');

        $this->assertApiSuccess($this->actingAsUser($this->owner, 'POST', $this->uri('subscription/downgrade'), ['plan' => 'pro']))
            ->assertJsonPath('data.plan.code', 'business')
            ->assertJsonPath('data.scheduled_plan.code', 'pro')
            ->assertJsonPath('data.scheduled_billing_period', 'monthly');
        // Bigger plans are bought, not "downgraded" to.
        $this->assertApiError($this->actingAsUser($this->owner, 'POST', $this->uri('subscription/downgrade'), ['plan' => 'business']), 409, 'subscription_change_not_allowed')
            ->assertJsonPath('details.reason', 'use_checkout');
        $this->assertApiError($this->actingAsUser($this->owner, 'POST', $this->uri('subscription/downgrade'), ['plan' => 'gold']), 422, 'plan_not_available');
    }

    public function test_nothing_to_cancel_on_the_free_plan(): void
    {
        $this->travel(15)->days();

        $this->assertApiError($this->actingAsUser($this->owner, 'POST', $this->uri('subscription/cancel')), 409, 'subscription_change_not_allowed')
            ->assertJsonPath('details.reason', 'nothing_to_cancel');
    }

    public function test_online_checkout_is_refused_while_no_payment_provider_is_set_up(): void
    {
        $this->assertApiError(
            $this->actingAsUser($this->owner, 'POST', $this->uri('subscription/checkout'), ['plan' => 'pro', 'billing_period' => 'monthly']),
            503,
            'payments_unavailable',
        );
        $this->assertSame(0, PaymentCheckout::query()->count());
        $this->assertApiError(
            $this->actingAsUser($this->owner, 'POST', $this->uri('subscription/checkout'), ['plan' => 'pro', 'billing_period' => 'weekly']),
            422,
            'validation_failed',
        );
    }

    public function test_a_suspended_business_shows_its_billing_but_cannot_change_it(): void
    {
        $this->business->forceFill(['status' => BusinessStatus::Suspended])->save();

        $this->actingAsUser($this->owner, 'GET', $this->uri('subscription'))->assertOk()
            ->assertJsonPath('data.permissions.manage_billing', false);
        $this->actingAsUser($this->owner, 'GET', $this->uri('invoices'))->assertOk();
        $this->assertApiError($this->actingAsUser($this->owner, 'POST', $this->uri('subscription/cancel')), 403, 'business_suspended');
    }

    public function test_a_checkout_of_another_business_is_not_found(): void
    {
        $other = $this->businessOwnedBy(User::factory()->create());
        $checkout = new PaymentCheckout;
        $checkout->forceFill([
            'business_id' => $other->getKey(),
            'plan_id' => $this->plan('pro')->getKey(),
            'billing_period' => 'monthly',
            'currency' => 'USD',
            'amount_minor' => 1500,
            'provider' => 'mastercard',
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ])->save();

        $this->assertApiError(
            $this->actingAsUser($this->owner, 'GET', $this->uri('subscription/checkouts/'.$checkout->public_id)),
            404,
            'checkout_not_found',
        );
    }
}
