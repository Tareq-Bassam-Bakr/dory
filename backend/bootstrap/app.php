<?php

use App\Exceptions\ApiException;
use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\ResolveBusinessContext;
use App\Http\Middleware\SetSecurityHeaders;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Every request (including 404s) gets a correlation id and security headers.
        $middleware->prepend(AssignRequestId::class);
        $middleware->append(SetSecurityHeaders::class);

        $middleware->api(prepend: [ForceJsonResponse::class]);
        $middleware->throttleApi('api');

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'business.context' => ResolveBusinessContext::class,
        ]);

        // Suspended accounts are rejected right after authentication, before
        // route bindings look up any tenant data.
        $middleware->appendToPriorityList(AuthenticatesRequests::class, EnsureUserIsActive::class);
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
