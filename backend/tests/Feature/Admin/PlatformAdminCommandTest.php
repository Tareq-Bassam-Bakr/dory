<?php

namespace Tests\Feature\Admin;

use App\Enums\PlatformAuditAction;
use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\UsesAdminDashboard;
use Tests\TestCase;

/**
 * php artisan dory:admin, the only way to make a platform admin
 * (docs/ADMIN_DASHBOARD.md, "Admin accounts").
 */
class PlatformAdminCommandTest extends TestCase
{
    use RefreshDatabase, UsesAdminDashboard;

    public function test_granting_makes_an_existing_account_an_admin_and_is_audited(): void
    {
        $user = User::factory()->create(['email' => 'rana@dory.test']);

        $this->artisan('dory:admin', ['action' => 'grant', 'email' => 'RANA@dory.test '])->assertSuccessful();

        $this->assertTrue($user->fresh()->is_platform_admin);
        $entry = PlatformAuditLog::query()->where('action', PlatformAuditAction::AdminGranted->value)->sole();
        $this->assertSame('console', $entry->actor_label);
        $this->assertNull($entry->actor_id);
        $this->assertNull($entry->ip_address);
        $this->assertSame($user->getKey(), $entry->subject_id);

        // And they can sign in to the dashboard.
        $this->signInWithForm($user)->assertRedirect('/admin');
    }

    public function test_granting_needs_an_active_existing_account(): void
    {
        $this->artisan('dory:admin', ['action' => 'grant', 'email' => 'nobody@dory.test'])->assertFailed();
        $suspended = User::factory()->suspended()->create();
        $this->artisan('dory:admin', ['action' => 'grant', 'email' => $suspended->email])->assertFailed();
        $this->assertFalse($suspended->fresh()->is_platform_admin);
        $this->artisan('dory:admin', ['action' => 'grant'])->assertExitCode(2);
        $this->artisan('dory:admin', ['action' => 'promote', 'email' => 'x@dory.test'])->assertExitCode(2);
    }

    public function test_revoking_ends_the_dashboard_sessions_at_once(): void
    {
        $admin = $this->platformAdmin();
        $this->signInWithForm($admin);
        $this->browse('GET', '/admin')->assertOk();
        $this->assertSame(1, DB::table('admin_sessions')->where('user_id', $admin->getKey())->count());

        $this->artisan('dory:admin', ['action' => 'revoke', 'email' => $admin->email])->assertSuccessful();

        $this->assertFalse($admin->fresh()->is_platform_admin);
        $this->assertSame(0, DB::table('admin_sessions')->where('user_id', $admin->getKey())->count());
        $this->browse('GET', '/admin')->assertRedirect('/admin/login');
        $this->assertSame(1, PlatformAuditLog::query()->where('action', PlatformAuditAction::AdminRevoked->value)->sole()->properties['sessions_ended']);
        $this->artisan('dory:admin', ['action' => 'revoke', 'email' => $admin->email])->assertFailed();
    }

    public function test_creating_an_admin_asks_for_a_strong_password_twice(): void
    {
        $this->artisan('dory:admin', ['action' => 'create', 'email' => 'new.admin@dory.test', '--name' => 'New Admin'])
            ->expectsQuestion('Password (at least 8 characters, upper and lower case and a number)', 'weak')
            ->expectsQuestion('Repeat the password', 'weak')
            ->assertFailed();
        $this->assertFalse(User::query()->where('email', 'new.admin@dory.test')->exists());

        $this->artisan('dory:admin', ['action' => 'create', 'email' => 'new.admin@dory.test', '--name' => 'New Admin'])
            ->expectsQuestion('Password (at least 8 characters, upper and lower case and a number)', 'Str0ng-Admin-Pass')
            ->expectsQuestion('Repeat the password', 'Different-Pass-1')
            ->assertFailed();

        $this->artisan('dory:admin', ['action' => 'create', 'email' => 'new.admin@dory.test', '--name' => 'New Admin'])
            ->expectsQuestion('Password (at least 8 characters, upper and lower case and a number)', 'Str0ng-Admin-Pass')
            ->expectsQuestion('Repeat the password', 'Str0ng-Admin-Pass')
            ->assertSuccessful();

        $admin = User::query()->where('email', 'new.admin@dory.test')->sole();
        $this->assertTrue($admin->is_platform_admin);
        $this->assertTrue(Hash::check('Str0ng-Admin-Pass', $admin->password));
        $this->assertNotNull($admin->email_verified_at);
        $entry = PlatformAuditLog::query()->where('action', PlatformAuditAction::AdminCreated->value)->sole();
        $this->assertStringNotContainsString('Str0ng-Admin-Pass', json_encode($entry->toArray()));

        $this->artisan('dory:admin', ['action' => 'create', 'email' => 'new.admin@dory.test', '--name' => 'Again'])->assertFailed();
    }

    public function test_listing_shows_admins_only(): void
    {
        $admin = $this->platformAdmin(['name' => 'Rana Admin']);
        User::factory()->create(['name' => 'Sami Customer']);

        $this->artisan('dory:admin', ['action' => 'list'])
            ->expectsOutputToContain($admin->email)
            ->doesntExpectOutputToContain('Sami Customer')
            ->assertSuccessful();
    }
}
