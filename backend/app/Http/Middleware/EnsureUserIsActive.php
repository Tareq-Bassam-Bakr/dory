<?php

namespace App\Http\Middleware;

use App\Exceptions\AccountInactiveException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects requests from suspended or deactivated accounts, even when they
 * still hold a valid token. Alias: `active`. Use after `auth:sanctum`.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->isActive()) {
            throw new AccountInactiveException;
        }

        return $next($request);
    }
}
