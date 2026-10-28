<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    public function test_entries_cannot_be_updated(): void
    {
        AuditLog::record('create', 'posts', 'post-1');
        $this->expectException(LogicException::class);
        AuditLog::first()->update(['action' => 'delete']);
    }

    public function test_entries_cannot_be_deleted(): void
    {
        AuditLog::record('create', 'posts', 'post-1');
        $this->expectException(LogicException::class);
        AuditLog::first()->delete();
    }

    public function test_no_route_writes_to_the_audit_log(): void
    {
        $writes = collect(Route::getRoutes())->filter(fn ($r) => str_contains($r->uri(), 'audit')
            && array_intersect($r->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']));
        $this->assertCount(0, $writes);
    }

    public function test_only_administrators_read_the_audit_log_and_backups(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        AuditLog::record('update', 'settings', 'site', 'fields: title', $admin);
        $this->actingAs($admin)->get('/admin/audit')->assertOk()->assertSee('fields: title');

        foreach (['editor', 'author', 'moderator'] as $role) {
            $user = User::factory()->create()->assignRole($role);
            $this->actingAs($user)->get('/admin/audit')->assertForbidden();
            $this->assertFalse($user->can('backups.view'), "$role must not see backups");
        }
    }

    public function test_audit_output_is_escaped(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        AuditLog::record('login_failed', 'auth', '<script>alert(1)</script>');
        $this->actingAs($admin)->get('/admin/audit')->assertDontSee('<script>alert(1)</script>', false);
    }
}
