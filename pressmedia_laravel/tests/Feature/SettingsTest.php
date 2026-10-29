<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->admin = User::factory()->create()->assignRole('admin');
    }

    private function form(array $overrides = []): array
    {
        // What the browser posts: every non-secret field, checkboxes only when checked.
        $values = collect(Settings::all())
            ->reject(fn ($v, $k) => Settings::DEFINITIONS[$k][1] === 'secret')
            ->reject(fn ($v, $k) => Settings::DEFINITIONS[$k][1] === 'bool' && ! $v)
            ->map(fn ($v) => is_bool($v) ? '1' : $v);

        return array_merge($values->all(), $overrides);
    }

    public function test_defaults_match_the_prototype(): void
    {
        $this->assertSame('Africa Global Press', Settings::get('siteTitle'));
        $this->assertSame('fr', Settings::get('defaultLang'));
        $this->assertSame(12, Settings::get('minPassword'));
    }

    public function test_saving_changes_is_journaled_with_field_names_only(): void
    {
        $form = $this->form(['siteTitle' => 'AGP Daily', 'aiOpenaiKey' => 'sk-secret-123']);
        unset($form['popup']);   // unchecking a box

        $this->actingAs($this->admin)->put('/admin/settings', $form)->assertRedirect('/admin/settings');

        $this->assertSame('AGP Daily', Settings::get('siteTitle'));
        $this->assertFalse(Settings::get('popup'));
        $this->assertSame('sk-secret-123', Settings::get('aiOpenaiKey'));
        $this->assertStringNotContainsString('sk-secret-123', Setting::find('aiOpenaiKey')->value, 'secrets are encrypted at rest');

        $entry = AuditLog::where('module', 'settings')->sole();
        $this->assertSame('fields: siteTitle, aiOpenaiKey, popup', $entry->detail);
        $this->assertStringNotContainsString('sk-secret', $entry->detail);
    }

    public function test_a_stored_secret_is_kept_when_left_blank_and_never_shown(): void
    {
        Settings::save(['aiGeminiKey' => 'gm-key-456']);
        $this->actingAs($this->admin)->put('/admin/settings', $this->form());
        $this->assertSame('gm-key-456', Settings::get('aiGeminiKey'));
        $this->assertFalse(AuditLog::where('module', 'settings')->exists(), 'no change, no entry');

        $this->get('/admin/settings')->assertOk()->assertDontSee('gm-key-456');
    }

    public function test_invalid_values_are_rejected(): void
    {
        $this->actingAs($this->admin)->put('/admin/settings', $this->form(['colorPrimary' => 'red', 'minPassword' => 4, 'defaultLang' => 'de']))
            ->assertSessionHasErrors(['colorPrimary', 'minPassword', 'defaultLang']);
    }

    public function test_editors_view_but_cannot_save(): void
    {
        $editor = User::factory()->create()->assignRole('editor');
        $this->actingAs($editor)->get('/admin/settings')->assertOk()->assertDontSee('Save settings');
        $this->actingAs($editor)->put('/admin/settings', $this->form(['siteTitle' => 'X']))->assertForbidden();
    }

    public function test_security_settings_drive_password_rules_and_login_throttle(): void
    {
        Settings::save(['minPassword' => 16, 'maxAttempts' => 3]);
        $this->assertTrue(validator(['p' => 'Short-Pass-1!'], ['p' => Password::defaults()])->fails());

        $user = User::factory()->create(['password' => 'Correct-Horse-9!']);
        foreach (range(1, 3) as $_) {
            $this->post('/admin/login', ['email' => $user->email, 'password' => 'nope']);
        }
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'Correct-Horse-9!'])->assertStatus(429);
    }
}
