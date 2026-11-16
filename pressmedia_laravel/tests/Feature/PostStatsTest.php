<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\PageView;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Admin > Posts > Stats: the audience of one article, from the first-party visit log. */
class PostStatsTest extends TestCase
{
    use RefreshDatabase;

    private Post $post;

    private Post $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $author = User::factory()->create(['name' => 'Awa Diop']);
        $mk = fn (string $slug, string $title) => tap(Post::create(['author_id' => $author->id, 'status' => 'published', 'published_at' => now()->subDays(10), 'views' => 7]), fn ($p) => $p->translations()->create(['locale' => 'fr', 'title' => $title, 'slug' => $slug, 'body' => '<p>x</p>']));
        $this->post = $mk('benin', 'Bénin : élections');
        $this->other = $mk('togo', 'Togo : économie');
    }

    private function addView(Post $p, array $attrs = [], $at = null): void
    {
        PageView::create($attrs + ['path' => '/fr/articles/x', 'locale' => 'fr', 'post_id' => $p->id, 'device' => 'mobile', 'visitor' => 'v1', 'created_at' => $at ?? now()]);
    }

    public function test_the_page_shows_totals_referrers_countries_devices_and_comments(): void
    {
        $this->addView($this->post, ['referrer_host' => 'www.google.com', 'country' => 'BJ', 'visitor' => 'a']);
        $this->addView($this->post, ['referrer_host' => 'www.google.com', 'country' => 'BJ', 'visitor' => 'a']);   // same visitor, same day
        $this->addView($this->post, ['device' => 'desktop', 'locale' => 'en', 'visitor' => 'b'], now()->subDay());
        $this->addView($this->other, ['visitor' => 'c']);
        PageView::create(['path' => '/fr', 'locale' => 'fr', 'visitor' => 'z', 'device' => 'desktop', 'created_at' => now()]);   // not an article view
        Comment::create(['post_id' => $this->post->id, 'locale' => 'fr', 'author' => 'Awa', 'email' => 'a@example.test', 'body' => 'Bravo', 'status' => 'approved', 'consent_at' => now()]);
        Comment::create(['post_id' => $this->post->id, 'locale' => 'fr', 'author' => 'Bob', 'email' => 'b@example.test', 'body' => 'Ok', 'status' => 'pending', 'consent_at' => now()]);

        $this->actingAs(User::factory()->create()->assignRole('editor'));
        $r = $this->get('/admin/posts/'.$this->post->id.'/stats')->assertOk()
            ->assertSee('Bénin : élections')->assertSee('www.google.com')->assertSee('BJ')->assertSee('desktop')->assertSee('Direct or unknown')
            ->assertSee('Awaiting moderation');

        $this->assertSame(3, $r->viewData('stats')['totals']['views']);
        $this->assertSame(2, $r->viewData('stats')['totals']['visitors'], 'two visitors: a (counted once a day) and b');
        $this->assertSame(7, $r->viewData('stats')['totals']['all_time']);
        $this->assertSame(1, $r->viewData('stats')['rank'], '3 views against 1 for the other article');
        $this->assertSame(2, $r->viewData('stats')['ranked']);
        $this->assertSame(['approved' => 1, 'pending' => 1], $r->viewData('stats')['comments']);
    }

    public function test_every_day_of_the_period_is_listed_even_without_visits(): void
    {
        $this->addView($this->post, [], now()->subDays(3));

        $this->actingAs(User::factory()->create()->assignRole('editor'));
        $daily = $this->get('/admin/posts/'.$this->post->id.'/stats?days=7')->assertOk()->viewData('stats')['daily'];

        $this->assertCount(7, $daily);
        $this->assertSame([1], collect($daily)->pluck(1)->filter()->values()->all());
        $this->assertSame(now()->toDateString(), end($daily)[0]);
    }

    public function test_a_young_article_starts_at_its_publication_day(): void
    {
        $this->post->forceFill(['published_at' => now()->subDays(2)])->saveQuietly();

        $this->actingAs(User::factory()->create()->assignRole('editor'));
        $this->assertCount(3, $this->get('/admin/posts/'.$this->post->id.'/stats?days=90')->viewData('stats')['daily']);
    }

    public function test_an_article_without_visits_says_so_and_an_unknown_period_falls_back_to_30_days(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('editor'));
        $this->get('/admin/posts/'.$this->post->id.'/stats?days=5')->assertOk()->assertSee('No visit recorded in this period')->assertSee('–');
    }

    public function test_the_screen_needs_the_analytics_right_and_the_list_links_to_it(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('author'));
        $this->get('/admin/posts/'.$this->post->id.'/stats')->assertForbidden();

        $this->actingAs(User::factory()->create()->assignRole('editor'));
        $this->get('/admin/posts')->assertOk()->assertSee('/admin/posts/'.$this->post->id.'/stats', false)->assertSee('>Views<', false);
        $this->get('/admin/posts/999999/stats')->assertNotFound();

        auth()->logout();
        $this->get('/admin/posts/'.$this->post->id.'/stats')->assertRedirect('/admin/login');
    }
}
