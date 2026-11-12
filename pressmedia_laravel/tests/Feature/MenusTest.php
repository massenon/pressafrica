<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        foreach ([['online-press', 'presse-en-ligne', 'Presse en ligne'], ['podcasts', 'podcasts', 'Podcasts'], ['videos', 'videos', 'Vidéos'], ['politics', 'politique', 'Politique']] as [$k, $slug, $name]) {
            Category::create(['key' => $k, 'layout' => 'archive'])->translations()->create(['locale' => 'fr', 'name' => $name, 'slug' => $slug]);
        }
        foreach (range(1, 6) as $i) {
            $p = Post::create(['author_id' => User::factory()->create()->id, 'status' => 'published', 'published_at' => now()->subHours($i), 'legacy_id' => 4554 + $i]);
            $p->translations()->create(['locale' => 'fr', 'title' => "Article $i", 'slug' => "article-$i", 'body' => '<p>x</p>']);
            $p->categories()->sync([Category::where('key', $i % 2 ? 'politics' : 'online-press')->value('id')]);
        }
        MenuItem::restoreDefaults();
    }

    public function test_the_default_menu_lists_the_latest_articles_in_its_panels(): void
    {
        $html = $this->get('/fr')->assertOk()->getContent();
        $nav = substr($html, strpos($html, '<ul class="nav-list">'), 6000);
        $this->assertStringContainsString('id="mega-top"', $nav);
        $this->assertStringContainsString('Article 1', $nav);
        $this->assertSame(4, substr_count($nav, 'class="nav-item" data-mega'));
        // Press panel: the three picks (legacy 4555-4557 = Articles 1-3) then the latest four.
        $press = substr($nav, strpos($nav, 'id="mega-press"'));
        $this->assertStringContainsString('mega-list-thumb', $press);
        // "Explore by topic" lists the section's articles.
        $this->assertStringContainsString('id="tp-politics"', $html);
    }

    public function test_admin_edits_reorders_hides_and_restores_the_menu(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('admin'));
        $vid = MenuItem::where('key', 'vid')->sole();

        $this->put("/admin/menus/{$vid->id}", ['key' => 'vid', 'panel' => 'none', 'active' => '1', 'translations' => ['fr' => ['label' => 'Nos vidéos', 'url' => '/fr/videos']]])->assertRedirect();
        $this->post("/admin/menus/{$vid->id}/move", ['dir' => 'up'])->assertRedirect();
        $this->get('/fr')->assertSeeInOrder(['nav-list', 'Nos vidéos', 'Podcasts']);

        $this->post('/admin/menus', ['key' => 'pol', 'panel' => 'articles', 'category_id' => Category::where('key', 'politics')->value('id'), 'from_category' => '1', 'active' => '0', 'translations' => ['fr' => ['label' => 'Politique']]])->assertRedirect();
        $this->get('/fr')->assertDontSee('id="mega-pol"', false);   // hidden entry

        $this->post('/admin/menus/restore')->assertRedirect();
        $this->assertSame(['top', 'pod', 'vid', 'press'], MenuItem::orderBy('position')->pluck('key')->all());
        $this->assertSame(['update', 'reorder', 'create', 'restore'], AuditLog::where('module', 'menus')->orderBy('id')->pluck('action')->all());
    }

    public function test_editors_only_view_the_menus(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('editor'));
        $this->get('/admin/menus')->assertOk()->assertDontSee('Restore default menu');
        $this->post('/admin/menus/restore')->assertForbidden();
    }
}
