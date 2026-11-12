<?php

use App\Exceptions\ApiException;
use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\AdminResponseHeaders;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\AuthenticateAdmin;
use App\Http\Middleware\ConfigureAdminSession;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureTicketAccessToken;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\ResolveBusinessContext;
use App\Http\Middleware\SetAdminLocale;
use App\Http\Middleware\SetGuestLocale;
use App\Http\Middleware\SetSecurityHeaders;
use App\Http\Middleware\VerifyGuestFormSubmission;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// Stack traces must never carry argument values (passwords, tokens) into
// logs, whatever php.ini says. PHP's development default records them.
ini_set('zend.exception_ignore_args', '1');

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // The platform admin dashboard (docs/ADMIN_DASHBOARD.md).
        then: function (): void {
            Route::middleware('admin')->prefix('admin')->name('admin.')->group(__DIR__.'/../routes/admin.php');
        },
    )
    // Private channel authorization for Reverb: POST /api/v1/broadcasting/auth
    // with the Sanctum token (docs/BROADCAST_CHANNELS.md).
    ->withBroadcasting(__DIR__.'/../routes/channels.php', [
        'prefix' => 'api/v1',
        'middleware' => ['api', 'auth:sanctum', 'active'],
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // Every request (including 404s) gets a correlation id and security headers.
        $middleware->prepend(AssignRequestId::class);
        $middleware->append(SetSecurityHeaders::class);

        $middleware->api(prepend: [ForceJsonResponse::class]);
        $middleware->throttleApi('api');

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'business.context' => ResolveBusinessContext::class,
            'ticket.token' => EnsureTicketAccessToken::class,
            'guest.locale' => SetGuestLocale::class,
            'guest.form' => VerifyGuestFormSubmission::class,
            'platform.admin' => EnsurePlatformAdmin::class,
            'admin.auth' => AuthenticateAdmin::class,
        ]);

        // The admin dashboard: the web stack with its own session settings
        // (ConfigureAdminSession, before the session starts) and language
        // (before the admin check, so its messages are in that language).
        $middleware->group('admin', [
            AdminResponseHeaders::class,
            ConfigureAdminSession::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            SetAdminLocale::class,
            ShareErrorsFromSession::class,
            PreventRequestForgery::class,
            SubstituteBindings::class,
        ]);
        $middleware->prependToPriorityList(EncryptCookies::class, ConfigureAdminSession::class);

        // Suspended accounts are rejected right after authentication, before
        // route bindings look up any tenant data.
        $middleware->appendToPriorityList(AuthenticatesRequests::class, EnsureUserIsActive::class);
        // Platform administration: refused before bindings look anything up.
        $middleware->appendToPriorityList(EnsureUserIsActive::class, EnsurePlatformAdmin::class);
        $middleware->appendToPriorityList(StartSession::class, SetAdminLocale::class);
        $middleware->appendToPriorityList(SetAdminLocale::class, AuthenticateAdmin::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Expected client errors (bad credentials, tenant access denied, ...)
        // are not server faults, so they are not logged as errors.
        $exceptions->dontReport([ApiException::class]);

        $exceptions->render(
            fn (Throwable $e, Request $request) => app(ApiExceptionRenderer::class)($e, $request),
        );
    })->create();
