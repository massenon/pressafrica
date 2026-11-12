<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Role/permission matrix, as in the prototype (assets/js/admin-users.js): admin is a system role with full access,
 * other roles are edited, created and deleted here. Every change is journaled with the permissions added and removed.
 */
class RoleController extends Controller
{
    /** Locked so nobody can remove the last way back into this screen. */
    public const SYSTEM_ROLE = 'admin';

    public function index(Request $request): View
    {
        $roles = Role::with('permissions')->withCount('users')->orderBy('id')->get();
        $current = $roles->firstWhere('name', $request->query('role')) ?? $roles->first();

        // Rows are modules, columns actions, both derived from the "<module>.<action>" names.
        $matrix = Permission::orderBy('id')->pluck('name')
            ->groupBy(fn ($p) => Str::before($p, '.'))
            ->map(fn ($perms) => $perms->mapWithKeys(fn ($p) => [Str::after($p, '.') => $p]));

        return view('admin.roles.index', [
            'roles' => $roles,
            'current' => $current,
            'matrix' => $matrix,
            'actions' => $matrix->flatMap(fn ($a) => $a->keys())->unique()->values(),
            'granted' => $current?->permissions->pluck('name')->all() ?? [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_-]*$/', Rule::unique('roles', 'name')]]);
        Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        AuditLog::record('create', 'users', 'role:'.$data['name']);

        return redirect()->route('admin.roles.index', ['role' => $data['name']])->with('status', 'Role created.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_if($role->name === self::SYSTEM_ROLE, 403, 'Administrator is a system role and always has full access.');

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ]);
        $before = $role->permissions->pluck('name');
        $after = collect($data['permissions'] ?? [])->unique()->values();

        $added = $after->diff($before)->sort()->values();
        $removed = $before->diff($after)->sort()->values();
        // Without this, any role holding users.edit could grant itself audit.view or backups.* (privilege escalation).
        $beyondOwn = $added->reject(fn ($p) => $request->user()->can($p));
        if ($beyondOwn->isNotEmpty()) {
            return back()->withErrors(['permissions' => 'You cannot grant permissions you do not hold: '.$beyondOwn->implode(', ')]);
        }
        if ($added->isEmpty() && $removed->isEmpty()) {
            return back()->with('status', 'No change.');
        }

        $role->syncPermissions($after->all());
        AuditLog::record('update', 'users', 'role:'.$role->name, trim(
            ($added->isNotEmpty() ? '+'.$added->implode(', +') : '').' '.($removed->isNotEmpty() ? '-'.$removed->implode(', -') : '')
        ));

        return redirect()->route('admin.roles.index', ['role' => $role->name])->with('status', 'Permissions saved.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_if($role->name === self::SYSTEM_ROLE, 403);
        if ($role->users()->exists()) {
            return back()->withErrors(['role' => 'Reassign the users of this role first.']);
        }

        $role->delete();
        AuditLog::record('delete', 'users', 'role:'.$role->name);

        return redirect()->route('admin.roles.index')->with('status', 'Role deleted.');
    }
}
