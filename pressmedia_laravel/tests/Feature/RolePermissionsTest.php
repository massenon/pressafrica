<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->admin = User::factory()->create()->assignRole('admin');
    }

    public function test_admin_sees_the_matrix_with_current_grants(): void
    {
        $this->actingAs($this->admin)->get('/admin/roles?role=author')->assertOk()
            ->assertSee('value="posts.create" aria-label="author: posts.create" checked', false)
            ->assertSee('value="posts.publish" aria-label="author: posts.publish"', false);
    }

    /** PRD phase 1 exit criterion: an admin changes a role's rights and the change appears in the audit log. */
    public function test_changing_a_roles_permissions_is_saved_and_journaled(): void
    {
        $author = Role::findByName('author');
        $perms = $author->permissions->pluck('name')->reject(fn ($p) => $p === 'ai.create')->push('posts.publish')->all();

        $this->actingAs($this->admin)->put('/admin/roles/author', ['permissions' => $perms])->assertRedirect('/admin/roles?role=author');

        $author->refresh();
        $this->assertTrue($author->hasPermissionTo('posts.publish'));
        $this->assertFalse($author->hasPermissionTo('ai.create'));
        $entry = AuditLog::where('action', 'update')->sole();
        $this->assertSame(['users', 'role:author', '+posts.publish -ai.create', 'admin'], [$entry->module, $entry->target, $entry->detail, $entry->role]);

        $this->get('/admin/audit')->assertSee('+posts.publish -ai.create');
    }

    public function test_saving_without_change_writes_no_entry(): void
    {
        $perms = Role::findByName('author')->permissions->pluck('name')->all();
        $this->actingAs($this->admin)->put('/admin/roles/author', ['permissions' => $perms]);
        $this->assertFalse(AuditLog::where('action', 'update')->exists());
    }

    public function test_admin_role_is_locked(): void
    {
        $this->actingAs($this->admin)->put('/admin/roles/admin', ['permissions' => []])->assertForbidden();
        $this->actingAs($this->admin)->delete('/admin/roles/admin')->assertForbidden();
        $this->assertTrue(Role::findByName('admin')->hasPermissionTo('users.edit'));
    }

    public function test_unknown_permissions_are_rejected(): void
    {
        $this->actingAs($this->admin)->put('/admin/roles/author', ['permissions' => ['posts.hack']])->assertSessionHasErrors('permissions.0');
    }

    public function test_a_non_admin_cannot_grant_rights_it_does_not_hold(): void
    {
        Role::create(['name' => 'hr', 'guard_name' => 'web'])->syncPermissions(['users.view', 'users.edit']);
        $hr = User::factory()->create()->assignRole('hr');

        $this->actingAs($hr)->put('/admin/roles/hr', ['permissions' => ['users.view', 'users.edit', 'audit.view']])->assertSessionHasErrors('permissions');
        $this->assertFalse($hr->fresh()->can('audit.view'));
    }

    public function test_roles_are_created_and_deleted_with_journal_entries(): void
    {
        $this->actingAs($this->admin)->post('/admin/roles', ['name' => 'reviewer'])->assertRedirect('/admin/roles?role=reviewer');
        $this->actingAs($this->admin)->post('/admin/roles', ['name' => 'Bad Name'])->assertSessionHasErrors('name');
        $this->actingAs($this->admin)->delete('/admin/roles/reviewer')->assertRedirect('/admin/roles');

        $this->assertNull(Role::where('name', 'reviewer')->first());
        $this->assertSame(['create', 'delete'], AuditLog::where('target', 'role:reviewer')->orderBy('id')->pluck('action')->all());
    }

    public function test_a_role_with_users_cannot_be_deleted(): void
    {
        User::factory()->create()->assignRole('moderator');
        $this->actingAs($this->admin)->delete('/admin/roles/moderator')->assertSessionHasErrors('role');
        $this->assertNotNull(Role::where('name', 'moderator')->first());
    }

    public function test_editors_can_view_but_not_change_roles(): void
    {
        $editor = User::factory()->create()->assignRole('editor');
        $this->actingAs($editor)->get('/admin/roles')->assertOk()->assertDontSee('Save permissions');
        $this->actingAs($editor)->put('/admin/roles/author', ['permissions' => []])->assertForbidden();
        $this->actingAs($editor)->post('/admin/roles', ['name' => 'x'])->assertForbidden();

        $author = User::factory()->create()->assignRole('author');
        $this->actingAs($author)->get('/admin/roles')->assertForbidden();
    }
}
