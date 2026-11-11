<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
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
        if (! ($request->user()?->isPlatformAdmin() ?? false)) {
            return ApiResponse::error(__('api.errors.forbidden'), 403, 'forbidden');
        }

        return $next($request);
    }
}
