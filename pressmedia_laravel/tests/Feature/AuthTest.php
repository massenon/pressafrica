<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Correct-Horse-9!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    private function user(string $role = 'admin'): User
    {
        return User::factory()->create(['email' => "$role@example.test", 'password' => self::PASSWORD])->assignRole($role);
    }

    public function test_login_page_renders_for_guests_and_admin_redirects_to_it(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('Sign in');
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_staff_signs_in_and_out_and_both_are_journaled(): void
    {
        $user = $this->user();

        $this->post('/admin/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);
        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest();

        $this->assertSame(['login', 'logout'], AuditLog::orderBy('id')->pluck('action')->all());
        $this->assertSame('admin', AuditLog::first()->role);
    }

    public function test_wrong_password_and_unknown_account_get_the_same_answer_and_are_journaled(): void
    {
        $user = $this->user();

        $wrong = $this->from('/admin/login')->post('/admin/login', ['email' => $user->email, 'password' => 'nope']);
        $unknown = $this->from('/admin/login')->post('/admin/login', ['email' => 'ghost@example.test', 'password' => 'nope']);

        $wrong->assertSessionHasErrors(['email' => __('auth.failed')]);
        $unknown->assertSessionHasErrors(['email' => __('auth.failed')]);
        $this->assertGuest();
        $this->assertSame(2, AuditLog::where('action', 'login_failed')->count());
    }

    public function test_login_is_rate_limited_per_account(): void
    {
        $user = $this->user();
        foreach (range(1, 5) as $_) {
            $this->post('/admin/login', ['email' => $user->email, 'password' => 'nope']);
        }

        // Even the right password is refused while locked out.
        $this->post('/admin/login', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->postJson('/api/tokens', ['email' => $user->email, 'password' => self::PASSWORD, 'device_name' => 'cli'])->assertStatus(429);
        $this->assertGuest();
        $this->assertTrue(AuditLog::where('action', 'lockout')->exists());
    }

    public function test_successful_sign_ins_never_lock_the_account(): void
    {
        $user = $this->user();
        foreach (range(1, 8) as $_) {
            $this->post('/admin/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertRedirect('/admin');
            $this->post('/admin/logout');
        }
        $this->assertFalse(AuditLog::where('action', 'lockout')->exists());
    }

    public function test_api_token_is_issued_used_and_revoked(): void
    {
        $user = $this->user('author');

        $this->postJson('/api/tokens', ['email' => $user->email, 'password' => 'nope', 'device_name' => 'cli'])->assertUnprocessable();
        $token = $this->postJson('/api/tokens', ['email' => $user->email, 'password' => self::PASSWORD, 'device_name' => 'cli'])
            ->assertCreated()->json('token');

        $this->withToken($token)->getJson('/api/user')->assertOk()->assertJsonPath('email', $user->email)->assertJsonMissingPath('password');
        $this->withToken($token)->deleteJson('/api/tokens/current')->assertNoContent();
        $this->assertSame(0, $user->tokens()->count());

        $this->assertSame(['login_failed', 'token_create', 'token_revoke'], AuditLog::orderBy('id')->pluck('action')->all());
    }

    public function test_session_cookie_is_http_only(): void
    {
        $this->assertTrue(config('session.http_only'));
    }
}
