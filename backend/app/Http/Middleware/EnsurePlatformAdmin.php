<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Platform administration (docs/SUBSCRIPTIONS.md, "Admin API"). Only
 * accounts flagged is_platform_admin; no business role ever qualifies. Runs
 * before route bindings (bootstrap/app.php), so nothing is looked up for
 * anyone else.
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || Gate::forUser($user)->denies('platform.admin')) {
            return ApiResponse::error(__('api.errors.forbidden'), 403, 'forbidden');
        }

        return $next($request);
    }
}
