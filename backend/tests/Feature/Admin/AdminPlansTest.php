<?php

namespace Tests\Feature\Admin;

use App\Enums\BillingPeriod;
use App\Enums\PlatformAuditAction;
use App\Models\PlatformAuditLog;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSubscriptions;
use Tests\Concerns\BuildsTenants;
use Tests\Concerns\UsesAdminDashboard;
use Tests\TestCase;

/**
 * Plans and prices from the admin dashboard (docs/ADMIN_DASHBOARD.md,
 * "Plans"). Nothing about the default plans changes unless an admin saves.
 */
class AdminPlansTest extends TestCase
{
    use BuildsSubscriptions, BuildsTenants, RefreshDatabase, UsesAdminDashboard;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->platformAdmin();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return array_replace([
            'code' => 'clinic-plus',
            'is_free' => '0',
            'name_en' => 'Clinic Plus',
            'name_ar' => 'العيادة بلس',
            'description_en' => 'For clinics',
            'description_ar' => '',
            'max_branches' => '5',
            'max_queues' => '',
            'max_queues_unlimited' => '1',
            'max_members' => '25',
            'features' => ['insights'],
            'sort_order' => '25',
            'is_active' => '1',
            'prices' => [
                'monthly_USD' => ['amount' => '29.00'],
                'yearly_USD' => ['amount' => '290'],
            ],
        ], $overrides);
    }

    public function test_the_plans_page_shows_the_default_plans_unchanged(): void
    {
        $this->asAdmin($this->admin)->get('/admin/plans')->assertOk()
            ->assertSee('Free')->assertSee('Pro')->assertSee('Business')
            ->assertSee('15.00 USD')->assertSee('490.00 USD');
        $this->assertSame(1500, $this->plan('pro')->priceFor(BillingPeriod::Monthly, 'USD')->amount_minor);
        $this->assertFalse(PlatformAuditLog::query()->exists());
    }

    public function test_the_plans_page_counts_the_businesses_on_each_plan_now(): void
    {
        // Found in the Phase 09 browser walk: every plan showed 0.
        $this->onPlan($this->businessOwnedBy(User::factory()->create(), ['name' => 'Given']), 'business');
        $this->businessOwnedBy(User::factory()->create(), ['name' => 'On trial']);

        $this->asAdmin($this->admin)->get('/admin/plans')->assertOk()
            ->assertSee('<td class="num" data-subscribers="business">1</td>', false)
            ->assertSee('<td class="num" data-subscribers="pro">1</td>', false)
            ->assertSee('<td class="num" data-subscribers="free">0</td>', false);
    }

    public function test_an_admin_creates_a_plan_with_decimal_prices_and_explicit_limits(): void
    {
        $this->asAdmin($this->admin)->post('/admin/plans', $this->form())
            ->assertRedirect('/admin/plans')
            ->assertSessionHas('status', __('admin.plans.created', ['plan' => 'Clinic Plus']));

        $plan = SubscriptionPlan::query()->with('prices')->where('code', 'clinic-plus')->sole();
        $this->assertFalse($plan->is_free);
        $this->assertSame([5, null, 25], [$plan->max_branches, $plan->max_queues, $plan->max_members]);
        $this->assertSame(['insights'], $plan->features);
        $this->assertNull($plan->description_ar);
        $this->assertSame(2900, $plan->priceFor(BillingPeriod::Monthly, 'USD')->amount_minor);
        $this->assertSame(29000, $plan->priceFor(BillingPeriod::Yearly, 'USD')->amount_minor);
        $entry = PlatformAuditLog::query()->where('action', PlatformAuditAction::PlanCreated->value)->sole();
        $this->assertSame('clinic-plus', $entry->properties['code']);
        // MySQL stores JSON objects with their keys reordered: compare by key.
        $limits = $entry->properties['limits'];
        ksort($limits);
        $this->assertSame(['branches' => 5, 'members' => 25, 'queues' => null], $limits);
    }

    public function test_plan_input_is_checked(): void
    {
        $post = fn (array $overrides) => $this->asAdmin($this->admin)->post('/admin/plans', $this->form($overrides));

        // An empty limit is never "unlimited" by accident.
        $post(['max_queues_unlimited' => null])->assertSessionHasErrors('max_queues');
        $post(['code' => 'Has Spaces'])->assertSessionHasErrors('code');
        $post(['code' => 'pro'])->assertSessionHasErrors('code');
        $post(['code' => 'unlimited'])->assertSessionHasErrors('code');
        $post(['features' => ['teleport']])->assertSessionHasErrors('features.0');
        $post(['prices' => ['monthly_USD' => ['amount' => '29.999']]])->assertSessionHasErrors('prices.monthly_USD.amount');
        $post(['prices' => ['monthly_USD' => ['amount' => '-5']]])->assertSessionHasErrors('prices.monthly_USD.amount');
        $post(['prices' => ['monthly_USD' => ['amount' => 'abc']]])->assertSessionHasErrors('prices.monthly_USD.amount');
        $post(['is_free' => '1'])->assertSessionHasErrors('prices');
        $this->assertFalse(SubscriptionPlan::query()->where('code', 'clinic-plus')->exists());
        $this->assertFalse(SubscriptionPlan::query()->where('code', 'has spaces')->exists());
    }

    public function test_prices_only_in_accepted_currencies(): void
    {
        config(['billing.currencies' => ['USD', 'IQD']]);

        // Three decimals for IQD; prices in other currencies are ignored.
        $this->asAdmin($this->admin)->post('/admin/plans', $this->form([
            'prices' => [
                'monthly_IQD' => ['amount' => '25000.500'],
                'monthly_EUR' => ['amount' => '10'],
            ],
        ]))->assertRedirect('/admin/plans');

        $plan = SubscriptionPlan::query()->with('prices')->where('code', 'clinic-plus')->sole();
        $this->assertSame(25000500, $plan->priceFor(BillingPeriod::Monthly, 'IQD')->amount_minor);
        $this->assertNull($plan->priceFor(BillingPeriod::Monthly, 'EUR'));
        $this->assertCount(1, $plan->prices);
    }

    public function test_editing_changes_limits_and_prices_for_every_business_at_once(): void
    {
        $this->asAdmin($this->admin)->get('/admin/plans/pro/edit')->assertOk()
            ->assertSee('value="15.00"', false)->assertSee('value="150.00"', false);

        $this->asAdmin($this->admin)->put('/admin/plans/pro', [
            'name_en' => 'Pro',
            'name_ar' => 'احترافي',
            'max_branches' => '4',
            'max_queues' => '20',
            'max_members' => '12',
            'features' => ['insights'],
            'sort_order' => '2',
            'is_active' => '1',
            'prices' => [
                'monthly_USD' => ['amount' => '19.00', 'existing' => '1', 'active' => '1'],
                // Still kept, no longer sold.
                'yearly_USD' => ['amount' => '150.00', 'existing' => '1'],
            ],
        ])->assertRedirect('/admin/plans');

        $pro = $this->plan('pro');
        $this->assertSame(4, $pro->max_branches);
        $this->assertSame(1900, $pro->priceFor(BillingPeriod::Monthly, 'USD')->amount_minor);
        $this->assertNull($pro->priceFor(BillingPeriod::Yearly, 'USD'));
        $this->assertFalse($pro->prices->firstWhere('billing_period', BillingPeriod::Yearly)->is_active);
        $changes = PlatformAuditLog::query()->where('action', PlatformAuditAction::PlanUpdated->value)->sole()->properties['changes'];
        $this->assertSame(3, $changes['max_branches']['from']);
        $this->assertSame(4, $changes['max_branches']['to']);
        $this->assertArrayHasKey('prices', $changes);
        $this->assertSame('pro', SubscriptionPlan::query()->whereKey($pro->getKey())->value('code'));
    }

    public function test_a_price_cannot_be_removed_and_the_default_plan_stays_offered(): void
    {
        $base = [
            'name_en' => 'Pro', 'name_ar' => 'احترافي', 'max_branches' => '3', 'max_queues' => '15', 'max_members' => '10',
            'features' => ['insights'], 'sort_order' => '2', 'is_active' => '1',
        ];
        $this->asAdmin($this->admin)->put('/admin/plans/pro', $base + ['prices' => ['monthly_USD' => ['amount' => '', 'existing' => '1']]])
            ->assertSessionHasErrors('prices.monthly_USD.amount');
        $this->assertSame(1500, $this->plan('pro')->priceFor(BillingPeriod::Monthly, 'USD')->amount_minor);

        $this->asAdmin($this->admin)->put('/admin/plans/free', [
            'name_en' => 'Free', 'name_ar' => 'مجاني', 'max_branches' => '1', 'max_queues' => '3', 'max_members' => '3',
            'sort_order' => '1',
        ])->assertSessionHasErrors('is_active');
        $this->assertTrue($this->plan('free')->is_active);
    }

    public function test_the_code_and_kind_of_a_plan_never_change(): void
    {
        $pro = $this->plan('pro');
        $this->asAdmin($this->admin)->put('/admin/plans/pro', [
            'code' => 'gold', 'is_free' => '1',
            // The form always sends every field.
            'description_en' => $pro->description_en, 'description_ar' => $pro->description_ar,
            'name_en' => 'Pro', 'name_ar' => 'احترافي', 'max_branches' => '3', 'max_queues' => '15', 'max_members' => '10',
            'features' => ['insights'], 'sort_order' => '2', 'is_active' => '1',
            'prices' => [
                'monthly_USD' => ['amount' => '15.00', 'existing' => '1', 'active' => '1'],
                'yearly_USD' => ['amount' => '150.00', 'existing' => '1', 'active' => '1'],
            ],
        ])->assertRedirect('/admin/plans');

        $this->assertTrue(SubscriptionPlan::query()->where('code', 'pro')->where('is_free', false)->exists());
        $this->assertFalse(SubscriptionPlan::query()->where('code', 'gold')->exists());
        // Nothing changed, so nothing is logged.
        $this->assertFalse(PlatformAuditLog::query()->exists());
    }
}
