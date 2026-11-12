<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * Requests to the admin dashboard (docs/ADMIN_DASHBOARD.md).
 *
 * - asAdmin(): signed in through the admin guard, for page and action tests.
 * - browse(): what a browser does, for session tests. Each request starts
 *   from nothing in memory, like a new PHP process in production: no cached
 *   guard user, no session object; only the cookies the server set earlier.
 */
trait UsesAdminDashboard
{
    /** @var array<string, string> cookie name => plain value */
    private array $cookieJar = [];

    protected function platformAdmin(array $attributes = []): User
    {
        return User::factory()->platformAdmin()->create($attributes);
    }

    /**
     * Signed in just now, in a new session (the absolute session limit
     * counts from here).
     */
    protected function asAdmin(?User $admin = null): static
    {
        $this->forgetAuthenticatedUser();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');

        return $this->actingAs($admin ?? $this->platformAdmin(), 'admin');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function browse(string $method, string $uri, array $data = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');

        // Only the admin session cookie matters here (tests skip CSRF checks).
        $name = (string) config('admin.session.cookie');
        $this->defaultCookies = $this->cookieJar;
        $response = $this->call($method, $uri, $data, $this->prepareCookiesForRequest());
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() !== $name) {
                continue;
            }
            if ($cookie->isCleared()) {
                unset($this->cookieJar[$name]);
            } else {
                $this->cookieJar[$name] = (string) $response->getCookie($name)?->getValue();
            }
        }

        return $response;
    }

    protected function signInWithForm(User $user, ?string $password = null): TestResponse
    {
        return $this->browse('POST', '/admin/login', [
            'email' => $user->email,
            'password' => $password ?? $this->factoryPassword(),
        ]);
    }

    protected function adminCookie(): ?string
    {
        return $this->cookieJar[config('admin.session.cookie')] ?? null;
    }
}
