<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every admin dashboard request (docs/ADMIN_DASHBOARD.md, "Security"):
 *
 * - signed in through the admin guard, or sent to the sign-in page;
 * - still a platform admin and still active: re-read from the database on
 *   each request, so a revoked flag or a suspension ends access at once;
 * - signed in less than config('admin.session.absolute_hours') ago.
 *
 * Runs before route bindings (bootstrap/app.php), so nothing is looked up
 * for anyone else.
 */
class AuthenticateAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('admin');
        $guard = Auth::guard('admin');
        $user = $guard->user();
        if ($user === null) {
            return redirect()->guest(route('admin.login'));
        }

        $fresh = User::query()->whereKey($user->getKey())->first();
        $session = $request->session();
        $signedInAt = (int) $session->get('admin.signed_in_at', 0);
        if ($signedInAt === 0) {
            $session->put('admin.signed_in_at', $signedInAt = now()->timestamp);
        }
        $expired = now()->timestamp - $signedInAt > max(1, (int) config('admin.session.absolute_hours')) * 3600;

        if ($fresh === null || $expired || ! Gate::forUser($fresh)->allows('platform.admin')) {
            $guard->logout();
            $session->invalidate();
            $session->regenerateToken();
            // The sign-in page keeps the admin's language.
            $session->put('admin.locale', App::getLocale());

            return redirect()->route('admin.login')
                ->with('status', __($expired ? 'admin.auth.session_expired' : 'admin.auth.access_ended'));
        }

        $guard->setUser($fresh);

        return $next($request);
    }
}
