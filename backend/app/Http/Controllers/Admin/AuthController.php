<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformAuditAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Platform\PlatformAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin dashboard sign-in (docs/ADMIN_DASHBOARD.md, "Security").
 *
 * - Only active platform admins get in. Anyone else, a wrong password and
 *   an unknown address all get the same answer, in about the same time.
 * - Attempts are limited per address and account, and per address overall.
 * - The session id changes at sign-in (no fixation) and the session ends at
 *   sign-out. Every outcome is in the platform audit log.
 */
class AuthController extends Controller
{
    private static ?string $timingSafetyHash = null;

    public function __construct(private readonly PlatformAuditLogger $audit) {}

    public function show(): View|RedirectResponse
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.overview');
        }

        return view('admin.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);
        $email = mb_strtolower(trim($credentials['email']));
        $limits = [
            'admin-login:'.sha1($email.'|'.$request->ip()) => max(1, (int) config('admin.rate_limits.login')),
            'admin-login-ip:'.$request->ip() => max(1, (int) config('admin.rate_limits.login_per_ip')),
        ];
        foreach ($limits as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw ValidationException::withMessages([
                    'email' => __('admin.auth.throttled', ['seconds' => RateLimiter::availableIn($key)]),
                ]);
            }
        }

        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            // Same work as for an existing account, so timing tells nothing.
            Hash::check($credentials['password'], self::$timingSafetyHash ??= Hash::make(Str::random(40)));
            $passwordMatches = false;
        } else {
            $passwordMatches = Hash::check($credentials['password'], $user->password);
        }

        if (! $passwordMatches || ! Gate::forUser($user)->allows('platform.admin')) {
            foreach (array_keys($limits) as $key) {
                RateLimiter::hit($key, 60);
            }
            $this->audit->record(
                PlatformAuditAction::AdminSignInFailed,
                $user?->isPlatformAdmin() ? $user : null,
                properties: [
                    'email' => $this->mask($email),
                    'reason' => match (true) {
                        $user === null => 'unknown_account',
                        ! $passwordMatches => 'wrong_password',
                        default => 'not_allowed',
                    },
                ],
                actorLabel: $user?->isPlatformAdmin() ? null : 'guest',
            );

            throw ValidationException::withMessages(['email' => __('admin.auth.failed')]);
        }

        RateLimiter::clear(array_key_first($limits));
        Auth::guard('admin')->login($user);
        $request->session()->regenerate();
        $request->session()->put('admin.signed_in_at', now()->timestamp);

        if (Hash::needsRehash($user->password)) {
            $user->password = $credentials['password']; // Re-hashed by the "hashed" cast.
        }
        $user->forceFill(['last_login_at' => now()])->saveOrFail();
        $this->audit->record(PlatformAuditAction::AdminSignedIn, $user);

        return redirect()->intended(route('admin.overview'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $admin = $request->user('admin');
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        // The sign-in page keeps the admin's language.
        $request->session()->put('admin.locale', app()->getLocale());
        if ($admin instanceof User) {
            $this->audit->record(PlatformAuditAction::AdminSignedOut, $admin);
        }

        return redirect()->route('admin.login')->with('status', __('admin.auth.signed_out'));
    }

    /**
     * "ad***@example.com": enough to recognise an attack on an account,
     * without keeping every address someone typed.
     */
    private function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 2).'***@'.$domain;
    }
}
