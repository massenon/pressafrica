<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaticPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixed_pages_render_in_both_languages_with_their_seo(): void
    {
        foreach (['fr/qui-sommes-nous', 'fr/contact', 'fr/mentions-legales', 'fr/politique-de-confidentialite', 'fr/politique-cookies',
            'en/about-us', 'en/contact', 'en/legal-notices', 'en/privacy-policy', 'en/cookie-policy'] as $page) {
            $html = $this->get("/$page")->assertOk()->assertSee('<link rel="canonical" href="'.url("$page").'">', false)->getContent();
            $this->assertStringNotContainsString('africaglobalpress.example', $html, "$page: prototype host replaced");
            $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        }
        $this->get('/fr/mentions-legales')->assertSee('hreflang="en" href="'.url('en/legal-notices').'"', false)
            ->assertSee('<title>'.e(__('seo.title.legal', [], 'fr')).'</title>', false);
    }

    public function test_search_page_is_noindex_and_carries_the_published_posts_index(): void
    {
        $author = User::factory()->create(['name' => 'Pressafrica Media']);
        foreach (['published' => now()->subDay(), 'draft' => null] as $status => $at) {
            Post::create(['author_id' => $author->id, 'status' => $status, 'published_at' => $at])
                ->translations()->create(['locale' => 'fr', 'title' => "Titre $status", 'slug' => $status, 'excerpt' => 'Résumé', 'body' => '<p>x</p>']);
        }

        $html = $this->get('/fr/recherche')->assertOk()->assertSee('<meta name="robots" content="noindex,follow">', false)->getContent();
        preg_match('#<script type="application/json" id="search-index">(.*?)</script>#s', $html, $m);
        $index = json_decode($m[1], true);
        $this->assertSame(['Titre published'], array_column($index, 't'));
        $this->assertSame(url('fr/articles/published'), $index[0]['u']);
        $this->assertSame('Pressafrica Media', $index[0]['a']);
    }
}
