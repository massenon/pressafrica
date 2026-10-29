<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    private function as(string $role): User
    {
        $u = User::factory()->create()->assignRole($role);
        $this->actingAs($u);

        return $u;
    }

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'status' => 'draft',
            'translations' => ['fr' => ['title' => 'Bénin : le pétrole', 'body' => '<p>Texte</p>']],
        ], $overrides);
    }

    public function test_a_french_only_post_is_created_sanitised_tagged_and_journaled(): void
    {
        $this->as('editor');
        $cat = Category::create(['key' => 'economie']);

        $this->post('/admin/posts', $this->payload([
            'status' => 'published', 'categories' => [$cat->id], 'tags' => 'Pétrole, Bénin, pétrole',
            'translations' => ['fr' => ['body' => '<p onclick="x()">Texte</p><script>alert(1)</script>']],
        ]))->assertRedirect();

        $post = Post::with('translations', 'tags')->sole();
        $t = $post->translation('fr');
        $this->assertSame('benin-le-petrole', $t->slug);
        $this->assertStringNotContainsString('<script', $t->body);
        $this->assertStringNotContainsString('onclick', $t->body);
        $this->assertSame('published', $post->status);
        $this->assertNotNull($post->published_at);
        $this->assertCount(1, $post->translations, 'English is optional (INV-10)');
        $this->assertSame(['petrole', 'benin'], $post->tags->pluck('slug')->all());
        $this->assertSame([$cat->id], $post->categories()->pluck('categories.id')->all());
        $this->assertTrue(AuditLog::where(['action' => 'create', 'module' => 'posts'])->exists());

        $this->assertSame('fr', Post::first()->translation('en')->locale, 'missing EN falls back to French content');
    }

    public function test_derived_slugs_get_a_suffix_and_explicit_duplicates_are_rejected(): void
    {
        $this->as('editor');
        $this->post('/admin/posts', $this->payload());
        $this->post('/admin/posts', $this->payload());
        $this->assertSame(['benin-le-petrole', 'benin-le-petrole-2'], Post::with('translations')->get()->map(fn ($p) => $p->translation('fr')->slug)->all());

        $this->post('/admin/posts', $this->payload(['translations' => ['fr' => ['slug' => 'benin-le-petrole']]]))->assertSessionHasErrors('translations.fr.slug');
    }

    public function test_english_needs_a_body_when_it_has_a_title(): void
    {
        $this->as('editor');
        $this->post('/admin/posts', $this->payload(['translations' => ['en' => ['title' => 'Benin oil']]]))->assertSessionHasErrors('translations.en.body');
    }

    public function test_authors_cannot_publish_and_only_edit_their_own_posts(): void
    {
        $other = User::factory()->create();
        $foreign = Post::create(['author_id' => $other->id, 'status' => 'draft']);
        $foreign->translations()->create(['locale' => 'fr', 'title' => 'Autre', 'slug' => 'autre', 'body' => 'x']);

        $this->as('author');
        $this->post('/admin/posts', $this->payload(['status' => 'published']))->assertSessionHasErrors('status');
        $this->post('/admin/posts', $this->payload(['status' => 'pending']))->assertRedirect();

        $this->get("/admin/posts/{$foreign->id}/edit")->assertForbidden();
        $this->put("/admin/posts/{$foreign->id}", $this->payload())->assertForbidden();
        $this->get('/admin/posts')->assertOk()->assertSee('Bénin : le pétrole')->assertDontSee('Autre');
    }

    /** PRD phase 2 exit criterion: a scheduled post publishes itself when due (scheduler command). */
    public function test_a_scheduled_post_publishes_itself_when_due(): void
    {
        $this->as('editor');
        $this->post('/admin/posts', $this->payload(['status' => 'published', 'published_at' => now()->addHour()->format('Y-m-d H:i')]));
        $post = Post::sole();
        $this->assertSame('scheduled', $post->status, 'published with a future date becomes scheduled');

        $this->artisan('posts:publish-scheduled');
        $this->assertSame('scheduled', $post->fresh()->status);

        $this->travel(61)->minutes();
        $this->artisan('posts:publish-scheduled')->expectsOutput('Published 1 post(s).');
        $this->assertSame('published', $post->fresh()->status);
        $this->assertSame(1, Post::published()->count());
        $this->assertTrue(AuditLog::where(['action' => 'publish', 'user_name' => 'System'])->exists());
    }

    public function test_scheduling_requires_a_future_date(): void
    {
        $this->as('editor');
        $this->post('/admin/posts', $this->payload(['status' => 'scheduled']))->assertSessionHasErrors('published_at');
        $this->post('/admin/posts', $this->payload(['status' => 'scheduled', 'published_at' => now()->subDay()->format('Y-m-d H:i')]))->assertSessionHasErrors('published_at');
    }

    public function test_the_scheduler_runs_the_publish_command_every_minute(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'posts:publish-scheduled'));
        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
    }

    public function test_editor_forms_render_with_saved_values(): void
    {
        $this->as('editor');
        Category::create(['key' => 'economie'])->translations()->create(['locale' => 'fr', 'name' => 'Économie', 'slug' => 'economie']);
        $this->get('/admin/posts/create')->assertOk()->assertSee('Économie')->assertSee('scheduled');
        $this->post('/admin/posts', $this->payload(['tags' => 'Pétrole']));
        $this->get('/admin/posts/'.Post::sole()->id.'/edit')->assertOk()->assertSee('Bénin : le pétrole')->assertSee('value="Pétrole"', false);

        $this->as('author');
        $this->get('/admin/posts/create')->assertOk()->assertDontSee('<option value="published"', false);
    }

    public function test_deleting_archives_the_post(): void
    {
        $this->as('editor');
        $this->post('/admin/posts', $this->payload());
        $post = Post::sole();
        $this->delete("/admin/posts/{$post->id}")->assertRedirect('/admin/posts');
        $this->assertSoftDeleted($post);
        $this->assertTrue(AuditLog::where(['action' => 'delete', 'target' => 'post:'.$post->id])->exists());
    }
}
