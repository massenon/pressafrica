<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * Staff accounts (EX-21): create, change role or e-mail, set a new password, deactivate. No delete: erasure goes
 * through Personal data, which keeps the articles under "former author". The last active administrator cannot be
 * demoted or deactivated, so the console can never be locked out.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $users = User::with('roles')->withCount('posts')
            ->when($q !== '', fn ($w) => $w->where(fn ($w) => $w->where('name', 'like', "%$q%")->orWhere('email', 'like', "%$q%")))
            ->where('email', 'not like', '%@authors.invalid')->orderBy('name')->paginate(30)->withQueryString();

        return view('admin.users.index', compact('users', 'q'));
    }

    public function create(): View
    {
        return view('admin.users.form', ['u' => new User, 'roles' => Role::orderBy('name')->pluck('name')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $this->validated($request, new User);
        $user = User::create(['name' => $d['name'], 'email' => $d['email'], 'password' => $d['password']]);
        $user->syncRoles([$d['role']]);
        AuditLog::record('create', 'users', 'user:'.$user->id, 'role: '.$d['role']);

        return redirect()->route('admin.users.edit', $user)->with('status', 'Account created. Send the password to the person by a separate channel.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', ['u' => $user, 'roles' => Role::orderBy('name')->pluck('name')]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $d = $this->validated($request, $user);
        $before = $user->getRoleNames()->first();
        if ($before === RoleController::SYSTEM_ROLE && $d['role'] !== $before) {
            $this->keepOneAdmin($user);
        }
        $changes = array_keys(array_filter(['name' => $d['name'] !== $user->name, 'email' => $d['email'] !== $user->email, 'password' => filled($d['password'] ?? null), 'role' => $d['role'] !== $before]));
        $user->fill(['name' => $d['name'], 'email' => $d['email']]);
        if (filled($d['password'] ?? null)) {
            $user->password = $d['password'];
        }
        $user->save();
        $user->syncRoles([$d['role']]);
        if ($changes) {
            AuditLog::record('update', 'users', 'user:'.$user->id, implode(', ', $changes).($d['role'] !== $before ? ": $before → {$d['role']}" : ''));
        }

        return back()->with('status', 'Account saved.');
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, 'You cannot deactivate your own account.');
        if (! $user->disabled_at && $user->hasRole(RoleController::SYSTEM_ROLE)) {
            $this->keepOneAdmin($user);
        }
        $user->forceFill(['disabled_at' => $user->disabled_at ? null : now()])->save();
        AuditLog::record($user->disabled_at ? 'disable' : 'enable', 'users', 'user:'.$user->id);

        return back()->with('status', $user->disabled_at ? 'Account deactivated: it can no longer sign in.' : 'Account reactivated.');
    }

    private function validated(Request $request, User $user): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::exists('roles', 'name')],
            'password' => [$user->exists ? 'nullable' : 'required', 'confirmed', Password::defaults()],
        ]);
    }

    private function keepOneAdmin(User $user): void
    {
        $others = User::role(RoleController::SYSTEM_ROLE)->whereNull('disabled_at')->whereKeyNot($user->id)->count();
        abort_if($others === 0, 422, 'This is the last active administrator.');
    }
}
