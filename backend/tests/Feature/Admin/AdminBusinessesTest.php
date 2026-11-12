<?php

namespace Tests\Feature\Admin;

use App\Enums\BusinessStatus;
use App\Enums\PlatformAuditAction;
use App\Models\Business;
use App\Models\BusinessAuditLog;
use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsDiscovery;
use Tests\Concerns\BuildsQueues;
use Tests\Concerns\BuildsSubscriptions;
use Tests\Concerns\BuildsTenants;
use Tests\Concerns\UsesAdminDashboard;
use Tests\TestCase;

/**
 * Business review from the admin dashboard (docs/ADMIN_DASHBOARD.md,
 * "Businesses").
 */
class AdminBusinessesTest extends TestCase
{
    use BuildsDiscovery, BuildsQueues, BuildsSubscriptions, BuildsTenants, RefreshDatabase, UsesAdminDashboard;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-12 09:00:00', 'UTC'));
        $this->admin = $this->platformAdmin(['name' => 'Rana Admin']);
    }

    public function test_admins_find_businesses_by_name_slug_id_or_owner_email(): void
    {
        $owner = User::factory()->create(['email' => 'lina@clinic.test', 'name' => 'Lina Haddad']);
        $clinic = $this->businessOwnedBy($owner, ['name' => 'Shifa Clinic']);
        $this->businessOwnedBy(User::factory()->create(), ['name' => 'Bakery Basma']);

        $this->asAdmin($this->admin)->get('/admin/businesses')->assertOk()
            ->assertSee('Shifa Clinic')->assertSee('Bakery Basma')->assertSee('lina@clinic.test');
        foreach (['Shifa', $clinic->slug, $clinic->public_id, 'lina@clinic', 'Haddad'] as $search) {
            $this->asAdmin($this->admin)->get('/admin/businesses?q='.urlencode($search))->assertOk()
                ->assertSee('Shifa Clinic')->assertDontSee('Bakery Basma');
        }
        // Wildcards are text, not patterns.
        $this->asAdmin($this->admin)->get('/admin/businesses?q=%25')->assertOk()->assertDontSee('Shifa Clinic');
        $this->asAdmin($this->admin)->get('/admin/businesses?status=nonsense')->assertSessionHasErrors('status');
    }

    public function test_the_business_page_shows_owner_plan_branches_and_team(): void
    {
        [$business, $branch] = $this->publicBusiness('Shifa Clinic');
        $owner = $business->members()->with('user')->first()->user;
        $this->openQueueIn($branch);

        $this->asAdmin($this->admin)->get('/admin/businesses/'.$business->public_id)->assertOk()
            ->assertSee('Shifa Clinic')
            ->assertSee($business->public_id)
            ->assertSee('Main')
            ->assertSee(__('admin.states.trialing'))
            ->assertSee($owner->name)
            ->assertSee($owner->email)
            ->assertSee(__('admin.businesses.suspend'));
    }

    public function test_an_admin_approves_a_business_once(): void
    {
        $business = $this->businessOwnedBy(User::factory()->create());

        $this->asAdmin($this->admin)->post('/admin/businesses/'.$business->public_id.'/approve')
            ->assertRedirect()->assertSessionHas('status', __('admin.businesses.approved'));
        $business->refresh();
        $this->assertNotNull($business->approved_at);
        $this->assertSame($this->admin->getKey(), $business->approved_by);
        $entry = PlatformAuditLog::query()->where('action', PlatformAuditAction::BusinessApproved->value)->sole();
        $this->assertSame($business->getKey(), $entry->business_id);
        $this->assertSame($this->admin->getKey(), $entry->actor_id);
        $this->assertSame('business', $entry->subject_type);

        $this->asAdmin($this->admin)->post('/admin/businesses/'.$business->public_id.'/approve')
            ->assertSessionHas('error', __('admin.errors.already_approved'));
        $this->assertSame(1, PlatformAuditLog::query()->where('action', PlatformAuditAction::BusinessApproved->value)->count());
    }

    public function test_suspending_a_business_stops_its_team_and_hides_it_but_keeps_everything(): void
    {
        [$business] = $this->publicBusiness('Shifa Clinic');
        $ownerOfPublic = $business->members()->first()->user;
        $this->getJson($this->discovery('businesses'))->assertJsonCount(1, 'data');

        // A reason is required.
        $this->asAdmin($this->admin)->post('/admin/businesses/'.$business->public_id.'/suspend', ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->assertSame(BusinessStatus::Active, $business->fresh()->status);

        $this->asAdmin($this->admin)->post('/admin/businesses/'.$business->public_id.'/suspend', ['reason' => 'Fake reviews reported'])
            ->assertSessionHas('status', __('admin.businesses.suspended'));
        $business->refresh();
        $this->assertSame(BusinessStatus::Suspended, $business->status);
        $this->assertSame('Fake reviews reported', $business->suspension_reason);
        $this->assertSame('active', $business->status_before_suspension);
        $this->assertNotNull($business->suspended_at);

        // Gone from discovery; the team cannot change it any more.
        $this->getJson($this->discovery('businesses'))->assertJsonCount(0, 'data');
        $this->assertApiError(
            $this->actingAsUser($ownerOfPublic, 'PUT', '/api/v1/businesses/'.$business->public_id, ['name' => 'Renamed']),
            403,
            'business_suspended',
        );
        $this->assertSame('Shifa Clinic', $business->fresh()->name);

        // Both histories have it.
        $entry = PlatformAuditLog::query()->where('action', PlatformAuditAction::BusinessSuspended->value)->sole();
        $this->assertSame('Fake reviews reported', $entry->properties['reason']);
        $this->assertTrue(BusinessAuditLog::query()->where('business_id', $business->getKey())->where('action', 'business.status_changed')->exists());

        $this->asAdmin($this->admin)->post('/admin/businesses/'.$business->public_id.'/suspend', ['reason' => 'Again'])
            ->assertSessionHas('error', __('admin.errors.already_suspended'));
    }

    public function test_reactivation_restores_the_status_from_before(): void
    {
        $active = $this->businessOwnedBy(User::factory()->create(), ['name' => 'Was Active']);
        $inactive = $this->businessOwnedBy(User::factory()->create(), ['name' => 'Was Inactive']);
        $inactive->forceFill(['status' => BusinessStatus::Inactive])->save();

        foreach ([$active, $inactive] as $business) {
            $this->asAdmin($this->admin)->post('/admin/businesses/'.$business->public_id.'/suspend', ['reason' => 'Check']);
            $this->asAdmin($this->admin)->post('/admin/businesses/'.$business->public_id.'/reactivate')
                ->assertSessionHas('status', __('admin.businesses.reactivated'));
        }

        $this->assertSame(BusinessStatus::Active, $active->fresh()->status);
        $this->assertSame(BusinessStatus::Inactive, $inactive->fresh()->status);
        $this->assertNull($active->fresh()->suspended_at);
        $this->assertNull($active->fresh()->suspension_reason);
        $this->assertSame(2, PlatformAuditLog::query()->where('action', PlatformAuditAction::BusinessReactivated->value)->count());

        $this->asAdmin($this->admin)->post('/admin/businesses/'.$active->public_id.'/reactivate')
            ->assertSessionHas('error', __('admin.errors.not_suspended'));
    }

    public function test_an_archived_business_cannot_be_suspended_or_given_a_plan(): void
    {
        $business = $this->businessOwnedBy(User::factory()->create());
        $business->forceFill(['status' => BusinessStatus::Archived])->save();
        $business->delete();

        $this->asAdmin($this->admin)->get('/admin/businesses?status=archived')->assertOk()->assertSee('Shifa Clinic');
        $this->asAdmin($this->admin)->get('/admin/businesses/'.$business->public_id)->assertOk();
        $this->asAdmin($this->admin)->post('/admin/businesses/'.$business->public_id.'/suspend', ['reason' => 'x'])
            ->assertSessionHas('error', __('admin.errors.not_suspendable'));
        $this->asAdmin($this->admin)->post('/admin/businesses/'.$business->public_id.'/subscription/plan', ['plan' => 'business'])
            ->assertSessionHas('error', __('admin.errors.business_archived'));
        $this->assertNotSame('business', $this->subscriptionOf($business)->plan->code);
    }

    public function test_discovery_can_require_approval_without_stopping_queues(): void
    {
        [$approved] = $this->publicBusiness('Approved Clinic');
        [$pending, $branch] = $this->publicBusiness('Pending Clinic');
        [, $queue] = $this->openQueueIn($branch);
        $this->asAdmin($this->admin)->post('/admin/businesses/'.$approved->public_id.'/approve');

        // Off (the default): everything active shows.
        $this->getJson($this->discovery('businesses'))->assertJsonCount(2, 'data');

        $this->asAdmin($this->admin)->put('/admin/settings', ['require_business_approval' => '1'])->assertRedirect('/admin/settings');
        $this->forgetAuthenticatedUser();
        $names = collect($this->getJson($this->discovery('businesses'))->assertOk()->json('data'))->pluck('name')->all();
        $this->assertSame(['Approved Clinic'], $names);
        $this->getJson($this->discovery('businesses/'.$pending->slug))->assertNotFound();

        // Customers already holding the queue's link can still join.
        $this->postJson('/api/v1/public/queues/'.$queue->public_token_encrypted.'/join', ['name' => 'Walk-in'])->assertCreated();
    }

    public function test_the_list_and_page_show_the_computed_plan(): void
    {
        $business = $this->businessOwnedBy(User::factory()->create(), ['name' => 'Trial Ends']);
        // The 14-day trial ended, the scheduler has not run.
        $this->travel(15)->days();

        $this->asAdmin($this->admin)->get('/admin/businesses')->assertOk()->assertSee(__('admin.states.expired'));
        $this->asAdmin($this->admin)->get('/admin/businesses/'.$business->public_id)->assertOk()
            ->assertSee(__('admin.states.expired'))->assertSee('Free');
    }
}
