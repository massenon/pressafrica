<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ArticlePageTest extends TestCase
{
    use RefreshDatabase;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();
        $author = User::factory()->create(['name' => 'Pressafrica Media']);
        $cats = [];
        foreach ([['online-press', 'Presse en ligne', 'presse-en-ligne', 'Online Press'], ['society', 'Société', 'societe', 'Society']] as [$k, $fr, $slug, $en]) {
            $c = Category::create(['key' => $k]);
            $c->translations()->create(['locale' => 'fr', 'name' => $fr, 'slug' => $slug]);
            $c->translations()->create(['locale' => 'en', 'name' => $en, 'slug' => Str::slug($en)]);
            $cats[$k] = $c;
        }
        $mk = function (string $slugFr, string $title, string $date, array $catKeys, ?string $slugEn = null) use ($author, $cats) {
            $p = Post::create(['author_id' => $author->id, 'status' => 'published', 'published_at' => $date]);
            $p->translations()->create(['locale' => 'fr', 'title' => $title, 'slug' => $slugFr, 'excerpt' => "Résumé de $title", 'body' => '<p>Intro</p><h2>Intertitre</h2><p>'.str_repeat('Texte. ', 400).'</p>']);
            if ($slugEn) {
                $p->translations()->create(['locale' => 'en', 'title' => "EN $title", 'slug' => $slugEn, 'body' => '<p>English body</p>']);
            }
            $p->categories()->sync(collect($catKeys)->map(fn ($k) => $cats[$k]->id));

            return $p;
        };
        $mk('plus-recent', 'Plus récent', '2026-01-03 10:00', ['online-press']);
        $this->post = $mk('tiktok', 'Afrique : alerte sur TikTok', '2026-01-02 10:00', ['society', 'online-press'], 'tiktok-en');
        $mk('plus-ancien', 'Plus ancien', '2026-01-01 10:00', ['society']);
        Post::create(['author_id' => $author->id, 'status' => 'draft'])->translations()->create(['locale' => 'fr', 'title' => 'Brouillon', 'slug' => 'brouillon', 'body' => 'x']);
    }

    public function test_the_article_renders_with_the_prototype_blocks(): void
    {
        $r = $this->get('/fr/articles/tiktok')->assertOk();

        $r->assertSee('<h1 class="article-title">Afrique : alerte sur TikTok</h1>', false)
            ->assertSee('<h2>Intertitre</h2>', false)                          // purified body printed as HTML
            ->assertSeeInOrder(['class="chip"', 'Presse en ligne', 'Société'])  // chips in label order
            ->assertSee(__('site.min_read', ['n' => 3], 'fr'))
            ->assertSee('rel="prev"', false)->assertSee('Plus récent')         // newer neighbour
            ->assertSee('rel="next"', false)->assertSee('Plus ancien')
            ->assertSee('class="site-header"', false)->assertSee('class="site-footer"', false)
            ->assertSee('data-comment-server', false)
            ->assertSee('action="'.url('fr/articles/tiktok/comments').'"', false);
    }

    public function test_seo_tags_and_structured_data(): void
    {
        $html = $this->get('/fr/articles/tiktok')->getContent();

        $this->assertStringContainsString('<link rel="canonical" href="'.url('fr/articles/tiktok').'">', $html);
        $this->assertStringContainsString('hreflang="en" href="'.url('en/articles/tiktok-en').'"', $html);
        $this->assertStringContainsString('<title>Afrique : alerte sur TikTok | Africa Global Press</title>', $html);
        $this->assertStringContainsString('<meta property="og:type" content="article">', $html);
        $this->assertStringContainsString('<meta property="og:locale" content="fr_FR">', $html);

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $ld = collect($m[1])->map(fn ($j) => json_decode($j, true));
        $this->assertTrue($ld->every(fn ($x) => is_array($x)), 'every JSON-LD block parses');
        $article = $ld->firstWhere('@type', 'NewsArticle');
        $this->assertSame(['Afrique : alerte sur TikTok', 'fr', 'Pressafrica Media'], [$article['headline'], $article['inLanguage'], $article['author']['name']]);
        $crumbs = $ld->firstWhere('@type', 'BreadcrumbList')['itemListElement'];
        $this->assertSame('Société', $crumbs[1]['name'], 'the catch-all section is not used when another exists');
    }

    public function test_language_switch_and_unknown_or_unpublished_pages(): void
    {
        $this->get('/en/articles/tiktok-en')->assertOk()->assertSee('<html lang="en"', false)->assertSee('href="'.url('fr/articles/tiktok').'"', false);
        $this->get('/en/articles/tiktok')->assertNotFound();   // French slug on the English site
        $this->get('/fr/articles/brouillon')->assertNotFound();
        $this->get('/fr/articles/nope')->assertNotFound();
    }

    public function test_only_approved_comments_show_and_never_their_email(): void
    {
        foreach (['approved' => 'Bravo', 'pending' => 'En attente', 'spam' => 'Achetez maintenant'] as $status => $body) {
            Comment::create(['post_id' => $this->post->id, 'locale' => 'fr', 'author' => "Awa $status", 'email' => "$status@example.test", 'body' => $body, 'status' => $status, 'consent_at' => now()]);
        }
        $this->get('/fr/articles/tiktok')->assertSee('Awa approved')->assertSee('Bravo')
            ->assertDontSee('En attente')->assertDontSee('Achetez maintenant')->assertDontSee('@example.test');
    }

    public function test_title_and_description_rules_match_the_prototype(): void
    {
        $this->assertSame('Court | Africa Global Press', Seo::title('Court'));
        $long = 'Un titre vraiment très long qui dépasse nettement la limite des soixante-dix caractères';
        $this->assertSame('Un titre vraiment très long qui dépasse… | Africa Global Press', Seo::title($long));
        $this->assertSame('Titre SEO choisi | Africa Global Press', Seo::title($long, 'Titre SEO choisi'));
        $seo = str_repeat('Titre SEO de soixante caractères ', 2);   // fits alone, not with the suffix
        $this->assertSame($seo, Seo::title($long, $seo));
        $this->assertSame('abc def…', Seo::truncate('abc def ghi', 9));
    }
}
