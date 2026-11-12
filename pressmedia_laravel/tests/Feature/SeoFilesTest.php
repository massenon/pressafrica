<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoFilesTest extends TestCase
{
    use RefreshDatabase;

    private function article(string $slug, $publishedAt, string $status = 'published'): Post
    {
        $p = Post::create(['author_id' => User::factory()->create()->id, 'status' => $status, 'published_at' => $publishedAt]);
        $p->translations()->create(['locale' => 'fr', 'title' => "Titre $slug", 'slug' => $slug, 'excerpt' => 'Résumé', 'body' => '<p>x</p>']);
        $p->translations()->create(['locale' => 'en', 'title' => "Title $slug", 'slug' => "$slug-en", 'body' => '<p>x</p>']);

        return $p;
    }

    public function test_robots_points_to_the_sitemaps_and_hides_the_admin(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /admin/')->assertSee('Sitemap: '.url('sitemap.xml'))->assertSee(url('news-sitemap.xml'));
        Settings::save(['newsSitemap' => false]);
        $this->get('/robots.txt')->assertDontSee('news-sitemap');
    }

    public function test_sitemap_lists_published_articles_with_alternates_and_skips_search(): void
    {
        $this->article('publie', now()->subDay());
        $this->article('brouillon', null, 'draft');

        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        $this->assertNotFalse(simplexml_load_string($xml), 'valid XML');
        $this->assertStringContainsString('<loc>'.url('fr/articles/publie').'</loc>', $xml);
        $this->assertStringContainsString('hreflang="en" href="'.url('en/articles/publie-en').'"', $xml);
        $this->assertStringContainsString('<loc>'.url('fr/qui-sommes-nous').'</loc>', $xml);
        $this->assertStringNotContainsString('brouillon', $xml);
        $this->assertStringNotContainsString('recherche', $xml);
    }

    public function test_news_sitemap_keeps_the_last_48_hours_only(): void
    {
        $this->article('frais', now()->subHours(3));
        $this->article('ancien', now()->subDays(3));
        $xml = $this->get('/news-sitemap.xml')->assertOk()->getContent();
        $this->assertNotFalse(simplexml_load_string($xml));
        $this->assertStringContainsString('fr/articles/frais', $xml);
        $this->assertStringContainsString('<news:language>en</news:language>', $xml);
        $this->assertStringNotContainsString('ancien', $xml);

        Settings::save(['newsSitemap' => false]);
        $this->get('/news-sitemap.xml')->assertNotFound();
    }

    public function test_pwa_manifest_and_service_worker_follow_the_setting(): void
    {
        Settings::save(['pwaName' => 'AGP Info', 'pwaTheme' => '#112233']);
        $this->get('/manifest.webmanifest')->assertOk()->assertJsonPath('start_url', '/fr')->assertJsonPath('name', 'AGP Info')->assertJsonPath('theme_color', '#112233');
        $js = $this->get('/sw.js')->assertOk()->assertHeader('Content-Type', 'application/javascript')->getContent();
        $this->assertStringContainsString("e.request.mode === 'navigate'", $js);
        $this->assertStringContainsString('admin|api', $js);
        $this->get('/fr')->assertSee('data-sw="/sw.js"', false)->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false);

        Settings::save(['pwa' => false]);
        $this->get('/sw.js')->assertNotFound();
        $this->get('/fr')->assertDontSee('data-sw=', false);
    }

    public function test_rss_feed_per_language(): void
    {
        $this->article('frais', now()->subHour());
        $xml = $this->get('/en/feed.xml')->assertOk()->getContent();
        $this->assertNotFalse(simplexml_load_string($xml));
        $this->assertStringContainsString('<title>Title frais</title>', $xml);
        Settings::save(['rssFeed' => false]);
        $this->get('/en/feed.xml')->assertNotFound();
    }
}
