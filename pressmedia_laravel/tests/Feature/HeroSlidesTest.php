<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\HeroSlide;
use App\Models\HomeSlot;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HeroSlidesTest extends TestCase
{
    use RefreshDatabase;

    private array $posts;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(RolesSeeder::class);
        $author = User::factory()->create(['name' => 'Pressafrica Media']);
        $this->posts = collect(range(1, 4))->map(function ($i) use ($author) {
            $p = Post::create(['author_id' => $author->id, 'status' => 'published', 'published_at' => now()->subDays($i)]);
            $p->translations()->create(['locale' => 'fr', 'title' => "Article $i", 'slug' => "article-$i", 'excerpt' => "Résumé $i", 'body' => '<p>x</p>']);

            return $p;
        })->all();
        foreach ([1, 2, 3] as $n) {
            HomeSlot::create(['slot' => 'hero', 'position' => $n, 'post_id' => $this->posts[$n - 1]->id]);
        }
        HeroSlide::restoreDefaults();
    }

    private function editor(): User
    {
        $u = User::factory()->create()->assignRole('editor');
        $this->actingAs($u);

        return $u;
    }

    public function test_default_carousel_is_the_prototype_order_with_the_intro_slide(): void
    {
        $this->assertSame(4, HeroSlide::count());
        $this->get('/fr')->assertOk()->assertSeeInOrder([
            'data-slide', 'Article 2', 'data-slide', __('proto.hero_intro_title', [], 'fr'), 'data-slide', 'Article 1', 'data-slide', 'Article 3',
        ])->assertSee('aria-label="'.__('proto.slide', [], 'fr').' 4 / 4"', false);
    }

    public function test_editor_creates_a_free_slide_with_an_upload_and_it_shows_on_the_home_page(): void
    {
        $this->editor();
        $this->post('/admin/featured', [
            'kind' => 'free', 'status' => 'published', 'upload' => UploadedFile::fake()->image('une.jpg', 1600, 900),
            'translations' => ['fr' => ['title' => 'Élections 2026', 'text' => 'Notre dossier', 'cta' => 'Lire le dossier', 'url' => '/fr/politique', 'alt' => 'Urne']],
        ])->assertRedirect('/admin/featured');

        $slide = HeroSlide::latest('id')->first();
        $this->assertSame('image/webp', $slide->media->mime);
        $this->get('/fr')->assertSee('Élections 2026')->assertSee('href="'.url('/fr/politique').'"', false)->assertSee('5 / 5');
        $this->get('/en')->assertSee('Élections 2026', false);   // no English text: French shown (INV-10)
        $this->assertTrue(AuditLog::where(['module' => 'featured', 'action' => 'create'])->exists());
    }

    public function test_article_slide_takes_the_article_texts_unless_overridden(): void
    {
        $this->editor();
        $this->post('/admin/featured', ['kind' => 'article', 'post_id' => $this->posts[3]->id, 'status' => 'published',
            'translations' => ['fr' => ['text' => 'Accroche maison']]])->assertRedirect();
        $this->get('/fr')->assertSee('Article 4')->assertSee('<p class="hero-text">Accroche maison</p>', false)->assertDontSee('<p class="hero-text">Résumé 4</p>', false);
    }

    public function test_reorder_hide_schedule_and_delete(): void
    {
        $this->editor();
        $first = HeroSlide::orderBy('position')->first();
        $this->post("/admin/featured/{$first->id}/move", ['dir' => 'down'])->assertRedirect();
        $this->assertSame(2, $first->fresh()->position);

        $this->put("/admin/featured/{$first->id}", ['kind' => 'article', 'post_id' => $first->post_id, 'status' => 'draft'])->assertRedirect();
        $this->get('/fr')->assertSee('3 / 3');

        $this->put("/admin/featured/{$first->id}", ['kind' => 'article', 'post_id' => $first->post_id, 'status' => 'published',
            'starts_at' => now()->addDay()->format('Y-m-d H:i')])->assertRedirect();
        $this->get('/fr')->assertSee('3 / 3');   // scheduled for tomorrow (Benin time)
        $this->travel(2)->days();
        $this->get('/fr')->assertSee('4 / 4');

        $this->delete("/admin/featured/{$first->id}")->assertRedirect();
        $this->assertSame(['reorder', 'update', 'update', 'delete'], AuditLog::where('module', 'featured')->orderBy('id')->pluck('action')->all());
    }

    public function test_rules_max_six_alt_text_and_free_slide_image(): void
    {
        $this->editor();
        foreach (range(1, 2) as $i) {
            $this->post('/admin/featured', ['kind' => 'article', 'post_id' => $this->posts[3]->id, 'status' => 'published'])->assertRedirect();
        }
        $this->post('/admin/featured', ['kind' => 'article', 'post_id' => $this->posts[3]->id, 'status' => 'published'])->assertSessionHasErrors('status');
        $this->post('/admin/featured', ['kind' => 'free', 'status' => 'draft', 'translations' => ['fr' => ['title' => 'Sans image']]])->assertSessionHasErrors('media_id');
        $m = Media::create(['path' => 'media/x.webp', 'mime' => 'image/webp', 'size' => 1]);
        $this->post('/admin/featured', ['kind' => 'free', 'status' => 'draft', 'media_id' => $m->id, 'translations' => ['fr' => ['title' => 'Sans alt']]])->assertSessionHasErrors('translations.fr.alt');
    }

    public function test_only_administrators_restore_the_default_carousel(): void
    {
        $this->editor();
        HeroSlide::first()->delete();
        $this->post('/admin/featured/restore')->assertForbidden();
        $this->get('/admin/featured')->assertOk()->assertDontSee('Restore default carousel');

        $this->actingAs(User::factory()->create()->assignRole('admin'));
        $this->post('/admin/featured/restore')->assertRedirect('/admin/featured');
        $this->assertSame(4, HeroSlide::count());
        $this->assertTrue(AuditLog::where(['module' => 'featured', 'action' => 'restore'])->exists());

        $this->actingAs(User::factory()->create()->assignRole('author'))->get('/admin/featured')->assertForbidden();
    }

    public function test_a_slide_whose_article_is_unpublished_is_hidden(): void
    {
        $this->posts[1]->update(['status' => 'draft']);
        $this->get('/fr')->assertSee('3 / 3')->assertDontSee('Article 2');
    }
}
