<?php

namespace App\Providers;

use App\Contracts\InsightNarrator;
use App\Contracts\InvitationNotifier;
use App\Contracts\PaymentGateway;
use App\Contracts\WaitTimeEstimator;
use App\Events\QueueActivity;
use App\Exceptions\BusinessNotFoundException;
use App\Exceptions\NotificationException;
use App\Models\Business;
use App\Models\PushDevice;
use App\Models\Queue;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Realtime\QueueActivityRelay;
use App\Services\Billing\PlanCatalog;
use App\Services\Business\CurrentBusinessContext;
use App\Services\Business\TenantResolver;
use App\Services\Discovery\PublicCatalogResolver;
use App\Services\Intelligence\SmartWaitTimeEstimator;
use App\Services\Intelligence\TemplateInsightNarrator;
use App\Services\Invitations\MailInvitationNotifier;
use App\Services\Payments\MastercardGateway;
use App\Services\Payments\UnavailablePaymentGateway;
use App\Services\Push\DisabledPushSender;
use App\Services\Push\FirebasePushSender;
use App\Services\Push\PushSender;
use App\Services\Queues\HistoricalWaitTimeEstimator;
use App\Services\Queues\PublicQueueResolver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Kreait\Firebase\Contract\Messaging;

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
        // Since Phase 07 the smart estimator, built on the historical profile
        // (docs/AI_ARCHITECTURE.md).
        $this->app->scoped(HistoricalWaitTimeEstimator::class);
        $this->app->scoped(WaitTimeEstimator::class, SmartWaitTimeEstimator::class);

        // Who words business insights. Only the built-in templates ship; an
        // LLM narrator would be selected here with DORY_AI_NARRATOR.
        $this->app->bind(InsightNarrator::class, fn () => match (config('dory.intelligence.narrator')) {
            default => new TemplateInsightNarrator,
        });

        // Plans (docs/SUBSCRIPTIONS.md): one catalogue per request or job, so
        // an admin's change of a plan is seen by every service at once.
        $this->app->scoped(PlanCatalog::class);

        // Online payments (docs/PAYMENTS.md). Without a configured provider,
        // checkouts are refused and payments are recorded by platform admins.
        $this->app->bind(PaymentGateway::class, function (): PaymentGateway {
            $provider = (string) config('billing.provider');

            return match ($provider) {
                'mastercard' => new MastercardGateway((array) config('billing.providers.mastercard')),
                default => new UnavailablePaymentGateway($provider === '' ? 'none' : $provider),
            };
        });

        // Push notifications (docs/PUSH_NOTIFICATIONS.md): Firebase only when
        // switched on and a credentials file exists. The Firebase SDK is not
        // even built otherwise.
        $this->app->singleton(PushSender::class, function ($app): PushSender {
            $credentials = (string) config('firebase.projects.app.credentials');

            return config('dory.push.enabled') && $credentials !== '' && is_readable($credentials)
                ? new FirebasePushSender($app->make(Messaging::class), (string) config('dory.push.android_channel_id'))
                : new DisabledPushSender;
        });
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

        // Committed queue changes: broadcasts and notifications
        // (docs/REALTIME_ARCHITECTURE.md).
        Event::listen(QueueActivity::class, QueueActivityRelay::class);
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
            'publicBranch', 'favoriteBusiness', 'device', 'checkout', 'billingBusiness',
        ];
        foreach ($publicIds as $parameter) {
            Route::pattern($parameter, '[0-9A-Za-z]{26}');
        }
        Route::pattern('invitationToken', '[A-Za-z0-9]{64}');
        // Notification ids are UUIDs.
        Route::pattern('notification', '[0-9a-fA-F-]{36}');
        Route::pattern('publicQueueToken', '[A-Za-z0-9]{40}');
        // A slug or a public id.
        Route::pattern('publicBusiness', '[A-Za-z0-9-]{1,160}');
        Route::pattern('billingPlan', '[a-z][a-z0-9_-]{1,39}');
        Route::pattern('provider', '[a-z]{2,30}');

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

        // Public catalogue (no membership needed; only public records).
        $catalog = fn (): PublicCatalogResolver => $this->app->make(PublicCatalogResolver::class);
        Route::bind('publicBusiness', fn (string $value) => $catalog()->business($value));
        Route::bind('publicBranch', fn (string $value) => $catalog()->branch($value));
        Route::bind('favoriteBusiness', fn (string $value) => $catalog()->favoriteTarget($value));

        // Platform administration (docs/SUBSCRIPTIONS.md): any business, for
        // platform admins only (the platform.admin middleware ran already;
        // checked again here, so a binding never reveals a business).
        Route::bind('billingBusiness', function (string $value) use ($user): Business {
            if (! ($user()?->isPlatformAdmin() ?? false)) {
                throw new BusinessNotFoundException;
            }

            return Business::query()->withTrashed()->where('public_id', $value)->first()
                ?? throw new BusinessNotFoundException;
        });
        Route::bind('billingPlan', fn (string $value) => SubscriptionPlan::query()->with('prices')
            ->where('code', $value)->firstOrFail());

        // The signed-in user's own push devices and notifications only.
        Route::bind('device', fn (string $value) => PushDevice::query()
            ->where('public_id', $value)
            ->where('user_id', $user()?->getKey())
            ->first() ?? throw NotificationException::deviceNotFound());
        Route::bind('notification', fn (string $value) => $user()?->notifications()->whereKey($value)->first()
            ?? throw NotificationException::notificationNotFound());
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
            $queue = (string) ($route?->originalParameter('joinableQueue')
                ?? $route?->originalParameter('publicQueueToken')
                ?? $route?->originalParameter('guestToken')
                ?? '');
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

        // Public discovery and search, per IP.
        RateLimiter::for('discovery', function (Request $request) {
            return Limit::perMinute(config('dory.rate_limits.discovery'))->by('discovery:'.$request->ip());
        });

        // Insights read up to two months of tickets: per user.
        RateLimiter::for('insights', function (Request $request) {
            return Limit::perMinute(30)->by('insights:'.($request->user()?->getAuthIdentifier() ?: $request->ip()));
        });

        // Starting and checking online payments: each start opens a session
        // with the payment provider. Per user.
        RateLimiter::for('billing-checkout', function (Request $request) {
            return Limit::perMinute(config('dory.rate_limits.billing_checkout', 10))
                ->by('billing-checkout:'.($request->user()?->getAuthIdentifier() ?: $request->ip()));
        });

        // Payment provider notifications, per IP.
        RateLimiter::for('billing-webhooks', function (Request $request) {
            return Limit::perMinute(config('dory.rate_limits.billing_webhooks', 120))->by('billing-webhooks:'.$request->ip());
        });

        // The payment and return pages, per IP.
        RateLimiter::for('billing-pages', function (Request $request) {
            return Limit::perMinute(config('dory.rate_limits.billing_pages', 30))->by('billing-pages:'.$request->ip());
        });

        // Push device registration, per user (or IP for guests).
        RateLimiter::for('devices', function (Request $request) {
            return Limit::perMinute(config('dory.rate_limits.devices'))
                ->by('devices:'.($request->user()?->getAuthIdentifier() ?: $request->ip()));
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
