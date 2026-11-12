<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectionPageTest extends TestCase
{
    use RefreshDatabase;

    private array $cats = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([['online-press', 'archive', 'Presse en ligne', 'presse-en-ligne', null], ['society', 'archive', 'Société', 'societe', null],
            ['politics', 'thematic', 'Politique', 'politique', 'Actualités Politiques'], ['podcasts', 'landing', 'Podcasts', 'podcasts', null], ['sports', 'thematic', 'Sports', 'sport', 'Actualités Sportives']] as [$k, $layout, $name, $slug, $heading]) {
            $c = Category::create(['key' => $k, 'layout' => $layout]);
            $c->translations()->create(['locale' => 'fr', 'name' => $name, 'slug' => $slug, 'heading' => $heading, 'seo_title' => "$name | Africa Global Press", 'description' => "Desc $name"]);
            $c->translations()->create(['locale' => 'en', 'name' => "EN $name", 'slug' => "en-$slug"]);
            $this->cats[$k] = $c;
        }
        $author = User::factory()->create(['name' => 'Pressafrica Media']);
        foreach (range(1, 12) as $i) {
            $p = Post::create(['author_id' => $author->id, 'status' => 'published', 'published_at' => now()->subDays($i)]);
            $p->translations()->create(['locale' => 'fr', 'title' => "Article $i", 'slug' => "article-$i", 'excerpt' => "Résumé $i", 'body' => '<p>x</p>']);
            $p->categories()->sync([$this->cats['society']->id, $this->cats['politics']->id, $this->cats['online-press']->id]);
        }
    }

    public function test_archive_layout_with_feature_side_story_pages_and_faq(): void
    {
        $html = $this->get('/fr/societe')->assertOk()
            ->assertSee('<h1 class="page-title">Société</h1>', false)
            ->assertSee('<title>Société | Africa Global Press</title>', false)
            ->assertSeeInOrder(['class="feat reveal"', 'Article 1', 'class="feat-side reveal"', 'Article 2', 'data-page="0"', 'Article 3', 'data-page="1"', 'Article 9'])
            ->assertSee('data-goto="1"', false)
            ->assertSee('Quelles actualités société trouve-t-on sur Africa Global Press ?')
            ->getContent();

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $types = collect($m[1])->map(fn ($j) => json_decode($j, true)['@type'] ?? null)->all();
        $this->assertContains('FAQPage', $types);
        $this->assertStringContainsString('"name":"Presse en ligne"', $html, 'archive sections sit under Online press in the breadcrumb');
        $this->assertStringContainsString('hreflang="en" href="'.url('en/en-societe').'"', $html);
    }

    public function test_thematic_layout_with_heading_and_show_more(): void
    {
        $this->get('/fr/politique')->assertOk()
            ->assertSee('<h1 class="thematic-title">Actualités Politiques</h1>', false)
            ->assertSee('class="strip"', false)
            ->assertSee('<div class="grid-cell" hidden data-more>', false)   // items 10-12 behind "show more"
            ->assertSee('data-loadmore', false);
    }

    public function test_empty_landing_and_unknown_sections(): void
    {
        $this->get('/fr/sport')->assertOk()->assertSee(__('site.empty', [], 'fr'));
        $this->get('/fr/podcasts')->assertOk()->assertSee(__('proto.landing_title', [], 'fr'));   // empty media section: coming soon
        $this->get('/fr/nope')->assertNotFound();
        $this->get('/en/societe')->assertNotFound();    // French slug on the English site
    }
}
