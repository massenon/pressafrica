<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\HomeSlot;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_renders_the_editorial_selection_and_blocks(): void
    {
        $author = User::factory()->create(['name' => 'Pressafrica Media']);
        $econ = Category::create(['key' => 'economy', 'layout' => 'thematic']);
        $econ->translations()->create(['locale' => 'fr', 'name' => 'Économie', 'slug' => 'economie']);
        $posts = collect(range(1, 20))->map(function ($i) use ($author, $econ) {
            $p = Post::create(['author_id' => $author->id, 'status' => 'published', 'published_at' => now()->subHours($i)]);
            $p->translations()->create(['locale' => 'fr', 'title' => "Titre $i", 'slug' => "titre-$i", 'excerpt' => "Résumé $i", 'body' => '<p>x</p>']);
            $p->categories()->sync([$econ->id]);

            return $p;
        });
        foreach (['hero' => [5, 6, 7], 'latest' => [1, 2, 3, 4, 8, 9], 'econ_big' => [10, 11], 'econ_small' => [12, 13, 14, 15]] as $slot => $ids) {
            foreach ($ids as $i => $n) {
                HomeSlot::create(['slot' => $slot, 'position' => $i + 1, 'post_id' => $posts[$n - 1]->id]);
            }
        }

        HeroSlide::restoreDefaults();
        $this->get('/')->assertRedirect('/fr/index.html');
        $this->get('/fr')->assertRedirect('/fr/index.html');
        $r = $this->get('/fr/index.html')->assertOk();

        $r->assertSee('<title>'.e(__('seo.title.home', [], 'fr')).'</title>', false)
            ->assertSee('class="ticker"', false)
            ->assertSeeInOrder(['data-slide', 'Titre 6', 'data-slide', __('proto.hero_intro_title', [], 'fr'), 'data-slide', 'Titre 5', 'data-slide', 'Titre 7'])
            ->assertSeeInOrder(['id="sec-latest"', 'Titre 1', 'Titre 2', 'Titre 3'])
            ->assertSee('data-econ-page="1" hidden', false)                   // remaining economy posts on page 2
            ->assertSeeInOrder(['id="sec-read"', 'Titre 16'])                // newest post not shown elsewhere
            ->assertSee('class="search-frame', false)->assertSee('class="tiles"', false);
    }
}
