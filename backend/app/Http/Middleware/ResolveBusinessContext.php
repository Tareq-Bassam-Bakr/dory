<?php

namespace App\Http\Middleware;

use App\Exceptions\BusinessNotFoundException;
use App\Models\Business;
use App\Services\Business\CurrentBusinessContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the business named in the route (its public id) through the
 * authenticated user's own active memberships. Alias: `business.context`.
 *
 * Usage (after auth:sanctum):
 *   Route::middleware('business.context')->get('businesses/{business}/...', ...)
 *   Route::middleware('business.context:businessId')->...   // custom parameter
 *
 * Non-members get the same 404 as for a non-existent business.
 */
class ResolveBusinessContext
{
    public function __construct(private readonly CurrentBusinessContext $context) {}

    public function handle(Request $request, Closure $next, string $parameter = 'business'): Response
    {
        $value = $request->route($parameter);
        $publicId = $value instanceof Business ? $value->public_id : $value;

        if (! is_string($publicId) || $publicId === '' || $request->user() === null) {
            throw new BusinessNotFoundException;
        }

        $this->context->resolveForUser($request->user(), $publicId);

        return $next($request);
    }
}
