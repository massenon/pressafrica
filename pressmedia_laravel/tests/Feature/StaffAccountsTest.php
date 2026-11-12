<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAccountsTest extends TestCase
{
    use RefreshDatabase;

    private const PW = 'Nouveau-Mot2passe!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    public function test_an_admin_creates_edits_and_deactivates_a_staff_account(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertSee($admin->email);

        $this->post('/admin/users', ['name' => 'Awa', 'email' => 'awa@pressafrica.media', 'role' => 'author', 'password' => self::PW, 'password_confirmation' => self::PW])->assertRedirect();
        $awa = User::where('email', 'awa@pressafrica.media')->sole();
        $this->assertTrue($awa->hasRole('author'));
        $this->get('/admin/users/create')->assertOk();
        $this->get("/admin/users/{$awa->id}/edit")->assertOk()->assertSee('Deactivate account');

        $this->put("/admin/users/{$awa->id}", ['name' => 'Awa D.', 'email' => $awa->email, 'role' => 'editor', 'password' => '', 'password_confirmation' => ''])->assertRedirect();
        $this->assertTrue($awa->fresh()->hasRole('editor'));
        $this->assertSame('Awa D.', $awa->fresh()->name);

        $this->post("/admin/users/{$awa->id}/toggle")->assertRedirect();
        $this->assertNotNull($awa->fresh()->disabled_at);
        $this->assertEqualsCanonicalizing(['create', 'update', 'disable'], AuditLog::where('module', 'users')->pluck('action')->all());

        // A deactivated account cannot sign in, and its open session ends on the next request.
        auth()->logout();
        $this->post('/admin/login', ['email' => $awa->email, 'password' => self::PW])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($awa->fresh())->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_the_last_administrator_cannot_be_demoted_or_deactivated_and_nobody_deactivates_themselves(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        $this->actingAs($admin)->put("/admin/users/{$admin->id}", ['name' => $admin->name, 'email' => $admin->email, 'role' => 'editor'])->assertStatus(422);
        $this->post("/admin/users/{$admin->id}/toggle")->assertStatus(422);
        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_an_author_cannot_manage_accounts_but_changes_their_own_password(): void
    {
        $author = User::factory()->create(['password' => 'password'])->assignRole('author');
        $this->actingAs($author)->get('/admin/users')->assertForbidden();
        $this->get('/admin/account')->assertOk();

        $this->put('/admin/account', ['name' => $author->name, 'current_password' => 'wrong', 'password' => self::PW, 'password_confirmation' => self::PW])->assertSessionHasErrors('current_password');
        $this->put('/admin/account', ['name' => $author->name, 'current_password' => 'password', 'password' => self::PW, 'password_confirmation' => self::PW])->assertSessionHasNoErrors();
        $this->assertTrue(password_verify(self::PW, $author->fresh()->password));
    }
}
