<?php

namespace Tests\Feature\Billing;

use App\Enums\BillingPeriod;
use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsSubscriptions;
use Tests\Concerns\BuildsTenants;
use Tests\TestCase;

/**
 * Platform administration of plans and subscriptions
 * (docs/SUBSCRIPTIONS.md, "Admin API"): admins only, every change recorded,
 * plan changes take effect at once without a deployment.
 */
class AdminBillingApiTest extends TestCase
{
    use BuildsSubscriptions, BuildsTenants, RefreshDatabase;

    private User $admin;

    private User $owner;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-11 10:00:00', 'UTC'));
        $this->admin = User::factory()->platformAdmin()->create();
        $this->owner = User::factory()->create();
        $this->business = $this->businessOwnedBy($this->owner);
    }

    private function uri(string $suffix = ''): string
    {
        return '/api/v1/admin/billing/'.$suffix;
    }

    private function businessUri(string $suffix = ''): string
    {
        return $this->uri('businesses/'.$this->business->public_id.($suffix === '' ? '' : '/'.$suffix));
    }

    // ----- Access ---------------------------------------------------------

    public function test_every_admin_route_requires_a_platform_admin(): void
    {
        $routes = array_filter(
            Route::getRoutes()->getRoutes(),
            fn (RouteDefinition $route) => str_starts_with($route->uri(), 'api/v1/admin/'),
        );
        $this->assertNotEmpty($routes);
        foreach ($routes as $route) {
            $this->assertContains('platform.admin', $route->gatherMiddleware(), "Route {$route->uri()} is not admin-only.");
        }
    }

    public function test_owners_and_other_users_are_refused_without_learning_anything(): void
    {
        $manager = $this->memberOf($this->business, BusinessRole::Manager);
        $missing = $this->uri('businesses/01JZZZZZZZZZZZZZZZZZZZZZZZ');
        // Signed out first: actingAsUser keeps the token for later requests.
        $this->assertApiError($this->getJson($this->uri('plans')), 401, 'unauthenticated');

        foreach ([$this->owner, $manager, User::factory()->create()] as $user) {
            $this->assertApiError($this->actingAsUser($user, 'GET', $this->uri('plans')), 403, 'forbidden');
            $this->assertApiError($this->actingAsUser($user, 'GET', $this->businessUri()), 403, 'forbidden');
            // Same answer for a business that does not exist.
            $this->assertApiError($this->actingAsUser($user, 'GET', $missing), 403, 'forbidden');
            $this->assertApiError($this->actingAsUser($user, 'POST', $this->businessUri('plan'), ['plan' => 'business']), 403, 'forbidden');
        }
        $this->assertSame('pro', $this->subscriptionOf($this->business)->plan->code);
    }

    // ----- Plans -----------------------------------------------------------------

    public function test_admins_see_every_plan_with_inactive_prices(): void
    {
        $this->plan('pro')->prices()->where('billing_period', 'yearly')->update(['is_active' => false]);

        $this->assertApiSuccess($this->actingAsUser($this->admin, 'GET', $this->uri('plans')))
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.1.code', 'pro')
            ->assertJsonPath('data.1.is_active', true)
            ->assertJsonPath('data.1.prices.1.billing_period', 'yearly')
            ->assertJsonPath('data.1.prices.1.is_active', false);
        // Businesses no longer see that price.
        $this->getJson('/api/v1/billing/plans')->assertJsonCount(1, 'data.plans.1.prices');
    }

    public function test_an_admin_creates_a_plan_with_prices(): void
    {
        // Prices only in currencies DORY accepts (setting "Currencies", Phase 09).
        config(['billing.currencies' => ['USD', 'SYP']]);
        $response = $this->actingAsUser($this->admin, 'POST', $this->uri('plans'), [
            'code' => 'clinic-plus',
            'is_free' => false,
            'name_en' => 'Clinic Plus',
            'name_ar' => 'العيادة بلس',
            'max_branches' => 5,
            'max_queues' => 30,
            'max_members' => 25,
            'features' => ['insights'],
            'sort_order' => 25,
            'prices' => [
                ['billing_period' => 'monthly', 'currency' => 'usd', 'amount_minor' => 2900],
                ['billing_period' => 'monthly', 'currency' => 'SYP', 'amount_minor' => 40000000],
            ],
        ]);

        $this->assertApiSuccess($response, 201)
            ->assertJsonPath('data.code', 'clinic-plus')
            ->assertJsonPath('data.limits', ['branches' => 5, 'queues' => 30, 'members' => 25])
            ->assertJsonCount(2, 'data.prices');
        $this->assertSame(2900, SubscriptionPlan::query()->where('code', 'clinic-plus')->firstOrFail()->priceFor(BillingPeriod::Monthly, 'USD')->amount_minor);
    }

    public function test_plan_input_is_validated(): void
    {
        $base = ['code' => 'x1', 'is_free' => true, 'name_en' => 'X', 'name_ar' => 'س', 'max_branches' => 1, 'max_queues' => 1, 'max_members' => 1, 'features' => []];

        // Limits must be given, so a forgotten one never means unlimited.
        $this->assertApiError($this->actingAsUser($this->admin, 'POST', $this->uri('plans'), array_diff_key($base, ['max_queues' => 1])), 422, 'validation_failed')
            ->assertJsonValidationErrors('max_queues');
        $this->assertApiError($this->actingAsUser($this->admin, 'POST', $this->uri('plans'), ['code' => 'pro'] + $base), 422, 'validation_failed')
            ->assertJsonValidationErrors('code');
        $this->assertApiError($this->actingAsUser($this->admin, 'POST', $this->uri('plans'), ['features' => ['teleport']] + $base), 422, 'validation_failed')
            ->assertJsonValidationErrors('features.0');
        $this->assertApiError(
            $this->actingAsUser($this->admin, 'POST', $this->uri('plans'), $base + ['prices' => [['billing_period' => 'monthly', 'currency' => 'USD', 'amount_minor' => 100]]]),
            422,
            'validation_failed',
        )->assertJsonValidationErrors('prices');
        // Codes and free/paid never change; the default plan stays active.
        $this->assertApiError($this->actingAsUser($this->admin, 'PUT', $this->uri('plans/pro'), ['code' => 'gold']), 422, 'validation_failed');
        $this->assertApiError($this->actingAsUser($this->admin, 'PUT', $this->uri('plans/pro'), ['is_free' => true]), 422, 'validation_failed');
        $this->assertApiError($this->actingAsUser($this->admin, 'PUT', $this->uri('plans/free'), ['is_active' => false]), 422, 'validation_failed')
            ->assertJsonValidationErrors('is_active');
        $this->actingAsUser($this->admin, 'PUT', $this->uri('plans/unknown'), ['name_en' => 'X'])->assertNotFound();
    }

    public function test_changing_a_plan_applies_to_its_businesses_at_once(): void
    {
        $this->actingAsUser($this->admin, 'PUT', $this->uri('plans/pro'), [
            'max_branches' => 1,
            'prices' => [['billing_period' => 'monthly', 'currency' => 'USD', 'amount_minor' => 1900]],
        ])->assertOk()->assertJsonPath('data.limits.branches', 1)->assertJsonPath('data.prices.0.amount_minor', 1900);

        $this->branchOf($this->business);
        $this->assertApiError(
            $this->actingAsUser($this->owner, 'POST', '/api/v1/businesses/'.$this->business->public_id.'/branches', [
                'name' => 'Malki', 'address' => 'Street 1', 'country' => 'SY', 'city' => 'Damascus', 'latitude' => 33.5, 'longitude' => 36.3,
            ]),
            403,
            'plan_limit_reached',
        )->assertJsonPath('details.max', 1);
    }

    // ----- Subscriptions ------------------------------------------------------------

    public function test_an_admin_records_a_bank_transfer(): void
    {
        $this->travel(15)->days();

        $response = $this->actingAsUser($this->admin, 'POST', $this->businessUri('payments'), [
            'plan' => 'business',
            'billing_period' => 'yearly',
            'method' => 'bank_transfer',
            'reference' => 'TRX-2026-0042',
            'notes' => 'Received at the bank on 26 November',
        ]);

        // Recorded only (Phase 09): nothing changes until an admin approves it.
        $this->assertApiSuccess($response, 201)
            ->assertJsonPath('data.invoice.status', 'open')
            ->assertJsonPath('data.invoice.number', null)
            ->assertJsonPath('data.invoice.amount', '490.00')
            ->assertJsonPath('data.invoice.payment_method', 'bank_transfer')
            ->assertJsonPath('data.subscription.subscription.plan.code', 'free');

        $approved = $this->actingAsUser($this->admin, 'POST', $this->uri('payments/'.$response->json('data.invoice.id').'/approve'));
        $this->assertApiSuccess($approved)
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.amount', '490.00');
        $this->assertMatchesRegularExpression('/^DORY-2026-\d{6}$/', (string) $approved->json('data.number'));
        $this->actingAsUser($this->admin, 'GET', $this->businessUri())->assertOk()
            ->assertJsonPath('data.subscription.plan.code', 'business')
            ->assertJsonPath('data.subscription.billing_period', 'yearly')
            ->assertJsonPath('data.events.0.type', 'activated')
            ->assertJsonPath('data.events.0.actor.id', $this->admin->public_id);

        // The same transfer cannot be recorded twice.
        $this->assertApiError($this->actingAsUser($this->admin, 'POST', $this->businessUri('payments'), [
            'plan' => 'business', 'billing_period' => 'yearly', 'method' => 'bank_transfer', 'reference' => 'TRX-2026-0042',
        ]), 422, 'validation_failed')->assertJsonValidationErrors('reference');
        $this->assertApiError($this->actingAsUser($this->admin, 'POST', $this->businessUri('payments'), [
            'plan' => 'free', 'billing_period' => 'monthly', 'method' => 'cash',
        ]), 422, 'plan_not_available');
    }

    public function test_an_admin_extends_a_trial(): void
    {
        $end = $this->subscriptionOf($this->business)->trial_ends_at->copy();

        $this->actingAsUser($this->admin, 'POST', $this->businessUri('trial'), ['days' => 7])->assertOk()
            ->assertJsonPath('data.subscription.status', 'trialing')
            ->assertJsonPath('data.subscription.trial_ends_at', $end->addDays(7)->toIso8601String());
        $this->assertApiError($this->actingAsUser($this->admin, 'POST', $this->businessUri('trial'), ['days' => 365]), 422, 'validation_failed');
    }

    public function test_an_assigned_plan_ends_on_its_date_without_grace(): void
    {
        $until = now()->addDays(30);
        $this->actingAsUser($this->admin, 'POST', $this->businessUri('plan'), [
            'plan' => 'business',
            'until' => $until->toIso8601String(),
            'note' => 'Partner pilot',
        ])->assertOk()->assertJsonPath('data.subscription.plan.code', 'business');

        $this->travelTo($until->copy()->addMinute());
        $this->actingAsUser($this->owner, 'GET', '/api/v1/businesses/'.$this->business->public_id.'/subscription')
            ->assertJsonPath('data.status', 'expired')
            ->assertJsonPath('data.plan.code', 'free');
    }

    public function test_a_plan_assigned_without_an_end_is_managed_by_admins_only(): void
    {
        $this->actingAsUser($this->admin, 'POST', $this->businessUri('plan'), ['plan' => 'business'])->assertOk();
        $this->travel(2)->years();

        $this->actingAsUser($this->owner, 'GET', '/api/v1/businesses/'.$this->business->public_id.'/subscription')
            ->assertJsonPath('data.plan.code', 'business')
            ->assertJsonPath('data.is_complimentary', true);
        $this->assertApiError(
            $this->actingAsUser($this->owner, 'POST', '/api/v1/businesses/'.$this->business->public_id.'/subscription/cancel'),
            409,
            'subscription_change_not_allowed',
        )->assertJsonPath('details.reason', 'managed_by_admin');

        $this->actingAsUser($this->admin, 'POST', $this->businessUri('expire'), ['reason' => 'pilot over'])->assertOk()
            ->assertJsonPath('data.subscription.plan.code', 'free');
        $this->assertApiError($this->actingAsUser($this->admin, 'POST', $this->businessUri('expire')), 409, 'subscription_change_not_allowed');
    }

    public function test_admins_list_subscriptions_by_status(): void
    {
        $other = $this->businessOwnedBy(User::factory()->create(), ['name' => 'Paid Clinic']);
        $this->travel(15)->days();
        $this->paidPlan($other, 'pro');

        $this->actingAsUser($this->admin, 'GET', $this->uri('subscriptions?status=active'))->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.business.name', 'Paid Clinic')
            ->assertJsonPath('data.0.plan', 'pro');
        // The trial ended without the sweep: stored "trialing", really expired.
        $this->actingAsUser($this->admin, 'GET', $this->uri('subscriptions?status=trialing'))->assertOk()
            ->assertJsonPath('data.0.status', 'expired')
            ->assertJsonPath('data.0.plan', 'free');
        $this->actingAsUser($this->admin, 'GET', $this->uri('subscriptions?search=paid'))->assertOk()->assertJsonCount(1, 'data');
    }
}
