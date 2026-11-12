<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locale comes from the URL prefix (/fr/..., /en/...). The visitor's last choice is remembered in a cookie so the
 * root URL can send them back to it (INV-07); the browser's Accept-Language is deliberately never used.
 */
class SetLocale
{
    public const LOCALES = ['fr', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');
        App::setLocale($locale);
        URL::defaults(['locale' => $locale]);
        $request->route()->forgetParameter('locale');   // controllers do not need it as an argument

        if ($request->cookie('locale') !== $locale) {
            // Built in full: named arguments through Cookie::queue() landed in the wrong slot ("path=lax" in production).
            Cookie::queue(Cookie::make('locale', $locale, 60 * 24 * 365, '/', null, null, true, false, 'lax'));
        }

        return $next($request);
    }
}
