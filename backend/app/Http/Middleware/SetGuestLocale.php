<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Language of the guest web pages: ?lang=ar|en (remembered in a cookie),
 * then the remembered choice, then the browser's Accept-Language.
 */
class SetGuestLocale
{
    public const SUPPORTED = ['en', 'ar'];

    public const COOKIE = 'dory_lang';

    public function handle(Request $request, Closure $next): Response
    {
        $chosen = $request->query('lang');
        $remembered = $request->cookie(self::COOKIE);

        if (is_string($chosen) && in_array($chosen, self::SUPPORTED, true)) {
            $locale = $chosen;
            Cookie::queue(self::COOKIE, $locale, 60 * 24 * 365, '/', null, $request->isSecure(), true, false, 'lax');
        } elseif (is_string($remembered) && in_array($remembered, self::SUPPORTED, true)) {
            $locale = $remembered;
        } else {
            $locale = $request->getPreferredLanguage(self::SUPPORTED) ?? 'en';
        }

        App::setLocale($locale);

        return $next($request);
    }
}
