<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Support\Settings;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Length and complexity come from Settings > Security (owner sets the rule; default 12 + complexity).
        // The breach check calls an external API, so it runs in production only.
        Password::defaults(fn () => Password::min(max(8, (int) Settings::get('minPassword')))
            ->when(Settings::get('passComplexity'), fn ($rule) => $rule->letters()->mixedCase()->numbers()->symbols())
            ->when(app()->isProduction(), fn ($rule) => $rule->uncompromised()));

        // Per account and per IP: the first stops guessing one password (maxAttempts per lockMinutes, from Settings),
        // the second stops spraying many accounts.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinutes(max(1, (int) Settings::get('lockMinutes')), max(1, (int) Settings::get('maxAttempts')))
                ->by(Str::lower((string) $request->input('email')).'|'.$request->ip())
                ->response(fn () => $this->lockedOut($request)),
            Limit::perMinute(20)->by('ip:'.$request->ip())
                ->response(fn () => $this->lockedOut($request)),
        ]);

        Event::listen(Login::class, fn (Login $e) => AuditLog::record('login', 'auth', $e->user->email, null, $e->user));
        Event::listen(Logout::class, fn (Logout $e) => $e->user && AuditLog::record('logout', 'auth', $e->user->email, null, $e->user));
        Event::listen(Failed::class, fn (Failed $e) => AuditLog::record('login_failed', 'auth', $e->credentials['email'] ?? null, null, null));
        Event::listen(Lockout::class, fn (Lockout $e) => AuditLog::record('lockout', 'auth', $e->request->input('email'), null, null));
    }

    private function lockedOut(Request $request)
    {
        event(new Lockout($request));

        return $request->expectsJson()
            ? response()->json(['message' => __('auth.throttle', ['seconds' => 60])], 429)
            : back()->withInput($request->only('email'))->withErrors(['email' => __('auth.throttle', ['seconds' => 60])])->setStatusCode(429);
    }
}
