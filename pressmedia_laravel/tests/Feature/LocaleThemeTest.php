<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_opens_in_french_whatever_the_browser_language(): void
    {
        $this->get('/', ['Accept-Language' => 'en-US,en;q=0.9'])->assertRedirect('/fr/index.html');
    }

    public function test_root_follows_the_remembered_choice(): void
    {
        $this->withUnencryptedCookie('locale', 'en')->get('/')->assertRedirect('/en/index.html');
        $this->withUnencryptedCookie('locale', 'de')->get('/')->assertRedirect('/fr/index.html');
    }

    public function test_locale_prefix_sets_language_and_remembers_it(): void
    {
        $this->get('/en/index.html')->assertOk()->assertSee('<html lang="en"', false)->assertSee('hreflang="fr" lang="fr"', false)->assertCookie('locale', 'en', false);
        $this->get('/fr/index.html')->assertOk()->assertSee('<html lang="fr"', false)->assertSee('hreflang="en" lang="en"', false);
        $this->get('/de/index.html')->assertNotFound();
    }

    public function test_pages_carry_hreflang_alternates(): void
    {
        $this->get('/fr/index.html')->assertSee('hreflang="en" href="'.url('en/index.html').'"', false)->assertSee('hreflang="fr" href="'.url('fr/index.html').'"', false);
    }

    public function test_theme_is_light_by_default_and_the_saved_choice_wins(): void
    {
        // Public pages: server paints light, the prototype's inline script applies the saved choice; no OS preference.
        $html = $this->get('/fr/index.html')->assertSee('data-theme="light"', false)->getContent();
        $this->assertStringContainsString('localStorage.getItem("agp-theme")', $html);
        $this->assertStringNotContainsString('prefers-color-scheme', $html);

        // Admin: the theme cookie, sanitised.
        $this->get('/admin/login')->assertSee('data-theme="light"', false);
        $this->withUnencryptedCookie('theme', 'dark')->get('/admin/login')->assertSee('data-theme="dark"', false);
        $this->withUnencryptedCookie('theme', '"><script>')->get('/admin/login')->assertSee('data-theme="light"', false);
    }
}
