<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Ends the session of an account deactivated while signed in, on its next request. */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->disabled_at) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => __('auth.failed')]);
        }

        return $next($request);
    }
}
