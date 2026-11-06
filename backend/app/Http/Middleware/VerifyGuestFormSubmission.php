<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cross-site request protection for the guest web forms, which cannot use
 * Laravel's session CSRF token (the backend keeps no sessions):
 *
 * - Sec-Fetch-Site, when the browser sends it, must be same-origin or none;
 * - Origin, when sent and not "null", must be this site.
 *
 * The join form also carries a double-submit nonce, and the cancel form
 * relies on the SameSite=Strict ticket cookie (GuestTicketCookies).
 */
class VerifyGuestFormSubmission
{
    public function handle(Request $request, Closure $next): Response
    {
        $site = $request->headers->get('Sec-Fetch-Site');
        $origin = $request->headers->get('Origin');

        // Browsers send "Origin: null" on form posts from pages with
        // Referrer-Policy: no-referrer (ours), so "null" proves nothing
        // either way; a real foreign origin is always refused.
        $crossSite = ($site !== null && ! in_array($site, ['same-origin', 'none'], true))
            || ($origin !== null && $origin !== 'null' && rtrim($origin, '/') !== $request->getSchemeAndHttpHost());

        if ($crossSite) {
            return response()->view('guest.message', [
                'title' => __('guest.expired_title'),
                'body' => __('guest.expired_body'),
                'retryUrl' => $request->url(),
            ], 419)->header('Cache-Control', 'no-store');
        }

        return $next($request);
    }
}
