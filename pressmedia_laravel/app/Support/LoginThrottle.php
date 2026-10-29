<?php

namespace App\Support;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Per-account lockout that counts failed sign-ins only (Settings > Security: maxAttempts per lockMinutes).
 * A route throttle would also count successful sign-ins and lock out staff who simply log in often.
 * The per-IP limit (many accounts from one address) stays on the route ("login" limiter).
 */
class LoginThrottle
{
    public static function key(Request $request): string
    {
        return 'login-fail:'.Str::lower((string) $request->input('email')).'|'.$request->ip();
    }

    /** @throws ValidationException when the account is locked (429) */
    public static function ensureNotLocked(Request $request): void
    {
        $key = self::key($request);
        if (RateLimiter::tooManyAttempts($key, max(1, (int) Settings::get('maxAttempts')))) {
            event(new Lockout($request));
            throw ValidationException::withMessages(['email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)])])->status(429);
        }
    }

    public static function failed(Request $request): void
    {
        RateLimiter::hit(self::key($request), 60 * max(1, (int) Settings::get('lockMinutes')));
    }

    public static function succeeded(Request $request): void
    {
        RateLimiter::clear(self::key($request));
    }
}
