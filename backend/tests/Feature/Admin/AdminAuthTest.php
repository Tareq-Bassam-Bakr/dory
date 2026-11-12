<?php

namespace Tests\Feature\Admin;

use App\Enums\PlatformAuditAction;
use App\Models\PlatformAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesAdminDashboard;
use Tests\TestCase;

/**
 * Admin dashboard sign-in and sessions (docs/ADMIN_DASHBOARD.md, "Security").
 * These tests go through the real sign-in form and carry the session cookie
 * like a browser.
 */
class AdminAuthTest extends TestCase
{
    use RefreshDatabase, UsesAdminDashboard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-12 09:00:00', 'UTC'));
    }

    public function test_the_sign_in_page_is_served_with_strict_security_headers(): void
    {
        $response = $this->browse('GET', '/admin/login')->assertOk()
            ->assertSee('name="email"', false)
            ->assertSee('name="_token"', false);

        $policy = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("script-src 'self'", $policy);
        $this->assertStringContainsString("frame-ancestors 'none'", $policy);
        $this->assertStringNotContainsString('unsafe-inline', $policy);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
    }

    public function test_an_admin_signs_in_with_a_dashboard_only_session_cookie(): void
    {
        $admin = $this->platformAdmin();
        $apiSessionCookie = (string) config('session.cookie');

        $response = $this->signInWithForm($admin)->assertRedirect('/admin');

        $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === 'dory_admin_session');
        $this->assertNotNull($cookie, 'The admin session cookie is set.');
        $this->assertSame('/admin', $cookie->getPath());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('strict', $cookie->getSameSite());
        // Expires with the browser; the server ends it after 30 idle minutes.
        $this->assertSame(0, $cookie->getExpiresTime());
        // The API's own session cookie is never used here.
        $this->assertNotSame('dory_admin_session', $apiSessionCookie);
        $this->assertFalse(collect($response->headers->getCookies())->contains(fn ($c) => $c->getName() === $apiSessionCookie));

        $this->browse('GET', '/admin')->assertOk()->assertSee($admin->name);
        $this->assertSame(1, DB::table('admin_sessions')->where('user_id', $admin->getKey())->count());
        $this->assertNotNull($admin->fresh()->last_login_at);
        $this->assertTrue(PlatformAuditLog::query()->where('action', PlatformAuditAction::AdminSignedIn->value)->where('actor_id', $admin->getKey())->exists());
    }

    public function test_the_cookie_is_https_only_when_the_site_runs_on_https(): void
    {
        config(['app.url' => 'https://dory.example', 'admin.session.secure' => null]);

        $response = $this->signInWithForm($this->platformAdmin());

        $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === 'dory_admin_session');
        $this->assertTrue($cookie->isSecure());
    }

    public function test_an_empty_secure_cookie_setting_follows_the_site_address(): void
    {
        foreach (['' => null, 'true' => true, 'false' => false] as $value => $expected) {
            $_ENV['ADMIN_SESSION_SECURE_COOKIE'] = $_SERVER['ADMIN_SESSION_SECURE_COOKIE'] = (string) $value;
            $this->assertSame($expected, (require config_path('admin.php'))['session']['secure'], "ADMIN_SESSION_SECURE_COOKIE=$value");
        }
        unset($_ENV['ADMIN_SESSION_SECURE_COOKIE'], $_SERVER['ADMIN_SESSION_SECURE_COOKIE']);
        $this->assertNull((require config_path('admin.php'))['session']['secure']);
    }

    public function test_the_session_id_changes_at_sign_in(): void
    {
        $admin = $this->platformAdmin();
        $this->browse('GET', '/admin/login');
        $before = $this->adminCookie();
        $this->assertNotNull($before);

        $this->signInWithForm($admin);

        $this->assertNotNull($this->adminCookie());
        $this->assertNotSame($before, $this->adminCookie());
    }

    public function test_the_same_answer_for_a_wrong_password_an_unknown_address_and_a_non_admin(): void
    {
        $admin = $this->platformAdmin(['email' => 'root@dory.test']);
        $customer = User::factory()->create(['email' => 'customer@dory.test']);
        $message = __('admin.auth.failed');

        $this->signInWithForm($admin, 'not-the-password')->assertSessionHasErrors(['email' => $message]);
        $this->browse('POST', '/admin/login', ['email' => 'nobody@dory.test', 'password' => 'whatever-1'])->assertSessionHasErrors(['email' => $message]);
        // The right password of an account that is not an admin.
        $this->signInWithForm($customer)->assertSessionHasErrors(['email' => $message]);

        $this->browse('GET', '/admin')->assertRedirect('/admin/login');
        $failures = PlatformAuditLog::query()->where('action', PlatformAuditAction::AdminSignInFailed->value)->orderBy('id')->get();
        $this->assertCount(3, $failures);
        $this->assertSame($admin->getKey(), $failures[0]->actor_id);
        $this->assertSame('wrong_password', $failures[0]->properties['reason']);
        // MySQL stores JSON objects with their keys reordered: compare by key.
        $this->assertEqualsCanonicalizing(['email' => 'no***@dory.test', 'reason' => 'unknown_account'], $failures[1]->properties);
        $this->assertSame('no***@dory.test', $failures[1]->properties['email']);
        $this->assertSame('unknown_account', $failures[1]->properties['reason']);
        $this->assertNull($failures[2]->actor_id);
        $this->assertSame('not_allowed', $failures[2]->properties['reason']);
        // Passwords never reach the log.
        $this->assertStringNotContainsString('not-the-password', json_encode(PlatformAuditLog::query()->get()->toArray()));
    }

    public function test_suspended_admins_cannot_sign_in(): void
    {
        $admin = $this->platformAdmin(['status' => 'suspended']);

        $this->signInWithForm($admin)->assertSessionHasErrors('email');
        $this->browse('GET', '/admin')->assertRedirect('/admin/login');
    }

    public function test_sign_in_attempts_are_limited(): void
    {
        $admin = $this->platformAdmin();
        for ($i = 0; $i < 5; $i++) {
            $this->signInWithForm($admin, 'wrong-'.$i)->assertSessionHasErrors('email');
        }

        // Even the right password waits now.
        $this->signInWithForm($admin)->assertSessionHasErrors(['email' => __('admin.auth.throttled', ['seconds' => 60])]);
        $this->browse('GET', '/admin')->assertRedirect('/admin/login');

        $this->travel(61)->seconds();
        $this->signInWithForm($admin)->assertRedirect('/admin');
    }

    public function test_guests_and_api_tokens_never_reach_the_dashboard(): void
    {
        $admin = $this->platformAdmin();

        foreach (['/admin', '/admin/businesses', '/admin/settings', '/admin/audit'] as $page) {
            $this->browse('GET', $page)->assertRedirect('/admin/login');
        }
        // An admin's API token is for the API only.
        $this->forgetAuthenticatedUser();
        $this->withToken($this->tokenFor($admin))->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_an_idle_session_ends_after_thirty_minutes(): void
    {
        $this->signInWithForm($this->platformAdmin());
        $this->travel(29)->minutes();
        $this->browse('GET', '/admin')->assertOk();

        $this->travel(31)->minutes();
        $this->browse('GET', '/admin')->assertRedirect('/admin/login');
    }

    public function test_an_active_session_still_ends_after_eight_hours(): void
    {
        $this->signInWithForm($this->platformAdmin());
        for ($minutes = 25; $minutes < 8 * 60; $minutes += 25) {
            $this->travel(25)->minutes();
            $this->browse('GET', '/admin')->assertOk();
        }

        $this->travel(25)->minutes();
        $this->browse('GET', '/admin')->assertRedirect('/admin/login')
            ->assertSessionHas('status', __('admin.auth.session_expired'));
    }

    public function test_access_ends_at_once_when_an_admin_is_demoted_or_suspended(): void
    {
        $admin = $this->platformAdmin();
        $this->signInWithForm($admin);
        $this->browse('GET', '/admin')->assertOk();

        $admin->forceFill(['is_platform_admin' => false])->save();
        $this->browse('GET', '/admin/businesses')->assertRedirect('/admin/login')
            ->assertSessionHas('status', __('admin.auth.access_ended'));
        $this->browse('GET', '/admin')->assertRedirect('/admin/login');

        $other = $this->platformAdmin();
        $this->signInWithForm($other);
        $other->forceFill(['status' => 'suspended'])->save();
        $this->browse('GET', '/admin')->assertRedirect('/admin/login');
    }

    public function test_the_end_of_a_session_is_explained_in_the_admins_language(): void
    {
        $admin = $this->platformAdmin();
        $this->browse('GET', '/admin/login?lang=ar');
        $this->signInWithForm($admin)->assertRedirect('/admin');

        $admin->forceFill(['is_platform_admin' => false])->save();
        $this->browse('GET', '/admin')->assertRedirect('/admin/login');
        $this->browse('GET', '/admin/login')->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee(trans('admin.auth.access_ended', [], 'ar'));

        // Signing out too.
        $other = $this->platformAdmin();
        $this->signInWithForm($other);
        $this->browse('POST', '/admin/logout')->assertRedirect('/admin/login');
        $this->browse('GET', '/admin/login')->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee(trans('admin.auth.signed_out', [], 'ar'));
    }

    public function test_signing_out_ends_the_session_on_the_server(): void
    {
        $admin = $this->platformAdmin();
        $this->signInWithForm($admin);
        $stolen = $this->adminCookie();

        $this->browse('POST', '/admin/logout')->assertRedirect('/admin/login');
        $this->assertTrue(PlatformAuditLog::query()->where('action', PlatformAuditAction::AdminSignedOut->value)->exists());

        // The old cookie no longer opens anything.
        $this->cookieJar = ['dory_admin_session' => $stolen];
        $this->browse('GET', '/admin')->assertRedirect('/admin/login');
    }

    public function test_a_signed_in_admin_visiting_the_sign_in_page_goes_to_the_overview(): void
    {
        $this->signInWithForm($this->platformAdmin());

        $this->browse('GET', '/admin/login')->assertRedirect('/admin');
    }

    public function test_after_sign_in_the_admin_returns_to_the_page_they_asked_for(): void
    {
        $this->browse('GET', '/admin/payments?tab=paid')->assertRedirect('/admin/login');

        $this->signInWithForm($this->platformAdmin())->assertRedirect('/admin/payments?tab=paid');
    }
}
