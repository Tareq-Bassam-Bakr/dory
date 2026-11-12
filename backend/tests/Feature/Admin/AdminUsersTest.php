<?php

namespace Tests\Feature\Admin;

use App\Enums\BusinessRole;
use App\Enums\PlatformAuditAction;
use App\Enums\UserStatus;
use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTenants;
use Tests\Concerns\UsesAdminDashboard;
use Tests\TestCase;

/**
 * Accounts from the admin dashboard (docs/ADMIN_DASHBOARD.md, "Users").
 */
class AdminUsersTest extends TestCase
{
    use BuildsTenants, RefreshDatabase, UsesAdminDashboard;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->platformAdmin();
    }

    public function test_admins_list_and_filter_accounts(): void
    {
        $owner = User::factory()->create(['name' => 'Lina Owner', 'email' => 'lina@dory.test']);
        $this->businessOwnedBy($owner);
        User::factory()->create(['name' => 'Sami Customer']);
        User::factory()->suspended()->create(['name' => 'Omar Suspended']);

        $this->asAdmin($this->admin)->get('/admin/users')->assertOk()
            ->assertSee('Lina Owner')->assertSee('Sami Customer')->assertSee('Omar Suspended');
        $this->asAdmin($this->admin)->get('/admin/users?q=LINA@dory')->assertOk()
            ->assertSee('Lina Owner')->assertDontSee('Sami Customer');
        $this->asAdmin($this->admin)->get('/admin/users?status=suspended')->assertOk()
            ->assertSee('Omar Suspended')->assertDontSee('Lina Owner');
        $this->asAdmin($this->admin)->get('/admin/users?role=business')->assertOk()
            ->assertSee('Lina Owner')->assertDontSee('Sami Customer');
        $this->asAdmin($this->admin)->get('/admin/users?role=customer')->assertOk()
            ->assertSee('Sami Customer')->assertDontSee('Lina Owner');
        $this->asAdmin($this->admin)->get('/admin/users?role=admin')->assertOk()
            ->assertSee($this->admin->name)->assertDontSee('Sami Customer');
    }

    public function test_the_account_page_shows_memberships_without_secrets(): void
    {
        $owner = User::factory()->create(['name' => 'Lina Owner']);
        $this->businessOwnedBy($owner, ['name' => 'Shifa Clinic']);
        $this->tokenFor($owner);

        $html = (string) $this->asAdmin($this->admin)->get('/admin/users/'.$owner->public_id)->assertOk()
            ->assertSee('Shifa Clinic')
            ->assertSee(__('admin.roles.owner'))
            ->assertSee(trans_choice('admin.users.tokens', 1, ['count' => 1]))
            ->getContent();
        $this->assertStringNotContainsString($owner->password, $html);
    }

    public function test_suspending_an_account_signs_it_out_everywhere(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();

        $this->asAdmin($this->admin)->post('/admin/users/'.$user->public_id.'/suspend', ['reason' => ''])->assertSessionHasErrors('reason');
        $this->asAdmin($this->admin)->post('/admin/users/'.$user->public_id.'/suspend', ['reason' => 'Abusive reviews'])
            ->assertSessionHas('status', __('admin.users.suspended'));

        $this->assertSame(UserStatus::Suspended, $user->fresh()->status);
        $this->assertSame(0, $user->tokens()->count());
        $this->forgetAuthenticatedUser();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => $this->factoryPassword(), 'device_name' => 'phpunit'])
            ->assertForbidden();
        $entry = PlatformAuditLog::query()->where('action', PlatformAuditAction::UserSuspended->value)->sole();
        $this->assertSame('Abusive reviews', $entry->properties['reason']);
        $this->assertSame(1, $entry->properties['tokens_revoked']);
        $this->assertSame('user', $entry->subject_type);
        $this->assertSame($user->getKey(), $entry->subject_id);

        $this->asAdmin($this->admin)->post('/admin/users/'.$user->public_id.'/reactivate')
            ->assertSessionHas('status', __('admin.users.reactivated'));
        $this->assertSame(UserStatus::Active, $user->fresh()->status);
        $this->assertTrue(PlatformAuditLog::query()->where('action', PlatformAuditAction::UserReactivated->value)->exists());
    }

    public function test_admins_cannot_suspend_themselves_or_another_admin_even_without_the_button(): void
    {
        $other = $this->platformAdmin();

        foreach ([$this->admin, $other] as $target) {
            $page = $this->asAdmin($this->admin)->get('/admin/users/'.$target->public_id)->assertOk();
            $page->assertDontSee('/admin/users/'.$target->public_id.'/suspend', false);

            // Posting the form anyway changes nothing.
            $this->asAdmin($this->admin)->post('/admin/users/'.$target->public_id.'/suspend', ['reason' => 'Test'])
                ->assertSessionHas('error', __('admin.errors.'.($target->is($this->admin) ? 'own_account' : 'admin_account')));
            $this->assertSame(UserStatus::Active, $target->fresh()->status);
        }
        $this->assertFalse(PlatformAuditLog::query()->where('action', PlatformAuditAction::UserSuspended->value)->exists());
    }

    public function test_a_suspended_member_keeps_the_membership_record(): void
    {
        $business = $this->businessOwnedBy(User::factory()->create());
        $manager = $this->memberOf($business, BusinessRole::Manager);

        $this->asAdmin($this->admin)->post('/admin/users/'.$manager->public_id.'/suspend', ['reason' => 'Left the company']);

        $this->assertNotNull($this->membershipOf($manager, $business));
        $this->assertSame(UserStatus::Suspended, $manager->fresh()->status);
        $this->asAdmin($this->admin)->post('/admin/users/'.$manager->public_id.'/suspend', ['reason' => 'Again'])
            ->assertSessionHas('error', __('admin.errors.already_suspended'));
    }
}
