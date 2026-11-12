<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Language of the admin dashboard: ?lang=en|ar (remembered for the session),
 * then the admin's account language, then the browser's. Arabic pages are
 * right to left.
 */
class SetAdminLocale
{
    public const SUPPORTED = ['en', 'ar'];

    public function handle(Request $request, Closure $next): Response
    {
        $chosen = $request->query('lang');
        if (is_string($chosen) && in_array($chosen, self::SUPPORTED, true)) {
            $request->session()->put('admin.locale', $chosen);
        }

        $locale = $request->session()->get('admin.locale')
            ?? Auth::guard('admin')->user()?->locale
            ?? $request->getPreferredLanguage(self::SUPPORTED);

        App::setLocale(in_array($locale, self::SUPPORTED, true) ? $locale : 'en');

        return $next($request);
    }
}
