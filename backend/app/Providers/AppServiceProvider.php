<?php

namespace App\Providers;

use App\Contracts\InvitationNotifier;
use App\Contracts\WaitTimeEstimator;
use App\Models\Queue;
use App\Models\User;
use App\Services\Business\CurrentBusinessContext;
use App\Services\Business\TenantResolver;
use App\Services\Invitations\MailInvitationNotifier;
use App\Services\Queues\HistoricalWaitTimeEstimator;
use App\Services\Queues\PublicQueueResolver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
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

        // Invitation delivery. Swap the implementation to add channels
        // (SMS, WhatsApp) without touching the invitation workflow.
        $this->app->bind(InvitationNotifier::class, MailInvitationNotifier::class);

        // Wait-time estimates (docs/WAIT_TIME_ESTIMATION.md). Scoped: the
        // per-queue rate cache never outlives a request or job.
        $this->app->scoped(WaitTimeEstimator::class, HistoricalWaitTimeEstimator::class);
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
        $this->configureRouteBindings();
    }

    /**
     * Tenant route parameters are resolved through the authenticated user's
     * own memberships and branch access (TenantResolver), never by a global
     * lookup. Anything the user may not reach is a 404 identical to a
     * missing record. Public ids are ULIDs; anything else never matches.
     */
    private function configureRouteBindings(): void
    {
        $publicIds = [
            'business', 'branch', 'service', 'member', 'invitation',
            'queue', 'queueSession', 'ticket', 'assignment', 'joinableQueue', 'myTicket', 'publicTicket',
        ];
        foreach ($publicIds as $parameter) {
            Route::pattern($parameter, '[0-9A-Za-z]{26}');
        }
        Route::pattern('invitationToken', '[A-Za-z0-9]{64}');
        Route::pattern('publicQueueToken', '[A-Za-z0-9]{40}');

        $resolver = fn (): TenantResolver => $this->app->make(TenantResolver::class);
        $user = fn (): ?User => request()->user();

        Route::bind('business', fn (string $value) => $resolver()->business($user(), $value));
        Route::bind('branch', fn (string $value) => $resolver()->branch($user(), $value));
        Route::bind('service', fn (string $value) => $resolver()->service($user(), $value));
        Route::bind('member', fn (string $value) => $resolver()->member($value));
        Route::bind('invitation', fn (string $value) => $resolver()->invitation($value));
        Route::bind('invitationToken', fn (string $value) => $resolver()->invitationByToken($value));

        // Queue engine, business side (members only).
        Route::bind('queue', fn (string $value) => $resolver()->queue($user(), $value));
        Route::bind('queueSession', fn (string $value) => $resolver()->queueSession($user(), $value));
        Route::bind('ticket', fn (string $value) => $resolver()->staffTicket($user(), $value));
        Route::bind('assignment', function (string $value, RouteInstance $route) use ($resolver) {
            $queue = $route->parameter('queue');

            return $resolver()->assignment($queue instanceof Queue ? $queue : null, $value);
        });

        // Queue engine, customer and guest side (no membership needed).
        $public = fn (): PublicQueueResolver => $this->app->make(PublicQueueResolver::class);
        Route::bind('joinableQueue', fn (string $value) => $public()->joinableQueue($value));
        Route::bind('myTicket', fn (string $value) => $public()->myTicket($user(), $value));
        Route::bind('publicQueueToken', fn (string $value) => $public()->byPublicToken($value));
        Route::bind('publicTicket', fn (string $value) => $public()->publicTicket($value));
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

        // Sending invitations, per inviting user.
        RateLimiter::for('invitations', function (Request $request) {
            return Limit::perMinute(config('dory.rate_limits.invitations'))
                ->by('invite:'.($request->user()?->getAuthIdentifier() ?: $request->ip()));
        });

        // Token lookups (view, accept, decline), per IP: tokens are 64 random
        // characters, and this keeps guessing impractical as well as impossible.
        RateLimiter::for('invitation-token', function (Request $request) {
            return Limit::perMinute(config('dory.rate_limits.invitation_token'))->by('invite-token:'.$request->ip());
        });

        // Joining a queue: per user (or IP for guests) and queue, and per IP.
        RateLimiter::for('queue-join', function (Request $request) {
            $route = $request->route();
            $queue = (string) ($route?->originalParameter('joinableQueue') ?? $route?->originalParameter('publicQueueToken') ?? '');
            $who = $request->user()?->getAuthIdentifier() ?: $request->ip();

            return [
                Limit::perMinute(config('dory.rate_limits.queue_join'))->by('queue-join:'.$who.'|'.hash('sha256', $queue)),
                Limit::perMinute(config('dory.rate_limits.auth_per_ip'))->by('queue-join-ip:'.$request->ip()),
            ];
        });

        // Public queue and ticket lookups (guest page, polling), per IP.
        RateLimiter::for('queue-public', function (Request $request) {
            return Limit::perMinute(config('dory.rate_limits.queue_public'))->by('queue-public:'.$request->ip());
        });

        // Staff actions on queues and tickets, per user.
        RateLimiter::for('queue-operations', function (Request $request) {
            return Limit::perMinute(config('dory.rate_limits.queue_operations'))
                ->by('queue-ops:'.($request->user()?->getAuthIdentifier() ?: $request->ip()));
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
