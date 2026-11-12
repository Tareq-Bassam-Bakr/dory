<?php

namespace Tests\Feature\Admin;

use App\Enums\PlatformAuditAction;
use App\Enums\SubscriptionEventType;
use App\Models\Business;
use App\Models\PlatformAuditLog;
use App\Models\SubscriptionEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsSubscriptions;
use Tests\Concerns\BuildsTenants;
use Tests\Concerns\UsesAdminDashboard;
use Tests\TestCase;

/**
 * Subscription changes from the admin dashboard (docs/ADMIN_DASHBOARD.md,
 * "Billing"), through the same lifecycle as the API.
 */
class AdminSubscriptionsTest extends TestCase
{
    use BuildsSubscriptions, BuildsTenants, RefreshDatabase, UsesAdminDashboard;

    private User $admin;

    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-12 09:00:00', 'UTC'));
        $this->admin = $this->platformAdmin();
        $this->business = $this->businessOwnedBy(User::factory()->create(), ['name' => 'Shifa Clinic']);
    }

    private function uri(string $action): string
    {
        return '/admin/businesses/'.$this->business->public_id.'/subscription/'.$action;
    }

    public function test_an_admin_extends_a_trial(): void
    {
        $end = $this->subscriptionOf($this->business)->trial_ends_at->copy();

        $this->asAdmin($this->admin)->post($this->uri('trial'), ['days' => 120])->assertSessionHasErrors('days');
        $this->asAdmin($this->admin)->post($this->uri('trial'), ['days' => 10])
            ->assertSessionHas('status', __('admin.subscriptions.trial_extended'));

        $this->assertTrue($this->subscriptionOf($this->business)->trial_ends_at->equalTo($end->addDays(10)));
        $entry = PlatformAuditLog::query()->where('action', PlatformAuditAction::TrialExtended->value)->sole();
        $this->assertSame(10, $entry->properties['days']);
        $event = SubscriptionEvent::query()->where('type', SubscriptionEventType::TrialExtended->value)->sole();
        $this->assertSame($this->admin->getKey(), $event->actor_id);

        $this->asAdmin($this->admin)->post($this->uri('trial'), ['days' => 5, 'plan' => 'free'])
            ->assertSessionHas('error', __('admin.errors.plan_not_available'));
    }

    public function test_an_admin_gives_a_plan_until_a_date(): void
    {
        $this->asAdmin($this->admin)->post($this->uri('plan'), ['plan' => 'business', 'until' => '2026-11-01'])->assertSessionHasErrors('until');
        $this->asAdmin($this->admin)->post($this->uri('plan'), ['plan' => 'business', 'until' => '2026-12-31', 'note' => 'Partner pilot'])
            ->assertSessionHas('status', __('admin.subscriptions.plan_assigned'));

        $subscription = $this->subscriptionOf($this->business);
        $this->assertSame('business', $subscription->plan->code);
        $this->assertSame('2026-12-31 23:59:59', $subscription->current_period_end->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('Partner pilot', PlatformAuditLog::query()->where('action', PlatformAuditAction::PlanAssigned->value)->sole()->properties['note']);

        // Shown as complimentary, not as paid revenue, with its end date.
        $this->asAdmin($this->admin)->get('/admin/subscriptions?state=complimentary')->assertOk()
            ->assertSee('Shifa Clinic')->assertSee('31 Dec 2026');
        $this->asAdmin($this->admin)->get('/admin/subscriptions?state=paid')->assertOk()->assertDontSee('Shifa Clinic');

        $this->travelTo(Carbon::parse('2027-01-01 00:00:01', 'UTC'));
        $this->asAdmin($this->admin)->get('/admin/subscriptions?state=expired')->assertOk()->assertSee('Shifa Clinic');
    }

    public function test_cancel_resume_and_end_follow_the_lifecycle(): void
    {
        $this->travel(15)->days();
        $this->paidPlan($this->business, 'pro');

        $this->asAdmin($this->admin)->post($this->uri('cancel'))->assertSessionHas('status', __('admin.subscriptions.canceled_done'));
        $this->assertTrue($this->subscriptionOf($this->business)->cancel_at_period_end);
        $this->asAdmin($this->admin)->post($this->uri('cancel'))->assertSessionHas('error', __('admin.errors.change.already_canceled'));

        $this->asAdmin($this->admin)->post($this->uri('resume'))->assertSessionHas('status', __('admin.subscriptions.resumed'));
        $this->assertFalse($this->subscriptionOf($this->business)->cancel_at_period_end);

        $this->asAdmin($this->admin)->post($this->uri('end'))->assertSessionHas('status', __('admin.subscriptions.ended'));
        $this->assertSame('expired', $this->subscriptionOf($this->business)->status->value);
        $this->asAdmin($this->admin)->post($this->uri('end'))->assertSessionHas('error', __('admin.errors.change.already_expired'));

        foreach ([PlatformAuditAction::SubscriptionCanceled, PlatformAuditAction::SubscriptionResumed, PlatformAuditAction::SubscriptionEnded] as $action) {
            $this->assertSame(1, PlatformAuditLog::query()->where('action', $action->value)->count(), $action->value);
        }
    }

    public function test_refusals_are_shown_in_the_admins_language(): void
    {
        $this->travel(15)->days();
        $this->paidPlan($this->business, 'pro');
        $this->asAdmin($this->admin)->post($this->uri('cancel'));

        $this->asAdmin($this->admin)->post($this->uri('cancel').'?lang=ar')
            ->assertSessionHas('error', trans('admin.errors.change.already_canceled', [], 'ar'));
    }

    public function test_the_list_groups_businesses_by_their_state_now(): void
    {
        $paid = $this->businessOwnedBy(User::factory()->create(), ['name' => 'Paid Clinic']);
        $this->travel(15)->days();
        $this->paidPlan($paid, 'pro');

        $page = $this->asAdmin($this->admin)->get('/admin/subscriptions')->assertOk();
        $page->assertSee(__('admin.states.paid').' (1)')->assertSee(__('admin.states.expired').' (1)');
        $this->asAdmin($this->admin)->get('/admin/subscriptions?state=paid')->assertOk()
            ->assertSee('Paid Clinic')->assertDontSee('Shifa Clinic');
        $this->asAdmin($this->admin)->get('/admin/subscriptions?state=expired&q=shifa')->assertOk()
            ->assertSee('Shifa Clinic')->assertDontSee('Paid Clinic');
        $this->asAdmin($this->admin)->get('/admin/subscriptions?state=bogus')->assertSessionHasErrors('state');
    }

    public function test_overdue_accounts_appear_on_the_overview(): void
    {
        $this->travel(15)->days();
        $this->paidPlan($this->business, 'pro');
        // The month ended unpaid: grace days.
        $this->travel(32)->days();

        $this->asAdmin($this->admin)->get('/admin')->assertOk()
            ->assertSee(__('admin.overview.overdue_title'))
            ->assertSee('Shifa Clinic');
        $this->asAdmin($this->admin)->get('/admin/subscriptions?state=past_due')->assertOk()->assertSee('Shifa Clinic');
    }
}
