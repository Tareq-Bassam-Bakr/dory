<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Session settings of the admin dashboard (config/admin.php), applied before
 * the session starts. Everything else in DORY keeps no sessions.
 *
 * - Stored server-side (admin_sessions), with the admin's id, so sessions
 *   can be ended at once.
 * - Its own cookie, sent to /admin only: HttpOnly, SameSite=Strict,
 *   encrypted, and Secure when the site runs on HTTPS.
 * - Ends after config('admin.session.idle_minutes') without activity, and
 *   when the browser closes. AuthenticateAdmin adds an absolute limit.
 *
 * The settings apply to the rest of the PHP process, which serves one
 * request (php-fpm, artisan serve). Not meant for long-running workers
 * such as Octane.
 */
class ConfigureAdminSession
{
    public function __construct(private readonly Application $app) {}

    public function handle(Request $request, Closure $next): Response
    {
        $settings = (array) config('admin.session');

        config([
            'session.driver' => $settings['driver'] ?? 'database',
            'session.table' => $settings['table'] ?? 'admin_sessions',
            'session.cookie' => $settings['cookie'] ?? 'dory_admin_session',
            'session.lifetime' => max(5, (int) ($settings['idle_minutes'] ?? 30)),
            'session.expire_on_close' => true,
            'session.encrypt' => true,
            'session.path' => '/admin',
            'session.domain' => null,
            'session.secure' => $settings['secure'] ?? str_starts_with((string) config('app.url'), 'https://'),
            'session.http_only' => true,
            'session.same_site' => 'strict',
            'session.partitioned' => false,
        ]);

        // The admin guard everywhere in the dashboard, sign-in included, so
        // each stored session row carries the admin's id from the start.
        Auth::shouldUse('admin');

        // Everything that keeps a session reference (the session guard, the
        // redirector's flash messages) must use this store, even when it was
        // resolved before this request reached the dashboard.
        $store = $this->app->make('session')->driver();
        $this->app->instance('session.store', $store);
        if ($this->app->resolved('redirect')) {
            $this->app->make('redirect')->setSession($store);
        }

        return $next($request);
    }
}
