<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\LoginThrottle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Staff sign-in to the admin console (session + CSRF). Failed attempts lock the account (LoginThrottle); the "login" route limiter caps requests per IP;
 * Login/Failed/Logout events are journaled by AppServiceProvider.
 */
class LoginController extends Controller
{
    public function create(): View
    {
        return view('admin.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        LoginThrottle::ensureNotLocked($request);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            LoginThrottle::failed($request);
            // Same message for unknown account and wrong password, so the form does not reveal which e-mails exist.
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }
        LoginThrottle::succeeded($request);

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
