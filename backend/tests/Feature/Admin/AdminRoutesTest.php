<?php

namespace Tests\Feature\Admin;

use App\Enums\BillingPeriod;
use App\Enums\PaymentMethod;
use App\Http\Middleware\AuthenticateAdmin;
use App\Http\Middleware\ConfigureAdminSession;
use App\Http\Middleware\SetAdminLocale;
use App\Models\BillingInvoice;
use App\Models\Business;
use App\Models\PlatformAuditLog;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Admin\BillingAdministration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Routing\Router;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsSubscriptions;
use Tests\Concerns\BuildsTenants;
use Tests\Concerns\UsesAdminDashboard;
use Tests\TestCase;

/**
 * Every admin dashboard route is for active platform admins only, checked
 * on the server for every request (docs/ADMIN_DASHBOARD.md, "Security").
 * Hiding a button is never the protection.
 */
class AdminRoutesTest extends TestCase
{
    use BuildsSubscriptions, BuildsTenants, RefreshDatabase, UsesAdminDashboard;

    /**
     * @return list<RouteDefinition>
     */
    private function adminRoutes(): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn (RouteDefinition $route) => $route->uri() === 'admin' || str_starts_with($route->uri(), 'admin/'),
        ));
    }

    public function test_every_admin_route_except_sign_in_requires_a_platform_admin(): void
    {
        $routes = $this->adminRoutes();
        $this->assertGreaterThan(30, count($routes));
        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();
            $this->assertContains('admin', $middleware, "{$route->uri()} is outside the admin group.");
            if ($route->uri() === 'admin/login') {
                continue;
            }
            $this->assertContains('admin.auth', $middleware, "{$route->uri()} does not require an admin.");
            $this->assertContains('can:platform.admin', $middleware, "{$route->uri()} does not check platform.admin.");
            $this->assertContains('throttle:admin', $middleware, "{$route->uri()} is not rate limited.");
        }
    }

    public function test_the_admin_check_runs_before_any_record_is_looked_up(): void
    {
        $router = $this->app->make(Router::class);
        foreach ($this->adminRoutes() as $route) {
            $order = array_map(fn ($m) => is_string($m) ? explode(':', $m)[0] : $m, $router->gatherRouteMiddleware($route));
            $configure = array_search(ConfigureAdminSession::class, $order, true);
            $session = array_search(StartSession::class, $order, true);
            $bindings = array_search(SubstituteBindings::class, $order, true);
            $this->assertNotFalse($configure);
            $this->assertLessThan($session, $configure, "{$route->uri()}: the admin session must be configured first.");
            if ($route->uri() !== 'admin/login') {
                $auth = array_search(AuthenticateAdmin::class, $order, true);
                $this->assertNotFalse($auth);
                $this->assertLessThan($bindings, $auth, "{$route->uri()}: route bindings run before the admin check.");
                // The language is known before the admin check speaks.
                $this->assertLessThan($auth, array_search(SetAdminLocale::class, $order, true), "{$route->uri()}: the language is set after the admin check.");
            }
        }
    }

    public function test_nobody_but_an_admin_can_use_any_page_or_action(): void
    {
        $owner = User::factory()->create();
        $business = $this->businessOwnedBy($owner);
        $customer = User::factory()->create();
        $invoice = app(BillingAdministration::class)->recordPayment($business, [
            'plan' => $this->plan('pro'),
            'billing_period' => BillingPeriod::Monthly,
            'currency' => 'USD',
            'method' => PaymentMethod::Cash,
        ], $this->platformAdmin());
        $before = $this->snapshot($business, $customer, $invoice);
        $auditBefore = PlatformAuditLog::query()->count();

        foreach ([null, $customer, $owner] as $who) {
            foreach ($this->adminRoutes() as $route) {
                if ($route->uri() === 'admin/login') {
                    continue;
                }
                $uri = '/'.strtr($route->uri(), [
                    '{adminBusiness}' => $business->public_id,
                    '{adminUser}' => $customer->public_id,
                    '{adminPlan}' => 'pro',
                    '{adminInvoice}' => $invoice->public_id,
                ]);
                foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                    $this->forgetAuthenticatedUser();
                    if ($who !== null) {
                        $this->actingAs($who, 'admin');
                    }
                    $response = $this->call($method, $uri, $this->payloadFor($route));
                    $this->assertTrue($response->isRedirect(url('/admin/login')), "$method $uri answered {$response->getStatusCode()} to ".($who?->email ?? 'a guest'));
                }
            }
        }

        $this->assertSame($before, $this->snapshot($business, $customer, $invoice));
        $this->assertSame($auditBefore, PlatformAuditLog::query()->count());
    }

    public function test_an_admin_reaches_every_page(): void
    {
        $business = $this->businessOwnedBy(User::factory()->create());
        $customer = User::factory()->create();
        $admin = $this->platformAdmin();
        $invoice = app(BillingAdministration::class)->recordPayment($business, [
            'plan' => $this->plan('pro'),
            'billing_period' => BillingPeriod::Monthly,
            'currency' => 'USD',
            'method' => PaymentMethod::Cash,
        ], $admin);

        foreach ($this->adminRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true) || $route->uri() === 'admin/login') {
                continue;
            }
            $uri = '/'.strtr($route->uri(), [
                '{adminBusiness}' => $business->public_id,
                '{adminUser}' => $customer->public_id,
                '{adminPlan}' => 'pro',
                '{adminInvoice}' => $invoice->public_id,
            ]);
            foreach (['en', 'ar'] as $locale) {
                $this->asAdmin($admin)->get($uri.'?lang='.$locale)->assertOk()
                    ->assertSee('<html lang="'.$locale.'" dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'">', false);
            }
        }
    }

    public function test_pages_carry_no_inline_script_or_style(): void
    {
        $admin = $this->platformAdmin();
        foreach (['/admin', '/admin/businesses', '/admin/plans/create', '/admin/payments/create', '/admin/settings', '/admin/revenue'] as $page) {
            $html = (string) $this->asAdmin($admin)->get($page)->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bsrc=)[^>]*>/i', $html, "$page has an inline script.");
            $this->assertDoesNotMatchRegularExpression('/\sstyle\s*=/i', $html, "$page has an inline style.");
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $html, "$page has an inline event handler.");
        }
    }

    public function test_unknown_records_are_not_found_for_admins(): void
    {
        $this->asAdmin()->get('/admin/businesses/01JZZZZZZZZZZZZZZZZZZZZZZZ')->assertNotFound();
        $this->asAdmin()->get('/admin/users/01JZZZZZZZZZZZZZZZZZZZZZZZ')->assertNotFound();
        $this->asAdmin()->get('/admin/payments/01JZZZZZZZZZZZZZZZZZZZZZZZ')->assertNotFound();
        $this->asAdmin()->get('/admin/plans/nothing/edit')->assertNotFound();
    }

    public function test_no_public_or_account_request_can_make_an_admin(): void
    {
        $registration = [
            'name' => 'Eve',
            'email' => 'eve@dory.test',
            'password' => 'Correct-Horse-9-Battery',
            'password_confirmation' => 'Correct-Horse-9-Battery',
            'device_name' => 'phpunit',
        ];
        $this->assertApiError($this->postJson('/api/v1/auth/register', $registration + ['is_platform_admin' => true]), 422, 'validation_failed')
            ->assertJsonValidationErrors('is_platform_admin');
        $this->assertFalse(User::query()->where('email', 'eve@dory.test')->exists());
        $this->postJson('/api/v1/auth/register', $registration)->assertCreated();
        $this->assertFalse(User::query()->where('email', 'eve@dory.test')->firstOrFail()->is_platform_admin);

        $user = User::factory()->create();
        $this->assertApiError($this->actingAsUser($user, 'PUT', '/api/v1/users/profile', ['name' => 'Mallory', 'is_platform_admin' => true]), 422, 'validation_failed');
        $this->assertFalse($user->fresh()->is_platform_admin);
        $this->assertNotContains('is_platform_admin', (new User)->getFillable());

        // No route of any kind sets the flag: only `php artisan dory:admin` does.
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $this->assertStringNotContainsString('ManagePlatformAdmins', (string) $route->getActionName());
        }
    }

    /**
     * Enough input to pass validation, so a missing check would show.
     *
     * @return array<string, mixed>
     */
    private function payloadFor(RouteDefinition $route): array
    {
        return match ($route->getName()) {
            'admin.businesses.suspend', 'admin.users.suspend' => ['reason' => 'Test'],
            'admin.subscriptions.trial' => ['days' => 30],
            'admin.subscriptions.plan' => ['plan' => 'business'],
            'admin.payments.reject' => ['reason' => 'Test'],
            'admin.payments.store' => ['business' => 'x', 'plan' => 'pro', 'billing_period' => 'monthly', 'currency' => 'USD', 'method' => 'cash', 'received_at' => '2026-11-01'],
            'admin.plans.store' => ['code' => 'evil', 'is_free' => '1', 'name_en' => 'E', 'name_ar' => 'E', 'sort_order' => 1,
                'max_branches_unlimited' => '1', 'max_queues_unlimited' => '1', 'max_members_unlimited' => '1'],
            'admin.plans.update' => ['name_en' => 'Hacked', 'name_ar' => 'Hacked', 'sort_order' => 1,
                'max_branches_unlimited' => '1', 'max_queues_unlimited' => '1', 'max_members_unlimited' => '1'],
            'admin.settings.update' => ['billing_enabled' => '0', 'trial_days' => 90],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Business $business, User $customer, BillingInvoice $invoice): array
    {
        $business = $business->fresh();

        return [
            'business' => [$business->status->value, $business->approved_at?->toIso8601String()],
            'customer' => $customer->fresh()->status->value,
            'invoice' => $invoice->fresh()->status->value,
            'subscription' => [$this->subscriptionOf($business)->plan->code, $this->subscriptionOf($business)->status->value, $this->subscriptionOf($business)->trial_ends_at?->toIso8601String()],
            'plans' => SubscriptionPlan::query()->orderBy('id')->get(['code', 'name_en', 'max_branches'])->toArray(),
            'settings' => PlatformSetting::query()->count(),
            'admins' => User::query()->where('is_platform_admin', true)->count(),
        ];
    }
}
