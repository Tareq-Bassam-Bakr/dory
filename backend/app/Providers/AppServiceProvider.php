<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Business\CurrentBusinessContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One business context per request; never shared between requests.
        $this->app->scoped(CurrentBusinessContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureModels();
        $this->configurePasswordRules();
        $this->configureRateLimiting();
        $this->configurePasswordResetLinks();
    }

    /**
     * Outside production, fail loudly on lazy loading, on assigning
     * non-fillable attributes, and on reading attributes that were not loaded.
     */
    private function configureModels(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
    }

    /**
     * Password policy for registration and reset: at least 8 characters with
     * upper and lower case letters and a number. In production, passwords
     * found in known data breaches are also rejected (Have I Been Pwned range
     * API: only a 5-character hash prefix leaves the server).
     */
    private function configurePasswordRules(): void
    {
        Password::defaults(function () {
            $rule = Password::min(8)->max(128)->letters()->mixedCase()->numbers();

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(config('dory.rate_limits.api'))
                ->by($request->user()?->getAuthIdentifier() ?: $request->ip());
        });

        // Brute-force protection: per account+IP, and per IP across accounts.
        RateLimiter::for('auth', function (Request $request) {
            $email = mb_strtolower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(config('dory.rate_limits.auth'))->by('auth:'.$email.'|'.$request->ip()),
                Limit::perMinute(config('dory.rate_limits.auth_per_ip'))->by('auth-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('password-reset', function (Request $request) {
            $email = mb_strtolower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(config('dory.rate_limits.password_reset'))->by('reset:'.$email.'|'.$request->ip()),
                Limit::perMinute(config('dory.rate_limits.auth_per_ip'))->by('reset-ip:'.$request->ip()),
            ];
        });
    }

    /**
     * The API has no web reset page; the email links to the configured client
     * URL (Flutter deep link or web page) with the token and email.
     */
    private function configurePasswordResetLinks(): void
    {
        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            return config('dory.password_reset_url').'?'.http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });
    }
}
