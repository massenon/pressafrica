<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Settings;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProductionSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_error_pages_are_branded_bilingual_and_hide_details(): void
    {
        $this->get('/fr/nope.html')->assertNotFound()->assertSee('Page introuvable')->assertSee('/fr/recherche.html', false);
        $this->get('/en/nope.html')->assertNotFound()->assertSee('Page not found')->assertSee('<html lang="en"', false);

        config(['app.debug' => false]);
        Route::get('/_boom', fn () => throw new \RuntimeException('secret stack detail'));
        $this->get('/_boom')->assertStatus(500)->assertSee('Erreur du serveur')->assertDontSee('secret stack detail');
    }

    public function test_maintenance_mode_closes_the_public_site_but_not_staff_or_listed_ips(): void
    {
        $this->seed(RolesSeeder::class);
        Settings::save(['maintenance' => true, 'maintenanceMsg' => 'Retour à 18 h.', 'maintenanceIps' => '10.0.0.9']);
        $this->get('/fr/index.html')->assertStatus(503)->assertSee('Retour à 18 h.');
        $this->get('/fr/index.html', ['REMOTE_ADDR' => '10.0.0.9'])->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])->get('/fr/index.html')->assertOk();
        $this->get('/admin/login')->assertOk();
        $this->actingAs(User::factory()->create()->assignRole('editor'))->get('/fr/index.html')->assertOk();
    }

    public function test_security_headers(): void
    {
        $this->get('/fr/index.html')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->get('/admin/login')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_the_production_template_is_safe(): void
    {
        $env = file_get_contents(base_path('.env.example'));
        foreach (['APP_ENV=production', 'APP_DEBUG=false', 'SESSION_SECURE_COOKIE=true', 'MAIL_PASSWORD='."\n"] as $line) {
            $this->assertStringContainsString($line, $env);
        }
        $this->assertTrue(config('session.http_only'));
    }
}
