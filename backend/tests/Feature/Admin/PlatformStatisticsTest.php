<?php

namespace Tests\Feature\Admin;

use App\Enums\BusinessStatus;
use App\Models\User;
use App\Services\Admin\PlatformStatistics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsSubscriptions;
use Tests\Concerns\BuildsTenants;
use Tests\Concerns\UsesAdminDashboard;
use Tests\TestCase;

/**
 * The overview numbers (docs/ADMIN_DASHBOARD.md, "Overview"): computed now,
 * the same way DORY decides what a business may do.
 */
class PlatformStatisticsTest extends TestCase
{
    use BuildsSubscriptions, BuildsTenants, RefreshDatabase, UsesAdminDashboard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-12 09:00:00', 'UTC'));
    }

    public function test_accounts_businesses_and_branches_are_counted(): void
    {
        $admin = $this->platformAdmin();
        User::factory()->suspended()->create();
        $active = $this->businessOwnedBy(User::factory()->create(), ['name' => 'Active Clinic']);
        $suspended = $this->businessOwnedBy(User::factory()->create(), ['name' => 'Suspended Clinic']);
        $suspended->forceFill(['status' => BusinessStatus::Suspended])->save();
        $archived = $this->businessOwnedBy(User::factory()->create(), ['name' => 'Archived Clinic']);
        $archived->delete();
        $this->branchOf($active);
        $this->branchOf($archived);

        $counts = app(PlatformStatistics::class)->counts();

        // The admin, three owners and the suspended account.
        $this->assertSame(5, $counts['users']['total']);
        $this->assertSame(1, $counts['users']['suspended']);
        $this->assertSame(1, $counts['users']['admins']);
        // Archived businesses are not counted, nor their branches.
        $this->assertSame(2, $counts['businesses']['total']);
        $this->assertSame(1, $counts['businesses']['suspended']);
        $this->assertSame(2, $counts['businesses']['awaiting_review']);
        $this->assertSame(1, $counts['branches']);
        $this->assertSame(0, $counts['awaiting_approval']);

        $this->asAdmin($admin)->get('/admin')->assertOk()
            ->assertSee(__('admin.overview.businesses_hint', ['active' => 1, 'suspended' => 1]));
    }

    public function test_subscription_states_are_computed_now(): void
    {
        $this->businessOwnedBy(User::factory()->create(), ['name' => 'Trial Clinic']);
        $paid = $this->businessOwnedBy(User::factory()->create(), ['name' => 'Paid Clinic']);
        $given = $this->businessOwnedBy(User::factory()->create(), ['name' => 'Given Clinic']);
        $this->onPlan($given, 'business');
        $this->travel(15)->days();
        $this->paidPlan($paid, 'pro');

        $states = app(PlatformStatistics::class)->subscriptions();

        // The trial ended 1 day ago; the scheduler has not stored it.
        $this->assertSame(1, $states['states']['expired']);
        $this->assertSame(1, $states['states']['paid']);
        $this->assertSame(1, $states['states']['complimentary']);
        $this->assertSame(0, $states['states']['trialing']);
        $this->assertSame(['business' => 1, 'free' => 1, 'pro' => 1], collect($states['plans'])->sortKeys()->all());
    }

    public function test_trials_ending_soon_are_listed(): void
    {
        $this->businessOwnedBy(User::factory()->create(), ['name' => 'Soon Clinic']);
        $this->travel(12)->days();

        $states = app(PlatformStatistics::class)->subscriptions();

        $this->assertCount(1, $states['trials_ending']);
        $this->assertSame('Soon Clinic', $states['trials_ending'][0]['business']->name);
    }

    public function test_the_growth_chart_shows_counts_not_the_revenue_amounts(): void
    {
        // Found in the Phase 09 browser walk: the revenue chart's labels
        // leaked into the growth chart below it.
        $business = $this->businessOwnedBy(User::factory()->create(), ['name' => 'Paying Clinic']);
        $this->businessOwnedBy(User::factory()->create(), ['name' => 'Second Clinic']);
        $this->travel(15)->days();
        $this->paidPlan($business, 'pro');

        $html = (string) $this->asAdmin()->get('/admin')->assertOk()->getContent();

        $growth = substr($html, (int) strpos($html, '<caption>'.__('admin.overview.growth_title').'</caption>'));
        $growth = substr($growth, 0, (int) strpos($growth, '</table>'));
        $this->assertNotSame('', $growth);
        $this->assertStringContainsString('<td>2</td>', $growth);
        $this->assertStringNotContainsString('15.00', $growth);
    }

    public function test_new_businesses_are_counted_per_month(): void
    {
        $this->businessOwnedBy(User::factory()->create(), ['name' => 'November']);
        $this->travelTo(Carbon::parse('2026-12-03 09:00:00', 'UTC'));
        $this->businessOwnedBy(User::factory()->create(), ['name' => 'December 1']);
        $this->businessOwnedBy(User::factory()->create(), ['name' => 'December 2']);

        $growth = app(PlatformStatistics::class)->newBusinessesByMonth(3);

        $this->assertSame(['2026-10' => 0, '2026-11' => 1, '2026-12' => 2], $growth);
    }
}
