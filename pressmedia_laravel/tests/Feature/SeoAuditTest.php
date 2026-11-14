<?php

namespace Tests\Feature;

use App\Http\Middleware\PageCache;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use App\Support\Assets;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Front-end SEO audit fixes: hreflang, duplicates, sitemaps, robots, headers, pagination, social tags, authors. */
class SeoAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = User::factory()->create(['name' => 'Awa Diop']);
        $this->cat = Category::create(['key' => 'society']);
        $this->cat->translations()->create(['locale' => 'fr', 'name' => 'Société', 'slug' => 'societe']);
        $this->cat->translations()->create(['locale' => 'en', 'name' => 'Society', 'slug' => 'society']);
    }

    /** @param  array<string, array<string, mixed>>  $versions  locale => translation attributes (slug, body_locale...) */
    private function makePost(array $versions, $at = '2026-01-02 10:00'): Post
    {
        $p = Post::create(['author_id' => $this->author->id, 'status' => 'published', 'published_at' => $at]);
        foreach ($versions as $locale => $attrs) {
            $p->translations()->create($attrs + ['locale' => $locale, 'title' => "Titre $locale {$attrs['slug']}", 'excerpt' => 'Résumé', 'body' => '<p>Corps</p>']);
        }
        $p->categories()->sync([$this->cat->id]);

        return $p;
    }

    public function test_hreflang_never_points_a_missing_language_at_the_home_page(): void
    {
        $this->makePost(['fr' => ['slug' => 'seul']]);

        $html = $this->get('/fr/articles/seul')->assertOk()->getContent();
        $this->assertStringContainsString('hreflang="fr" href="'.url('fr/articles/seul').'"', $html);
        $this->assertStringNotContainsString('<link rel="alternate" hreflang="en"', $html);
        $this->assertStringContainsString('hreflang="x-default" href="'.url('fr/articles/seul').'"', $html);
        $this->assertStringContainsString('<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">', $html);
    }

    public function test_a_translation_with_a_foreign_body_is_noindex_and_left_out_everywhere(): void
    {
        $this->makePost(['fr' => ['slug' => 'vrai'], 'en' => ['slug' => 'real-en', 'body_locale' => 'fr']], now()->subHour());

        $en = $this->get('/en/articles/real-en')->assertOk()->getContent();
        $this->assertStringContainsString('<meta name="robots" content="noindex,follow">', $en);

        $fr = $this->get('/fr/articles/vrai')->getContent();
        $this->assertStringNotContainsString('<link rel="alternate" hreflang="en"', $fr, 'the duplicate is not in the hreflang cluster');

        $this->assertStringNotContainsString('real-en', $this->get('/sitemap.xml')->getContent());
        $this->assertStringNotContainsString('real-en', $this->get('/news-sitemap.xml')->getContent());
        $this->assertStringNotContainsString('real-en', $this->get('/en/feed.xml')->getContent());
        $this->assertStringContainsString('fr/articles/vrai', $this->get('/news-sitemap.xml')->getContent());
    }

    public function test_sitemap_lastmod_is_real_and_fixed_pages_have_none(): void
    {
        $p = $this->makePost(['fr' => ['slug' => 'a'], 'en' => ['slug' => 'a-en']]);
        $p->forceFill(['updated_at' => '2026-03-04 12:00:00'])->saveQuietly();

        $xml = $this->get('/sitemap.xml')->getContent();
        $this->assertMatchesRegularExpression('#<loc>'.preg_quote(url('fr'), '#').'</loc><lastmod>2026-03-04</lastmod>#', $xml, 'home = newest article');
        $this->assertMatchesRegularExpression('#<loc>'.preg_quote(url('fr/societe'), '#').'</loc><lastmod>2026-03-04</lastmod>#', $xml, 'section = its newest article');
        $this->assertMatchesRegularExpression('#<loc>'.preg_quote(url('fr/qui-sommes-nous'), '#').'</loc><xhtml#', $xml, 'a fixed page has no invented date');
        $this->assertStringNotContainsString(now()->toDateString(), $xml);
    }

    public function test_sitemap_becomes_an_index_past_the_chunk_size_and_lists_authors(): void
    {
        config(['seo.sitemap_chunk' => 2]);
        foreach (['a', 'b', 'c'] as $i => $s) {
            $this->makePost(['fr' => ['slug' => $s]], now()->subDays($i + 1));
        }

        $index = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('<sitemapindex', $index);
        $this->assertStringContainsString('<loc>'.url('sitemap-2.xml').'</loc>', $index);

        $one = $this->get('/sitemap-1.xml')->assertOk()->getContent();
        $two = $this->get('/sitemap-2.xml')->assertOk()->getContent();
        $this->assertStringContainsString('fr/articles/a', $one);
        $this->assertStringContainsString('fr/authors/awa-diop', $one, 'authors sit in part 1');
        $this->assertStringContainsString('fr/articles/c', $two);
        $this->assertStringNotContainsString('fr/articles/c', $one);
        $this->get('/sitemap-3.xml')->assertNotFound();
    }

    public function test_sitemap_is_cached_and_refreshed_after_a_content_change(): void
    {
        config(['cache.pages_enabled' => true]);
        PageCache::flush();
        $this->makePost(['fr' => ['slug' => 'un']]);
        $this->assertStringNotContainsString('fr/articles/deux', $this->get('/sitemap.xml')->getContent());

        $this->makePost(['fr' => ['slug' => 'deux']]);   // any save moves the cache version
        $this->assertStringContainsString('fr/articles/deux', $this->get('/sitemap.xml')->getContent());
        PageCache::flush();
    }

    public function test_robots_blocks_ad_clicks_and_whole_site_outside_production(): void
    {
        $this->get('/robots.txt')->assertSee('Disallow: /ad/')->assertSee('Disallow: /admin/');

        $this->app['env'] = 'staging';
        $this->get('/robots.txt')->assertSee('Disallow: /', false)->assertDontSee('Sitemap:')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_static_seo_files_carry_no_cookie_and_revalidate_with_an_etag(): void
    {
        $r = $this->get('/robots.txt')->assertOk();
        $this->assertSame([], $r->headers->getCookies(), 'no session or CSRF cookie on robots.txt');
        $this->assertStringContainsString('public', $r->headers->get('Cache-Control'));

        $etag = $this->get('/sitemap.xml')->assertOk()->headers->get('ETag');
        $this->assertNotEmpty($etag);
        $this->withHeader('If-None-Match', $etag)->get('/sitemap.xml')->assertStatus(304);

        $this->get('/sw.js')->assertHeader('Cache-Control', 'no-cache, public');
    }

    public function test_admin_answers_noindex(): void
    {
        $this->get('/admin/login')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/fr')->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_page_cache_ignores_tracking_parameters_but_not_pagination(): void
    {
        config(['cache.pages_enabled' => true]);
        PageCache::flush();
        $this->get('/fr?utm_source=a&fbclid=1')->assertHeader('X-Page-Cache', 'miss');
        $this->get('/fr?utm_source=b&gclid=2')->assertHeader('X-Page-Cache', 'hit');
        $this->get('/fr/societe')->assertHeader('X-Page-Cache', 'miss');
        $this->get('/fr/societe?page=2')->assertStatus(404);
        PageCache::flush();
    }

    public function test_sections_are_paginated_with_prev_next_and_self_canonical(): void
    {
        config(['seo.per_page' => 2]);
        foreach (['a', 'b', 'c', 'd', 'e'] as $i => $s) {
            $this->makePost(['fr' => ['slug' => $s]], now()->subDays($i + 1));
        }

        $one = $this->get('/fr/societe')->assertOk()->getContent();
        $this->assertStringContainsString('<link rel="next" href="'.url('fr/societe').'?page=2">', $one);
        $this->assertStringNotContainsString('rel="prev" href', $one);
        $this->assertStringContainsString('<link rel="canonical" href="'.url('fr/societe').'">', $one);

        $two = $this->get('/fr/societe?page=2')->assertOk()->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="'.url('fr/societe').'?page=2">', $two);
        $this->assertStringContainsString('<link rel="prev" href="'.url('fr/societe').'">', $two, 'page 1 is the bare URL');
        $this->assertStringContainsString('<link rel="next" href="'.url('fr/societe').'?page=3">', $two);
        $this->assertMatchesRegularExpression('#<title>[^<]*Page 2[^<]*</title>#', $two);
        $this->assertStringContainsString('rel="next"', $two);

        $this->get('/fr/societe?page=4')->assertNotFound();
    }

    public function test_section_without_a_description_still_gets_one(): void
    {
        $html = $this->get('/fr/societe')->assertOk()->getContent();
        preg_match('#<meta name="description" content="([^"]*)"#', $html, $m);
        $this->assertNotEmpty($m[1] ?? '');
        $this->assertLessThanOrEqual(161, mb_strlen(html_entity_decode($m[1])));
    }

    public function test_social_tags_default_image_rss_discovery_and_organization(): void
    {
        $html = $this->get('/fr')->assertOk()->getContent();
        $this->assertStringContainsString('<meta property="og:image" content="'.url('img/og-default.png').'">', $html);
        $this->assertStringContainsString('<meta property="og:image:width" content="1200">', $html);
        $this->assertStringContainsString('<meta name="twitter:site" content="@africaglobalpress">', $html);
        $this->assertStringContainsString('<meta name="twitter:image" content="'.url('img/og-default.png').'">', $html);
        $this->assertStringContainsString('type="application/rss+xml"', $html);
        $this->assertStringContainsString('href="'.url('fr/feed.xml').'"', $html);

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $org = collect($m[1])->map(fn ($j) => json_decode($j, true))->firstWhere('@type', 'Organization');
        $this->assertSame('ImageObject', $org['logo']['@type']);
        $this->assertContains('https://x.com/africaglobalpress', $org['sameAs']);

        Settings::save(['rssFeed' => false]);
        $this->assertStringNotContainsString('rss+xml', $this->get('/fr')->getContent());
    }

    public function test_section_pages_carry_no_faqpage_markup(): void
    {
        $this->assertStringNotContainsString('FAQPage', $this->get('/fr/societe')->getContent());
    }

    public function test_article_links_its_author_page_and_json_ld_is_valid(): void
    {
        $this->makePost(['fr' => ['slug' => 'signe'], 'en' => ['slug' => 'signed']]);

        $html = $this->get('/fr/articles/signe')->getContent();
        $this->assertStringContainsString('<meta property="article:author" content="'.url('fr/authors/awa-diop').'">', $html);
        $this->assertStringContainsString('<a href="'.url('fr/authors/awa-diop').'" rel="author">Awa Diop</a>', $html);
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $article = collect($m[1])->map(fn ($j) => json_decode($j, true))->firstWhere('@type', 'NewsArticle');
        $this->assertSame(['Person', 'Awa Diop', url('fr/authors/awa-diop')], [$article['author']['@type'], $article['author']['name'], $article['author']['url']]);

        $page = $this->get('/fr/authors/awa-diop')->assertOk()->assertSee('<h1 class="page-title">Awa Diop</h1>', false)->assertSee('Titre fr signe')->getContent();
        $this->assertStringContainsString('"@type":"ProfilePage"', $page);
        $this->assertStringContainsString('hreflang="en" href="'.url('en/authors/awa-diop').'"', $page);
        $this->get('/fr/authors/inconnu')->assertNotFound();
    }

    public function test_an_author_without_a_name_falls_back_to_the_organization_in_json_ld(): void
    {
        $this->author->forceFill(['name' => ''])->saveQuietly();
        $this->makePost(['fr' => ['slug' => 'anonyme']]);

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $this->get('/fr/articles/anonyme')->getContent(), $m);
        $article = collect($m[1])->map(fn ($j) => json_decode($j, true))->firstWhere('@type', 'NewsArticle');
        $this->assertSame('Organization', $article['author']['@type']);
        $this->assertStringNotContainsString('rel="author"', $this->get('/fr/articles/anonyme')->getContent());
    }

    public function test_asset_urls_carry_a_version(): void
    {
        $this->assertMatchesRegularExpression('#^/img/og-default\.png\?v=\d+$#', Assets::v('/img/og-default.png'));
        $this->assertSame('/missing/file.css', Assets::v('/missing/file.css'));
    }
}
