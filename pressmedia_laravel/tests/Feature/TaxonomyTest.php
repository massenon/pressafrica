<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxonomyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->actingAs(User::factory()->create()->assignRole('editor'));
    }

    private function category(string $key, ?int $parent = null): Category
    {
        $this->post('/admin/categories', ['key' => $key, 'parent_id' => $parent, 'translations' => ['fr' => ['name' => ucfirst($key)]]])->assertRedirect();

        return Category::where('key', $key)->sole();
    }

    public function test_categories_nest_without_limit_and_cannot_form_a_cycle(): void
    {
        $a = $this->category('afrique');
        $b = $this->category('ouest', $a->id);
        $c = $this->category('benin', $b->id);
        $this->get('/admin/categories')->assertOk()->assertSeeInOrder(['Afrique', 'Ouest', 'Benin']);

        $this->put("/admin/categories/{$a->id}", ['key' => 'afrique', 'parent_id' => $c->id, 'translations' => ['fr' => ['name' => 'Afrique']]])
            ->assertSessionHasErrors('parent_id');
        $this->assertSame(3, AuditLog::where(['module' => 'categories', 'action' => 'create'])->count());
    }

    public function test_a_category_with_children_or_posts_is_not_deleted(): void
    {
        $a = $this->category('afrique');
        $this->category('ouest', $a->id);
        $this->delete("/admin/categories/{$a->id}")->assertSessionHasErrors('category');
        $this->assertNotNull($a->fresh());
    }

    public function test_duplicate_category_slugs_are_rejected(): void
    {
        $this->category('politique');
        $this->post('/admin/categories', ['key' => 'politique-2', 'translations' => ['fr' => ['name' => 'Politique']]])->assertSessionHasErrors('translations.fr.slug');
    }

    public function test_tags_are_merged_with_usage_kept(): void
    {
        $post = Post::create(['author_id' => auth()->id(), 'status' => 'draft']);
        [$a, $b] = [Tag::create(['name' => 'Pétrole', 'slug' => 'petrole']), Tag::create(['name' => 'Oil', 'slug' => 'oil'])];
        $post->tags()->attach($b);

        $this->get('/admin/tags')->assertOk()->assertSee('Oil');
        $this->post("/admin/tags/{$b->id}/merge", ['into' => $a->id])->assertRedirect();

        $this->assertNull($b->fresh());
        $this->assertSame([$a->id], $post->tags()->pluck('tags.id')->all());
        $this->assertSame('into petrole', AuditLog::where('action', 'merge')->sole()->detail);
        $this->post("/admin/tags/{$a->id}/merge", ['into' => $a->id])->assertSessionHasErrors('into');
    }

    public function test_tags_are_created_renamed_and_deleted(): void
    {
        $this->post('/admin/tags', ['name' => 'Élections'])->assertRedirect();
        $tag = Tag::where('slug', 'elections')->sole();
        $this->post('/admin/tags', ['name' => 'elections'])->assertSessionHasErrors('slug');
        $this->put("/admin/tags/{$tag->id}", ['name' => 'Élections 2026']);
        $this->assertSame('elections-2026', $tag->fresh()->slug);
        $this->delete("/admin/tags/{$tag->id}");
        $this->assertSame(0, Tag::count());
    }
}
