<?php

namespace App\Http\Middleware;

use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Settings > maintenance: visitors get the 503 page; staff signed in and the listed IP addresses still see the site.
 * The admin console and its login are never closed (not in this middleware's routes).
 */
class MaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Settings::get('maintenance') && ! $request->user()) {
            $allowed = array_filter(array_map('trim', preg_split('/[\s,]+/', (string) Settings::get('maintenanceIps'))));
            if (! in_array($request->ip(), $allowed, true)) {
                abort(503);
            }
        }

        return $next($request);
    }
}
