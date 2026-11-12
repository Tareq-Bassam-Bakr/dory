<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headers of every admin dashboard page (docs/ADMIN_DASHBOARD.md,
 * "Security"), on top of the global ones (SetSecurityHeaders):
 *
 * - a strict Content Security Policy: scripts and styles only from DORY's
 *   own files, no inline code, no framing, forms post to DORY only;
 * - never cached, never indexed: the pages show personal and billing data.
 */
class AdminResponseHeaders
{
    public const CONTENT_SECURITY_POLICY = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; "
        ."font-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'; object-src 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', self::CONTENT_SECURITY_POLICY);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
