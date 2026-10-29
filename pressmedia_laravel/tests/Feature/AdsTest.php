<?php

namespace Tests\Feature;

use App\Models\AdZone;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        AdZone::ensureDefaults();
        $p = Post::create(['author_id' => User::factory()->create()->id, 'status' => 'published', 'published_at' => now()->subDay()]);
        $p->translations()->create(['locale' => 'fr', 'title' => 'Bénin', 'slug' => 'benin', 'body' => '<p>x</p>']);
    }

    private function configure(array $desktop, bool $active = true): AdZone
    {
        $this->actingAs(User::factory()->create()->assignRole('admin'));
        $zone = AdZone::where('key', 'sidebar_article')->sole();
        $this->put("/admin/ads/{$zone->id}", ['name' => 'Article sidebar', 'active' => $active ? '1' : '0',
            'desktop' => $desktop + ['enabled' => '1'], 'mobile' => ['type' => 'banner', 'enabled' => '0']])->assertRedirect('/admin/ads');
        auth()->logout();

        return $zone->fresh();
    }

    public function test_inactive_zones_keep_the_reserved_placeholder(): void
    {
        $this->get('/fr/articles/benin.html')->assertSee(__('site.ad_reserved', [], 'fr'));
        $this->assertSame(0, DB::table('ad_stats')->count());
    }

    public function test_banner_impressions_clicks_and_ctr(): void
    {
        $m = Media::create(['path' => 'media/ban.webp', 'mime' => 'image/webp', 'size' => 1, 'width' => 300, 'height' => 250, 'alt_fr' => 'Sponsor']);
        $zone = $this->configure(['type' => 'banner', 'media_id' => $m->id, 'url' => 'https://sponsor.example/offre']);

        $this->get('/fr/articles/benin.html')->assertDontSee(__('site.ad_reserved', [], 'fr'))->assertSee('rel="sponsored noopener"', false);
        $this->get("/ad/{$zone->id}/desktop")->assertRedirect('https://sponsor.example/offre');
        $this->assertSame([1, 1], [(int) DB::table('ad_stats')->sum('impressions'), (int) DB::table('ad_stats')->sum('clicks')]);

        $this->actingAs(User::factory()->create()->assignRole('editor'))->get('/admin/ads')->assertOk()->assertSee('100.00 %');
        $this->get("/ad/{$zone->id}/mobile")->assertNotFound();   // mobile disabled
        $this->assertTrue(AuditLog::where(['module' => 'ads', 'action' => 'update'])->exists());
    }

    public function test_header_and_in_article_zones_appear_only_when_active(): void
    {
        $this->get('/fr/articles/benin.html')->assertDontSee('data-ad-zone="header_top"', false)->assertDontSee('data-ad-zone="in_article"', false);
        Post::first()->translations()->update(['body' => '<p>Un</p><p>Deux</p><p>Trois</p><p>Quatre</p>']);
        $m = Media::create(['path' => 'media/ban.webp', 'mime' => 'image/webp', 'size' => 1, 'width' => 728, 'height' => 90, 'alt_fr' => 'Sponsor']);
        foreach (['header_top', 'in_article'] as $key) {
            AdZone::where('key', $key)->update(['active' => true, 'desktop' => ['enabled' => true, 'type' => 'banner', 'media_id' => $m->id, 'url' => 'https://s.example'], 'mobile' => ['enabled' => false, 'type' => 'banner']]);
        }
        $html = $this->get('/fr/articles/benin.html')->getContent();
        $this->assertStringContainsString('data-ad-zone="header_top"', $html);
        $this->assertMatchesRegularExpression('#<p>Trois</p><div class="ad-in-article" data-ad-zone="in_article">.*?</div><p>Quatre</p>#s', $html);
    }

    public function test_adsense_waits_for_consent_and_bad_codes_are_refused(): void
    {
        $this->configure(['type' => 'adsense', 'client' => 'ca-pub-1234567890123456', 'slot' => '1234567890']);
        $html = $this->get('/fr/articles/benin.html')->getContent();
        $this->assertStringContainsString('<script type="text/plain" data-consent-cat="ads" data-src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1234567890123456">', $html);

        $this->actingAs(User::factory()->create()->assignRole('admin'));
        $zone = AdZone::where('key', 'sidebar_article')->sole();
        $this->put("/admin/ads/{$zone->id}", ['name' => 'x', 'desktop' => ['type' => 'adsense', 'client' => 'pub-evil"><script>', 'slot' => '1'], 'mobile' => ['type' => 'banner']])
            ->assertSessionHasErrors(['desktop.client', 'desktop.slot']);
        $this->actingAs(User::factory()->create()->assignRole('editor'))->get("/admin/ads/{$zone->id}/edit")->assertForbidden();
    }
}
