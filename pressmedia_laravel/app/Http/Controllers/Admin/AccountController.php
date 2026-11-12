<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Any staff member's own account: display name and password, the current password being required for both. */
class AccountController extends Controller
{
    public function edit(): View
    {
        return view('admin.account');
    }

    public function update(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'current_password' => ['required', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);
        $user = $request->user();
        $user->name = $d['name'];
        if (filled($d['password'] ?? null)) {
            $user->password = $d['password'];
        }
        $user->save();
        AuditLog::record('update', 'users', 'user:'.$user->id, filled($d['password'] ?? null) ? 'own password changed' : 'own profile');
        // Other browsers signed in with the old password are signed out by the auth.session middleware.
        $request->session()->regenerate();

        return back()->with('status', 'Your account is saved.');
    }
}
