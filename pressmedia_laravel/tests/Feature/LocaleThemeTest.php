<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocaleThemeTest extends TestCase
{
    public function test_root_opens_in_french_whatever_the_browser_language(): void
    {
        $this->get('/', ['Accept-Language' => 'en-US,en;q=0.9'])->assertRedirect('/fr');
    }

    public function test_root_follows_the_remembered_choice(): void
    {
        $this->withUnencryptedCookie('locale', 'en')->get('/')->assertRedirect('/en');
        $this->withUnencryptedCookie('locale', 'de')->get('/')->assertRedirect('/fr');
    }

    public function test_locale_prefix_sets_language_and_remembers_it(): void
    {
        $this->get('/en')->assertOk()->assertSee('<html lang="en"', false)->assertSee('Français')->assertCookie('locale', 'en', false);
        $this->get('/fr')->assertOk()->assertSee('<html lang="fr"', false)->assertSee('English');
        $this->get('/de')->assertNotFound();
    }

    public function test_pages_carry_hreflang_alternates(): void
    {
        $this->get('/fr')->assertSee('hreflang="en" href="'.url('en').'"', false)->assertSee('hreflang="fr" href="'.url('fr').'"', false);
    }

    public function test_theme_is_light_by_default_and_follows_the_cookie(): void
    {
        $this->get('/fr')->assertSee('data-theme="light"', false);
        $this->withUnencryptedCookie('theme', 'dark')->get('/fr')->assertSee('data-theme="dark"', false);
        $this->withUnencryptedCookie('theme', '"><script>')->get('/fr')->assertSee('data-theme="light"', false);
        $this->get('/admin/login')->assertSee('data-theme="light"', false);
    }
}
