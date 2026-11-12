<?php

namespace Tests\Feature;

use App\Livewire\MediaPicker;
use App\Livewire\PostPicker;
use App\Models\Comment;
use App\Models\Media;
use App\Models\MediaItem;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AdminConsoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(RolesSeeder::class);
    }

    private function article(string $title, string $status = 'published'): Post
    {
        $p = Post::create(['author_id' => User::factory()->create()->id, 'status' => $status, 'published_at' => now()->subDay()]);
        $p->translations()->create(['locale' => 'fr', 'title' => $title, 'slug' => Str::slug($title), 'body' => '<p>x</p>']);

        return $p;
    }

    public function test_the_console_has_the_prototype_shell_and_the_sidebar_follows_rights(): void
    {
        $this->article('En attente', 'pending');
        Comment::create(['post_id' => Post::first()->id, 'locale' => 'fr', 'author' => 'A', 'email' => 'a@x.test', 'body' => 'b', 'status' => 'pending', 'consent_at' => now()]);

        $this->actingAs(User::factory()->create()->assignRole('admin'))->get('/admin')->assertOk()
            ->assertSee('<aside class="a-side" id="sidebar"', false)->assertSee('<main id="main" class="a-view"', false)
            ->assertSee('/assets/css/admin.min.css', false)->assertSee('wire:navigate', false)
            ->assertSeeInOrder(['Overview', 'Newsroom', 'Channels', 'Audience', 'Site', 'System'])
            ->assertSee('aria-label="1 posts pending review"', false)->assertSee('aria-label="1 comments pending moderation"', false);

        $this->actingAs(User::factory()->create()->assignRole('author'))->get('/admin')->assertOk()
            ->assertSee('>Posts<', false)->assertDontSee('>Settings<', false)->assertDontSee('>Audit log<', false)->assertDontSee('>Comments<', false);
    }

    public function test_media_picker_chooses_uploads_and_checks_rights(): void
    {
        $m = Media::create(['path' => 'media/a.webp', 'mime' => 'image/webp', 'size' => 1, 'alt_fr' => 'Cotonou']);
        $this->actingAs(User::factory()->create()->assignRole('editor'));

        Livewire::test(MediaPicker::class, ['name' => 'cover_id'])
            ->set('open', true)->set('q', 'Coto')->assertSee('Cotonou')
            ->call('choose', $m->id)->assertSet('value', $m->id)->assertSet('open', false)
            ->set('open', true)->set('upload', UploadedFile::fake()->image('n.jpg', 50, 40))->set('uploadAlt', 'Nouvelle')
            ->call('save')->assertHasNoErrors();
        $this->assertSame('image/webp', Media::where('alt_fr', 'Nouvelle')->sole()->mime);

        Livewire::test(MediaPicker::class)->set('upload', UploadedFile::fake()->createWithContent('x.jpg', '<?php 1;'))->set('uploadAlt', 'x')
            ->call('save')->assertHasErrors('upload');
        Livewire::test(MediaPicker::class, ['mode' => 'insert'])->call('choose', $m->id)->assertDispatched('media-picked', url: parse_url($m->url(), PHP_URL_PATH));

        $this->actingAs(User::factory()->create()->assignRole('moderator'));
        Livewire::test(MediaPicker::class)->assertForbidden();
    }

    public function test_post_picker_searches_by_title_and_keeps_the_order(): void
    {
        [$a, $b, $c] = [$this->article('Bénin pétrole'), $this->article('Bénin coton'), $this->article('Togo')];
        $this->actingAs(User::factory()->create()->assignRole('editor'));

        Livewire::test(PostPicker::class, ['name' => 'articles', 'multiple' => true])
            ->set('q', 'Bénin')->assertSee('Bénin pétrole')->assertDontSee('Togo')
            ->call('add', $a->id)->call('add', $b->id)->call('move', $b->id, -1)->assertSet('selected', [$b->id, $a->id])
            ->call('remove', $b->id)->assertSet('selected', [$a->id]);
        Livewire::test(PostPicker::class)->call('add', $a->id)->call('add', $c->id)->assertSet('selected', [$c->id]);   // single mode
    }

    public function test_magazine_contents_and_post_cover_come_from_the_pickers(): void
    {
        [$a, $b] = [$this->article('Premier'), $this->article('Second')];
        $m = Media::create(['path' => 'media/c.webp', 'mime' => 'image/webp', 'size' => 1, 'alt_fr' => 'Couverture']);
        $this->actingAs(User::factory()->create()->assignRole('editor'));

        $this->post('/admin/media-items/magazine', ['key' => 'mag-9', 'status' => 'draft', 'articles' => [$b->id, $a->id],
            'translations' => ['fr' => ['title' => 'N° 9']]])->assertRedirect();
        $this->assertSame([$b->id, $a->id], MediaItem::where('key', 'mag-9')->sole()->posts->pluck('id')->all());

        $this->put("/admin/posts/{$a->id}", ['status' => 'published', 'cover_id' => $m->id, 'translations' => ['fr' => ['title' => 'Premier', 'body' => '<p>x</p>']]])->assertRedirect();
        $this->assertSame($m->id, $a->fresh()->cover_id);
        $this->get("/admin/posts/{$a->id}/edit")->assertOk()->assertSee('data-rte', false)->assertSee('/assets/vendor/quill/quill.js', false)->assertSee('data-quill', false)->assertSee('Couverture');
    }

    public function test_quill_semantic_html_survives_the_sanitiser(): void
    {
        // What Quill 2 getSemanticHTML() produces for the toolbar's formats.
        $html = '<h2>Intertitre</h2><h3>Sous-titre</h3><p><strong>Gras</strong> <em>italique</em> <u>souligné</u> <a href="https://example.org" rel="noopener noreferrer" target="_blank">lien</a></p>'
            .'<blockquote>Citation</blockquote><ol><li>Un</li><li>Deux</li></ol><ul><li>Puce</li></ul><p><img src="/storage/media/2026/10/x.webp" alt="Photo"></p>';
        $clean = clean($html);
        foreach (['<h2>Intertitre</h2>', '<h3>Sous-titre</h3>', '<strong>Gras</strong>', '<em>italique</em>', '<u>souligné</u>', '<a href="https://example.org">lien</a>',
            '<blockquote>Citation</blockquote>', '<ol><li>Un</li><li>Deux</li></ol>', '<ul><li>Puce</li></ul>', '<img src="/storage/media/2026/10/x.webp" alt="Photo"'] as $kept) {
            $this->assertStringContainsString($kept, $clean);
        }
        $this->assertStringNotContainsString('onerror', clean('<img src="x" onerror="alert(1)">'));
    }
}
