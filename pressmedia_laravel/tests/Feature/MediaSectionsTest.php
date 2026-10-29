<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\MediaItem;
use App\Models\Post;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MediaSectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        foreach ([['podcasts', 'podcasts'], ['videos', 'videos'], ['magazine', 'magazine'], ['radios', 'radios-africaines'], ['television', 'television-africaine'], ['online-press', 'presse-en-ligne']] as [$k, $slug]) {
            Category::create(['key' => $k, 'layout' => $k === 'online-press' ? 'archive' : 'landing'])
                ->translations()->create(['locale' => 'fr', 'name' => ucfirst($k), 'slug' => $slug]);
        }
    }

    private function item(string $type, string $key, array $data = [], array $fr = [], string $status = 'published', ?string $date = '2026-10-01'): MediaItem
    {
        $i = MediaItem::create(['type' => $type, 'key' => $key, 'status' => $status, 'published_on' => $date, 'data' => $data]);
        $i->translations()->create(['locale' => 'fr'] + $fr);

        return $i;
    }

    public function test_empty_sections_show_the_coming_soon_block(): void
    {
        $this->get('/fr/magazine')->assertOk()->assertSee(__('proto.landing_title', [], 'fr'));
    }

    public function test_podcasts_page_with_latest_episode_player_and_shows(): void
    {
        $this->item('podcast_show', 'grand-format', [], ['title' => 'Le Grand Format', 'description' => 'Chaque semaine']);
        $this->item('podcast', 'ep-002', ['show' => 'grand-format', 'duration' => 1500], ['title' => 'Épisode récent', 'file' => 'assets/audio/ep-002-fr.mp3'], 'published', '2026-10-20');
        $this->item('podcast', 'ep-001', ['show' => 'grand-format', 'duration' => 60], ['title' => 'Épisode ancien', 'file' => 'assets/audio/ep-001-fr.mp3'], 'published', '2026-10-10');
        $this->item('podcast', 'ep-003', ['show' => 'grand-format'], ['title' => 'Brouillon'], 'draft');

        $html = $this->get('/fr/podcasts')->assertOk()
            ->assertSeeInOrder(['class="pod-hero', 'Épisode récent', 'src="'.url('assets/audio/ep-002-fr.mp3').'"', 'class="pod-ep', 'Épisode ancien'])
            ->assertSee('25 '.__('proto.pod_min', [], 'fr'))->assertDontSee('Brouillon')->assertDontSee(__('proto.landing_title', [], 'fr'))
            ->getContent();
        $this->assertStringContainsString('"@type":"PodcastSeries"', $html);
    }

    public function test_directories_honour_the_outbound_links_setting(): void
    {
        $this->item('radio', 'rad-001', ['name' => 'RFI Afrique', 'country' => 'FR', 'language' => 'fr', 'genre' => 'public', 'website' => 'https://www.rfi.fr'], ['description' => 'Radio internationale'], 'published', null);
        $this->get('/fr/radios-africaines')->assertOk()->assertSee('RFI Afrique')->assertSee(__('media.genres.public', [], 'fr'))
            ->assertSee('href="https://www.rfi.fr" target="_blank" rel="noopener noreferrer nofollow"', false);

        Settings::save(['directoryLinks' => false]);   // owner's answer to Q5 can switch them off
        $this->get('/fr/radios-africaines')->assertDontSee('https://www.rfi.fr');
    }

    public function test_magazine_lists_issue_contents_in_order(): void
    {
        $author = User::factory()->create();
        [$a, $b] = collect(['Premier', 'Second'])->map(function ($t) use ($author) {
            $p = Post::create(['author_id' => $author->id, 'status' => 'published', 'published_at' => now()->subDay()]);
            $p->translations()->create(['locale' => 'fr', 'title' => $t, 'slug' => strtolower($t), 'body' => 'x']);

            return $p;
        })->all();
        $mag = $this->item('magazine', 'mag-001', ['number' => 1], ['title' => 'Numéro un']);
        $mag->posts()->sync([$b->id => ['position' => 1], $a->id => ['position' => 2]]);

        $this->get('/fr/magazine')->assertOk()->assertSee('Numéro un')->assertSeeInOrder(['mag-toc', 'Second', 'Premier'])->assertSee('"@type":"Periodical"', false);
    }

    public function test_admin_creates_a_video_with_an_upload_and_it_is_journaled(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create()->assignRole('editor'));
        $poster = UploadedFile::fake()->image('poster.png', 20, 10);

        $this->post('/admin/media-items/video', [
            'key' => 'vid-100', 'status' => 'published', 'published_on' => '2026-10-28',
            'data' => ['topic' => 'economy', 'duration' => 75, 'file' => 'assets/video/vid-100.mp4'],
            'upload' => ['poster' => $poster],
            'translations' => ['fr' => ['title' => 'Nouvelle vidéo', 'description' => 'Desc']],
        ])->assertRedirect();

        $v = MediaItem::where('key', 'vid-100')->sole();
        $this->assertSame(['economy', 75], [$v->get('topic'), $v->get('duration')]);
        Storage::disk('public')->assertExists($v->get('poster'));
        $this->assertTrue(AuditLog::where(['module' => 'videos', 'action' => 'create'])->exists());
        $this->get('/fr/videos')->assertOk()->assertSee('Nouvelle vidéo')->assertSee('1:15');
    }

    public function test_upload_types_rights_and_publish_rule(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('editor'));
        $fake = UploadedFile::fake()->createWithContent('clip.mp4', '<?php echo 1;');
        $this->post('/admin/media-items/video', ['key' => 'vid-x', 'status' => 'draft', 'upload' => ['file' => $fake], 'translations' => ['fr' => ['title' => 'X']]])
            ->assertSessionHasErrors('upload.file');

        $this->actingAs(User::factory()->create()->assignRole('author'))->get('/admin/media-items/radio')->assertForbidden();
        $this->get('/admin/media-items/unknown')->assertNotFound();

        Role::create(['name' => 'contrib', 'guard_name' => 'web'])->syncPermissions(['radios.view', 'radios.create']);
        $this->actingAs(User::factory()->create()->assignRole('contrib'));
        $this->post('/admin/media-items/radio', ['key' => 'rad-x', 'status' => 'published', 'data' => ['name' => 'Radio X']])->assertSessionHasErrors('status');
        $this->post('/admin/media-items/radio', ['key' => 'rad-x', 'status' => 'draft', 'data' => ['name' => 'Radio X']])->assertRedirect();
    }

    public function test_import_media_from_the_prototype_data(): void
    {
        $this->artisan('content:import-media')->assertSuccessful();
        $this->assertSame(['magazine' => 6, 'podcast' => 8, 'podcast_show' => 3, 'radio' => 6, 'tv' => 8, 'video' => 8],
            MediaItem::selectRaw('type, count(*) n')->groupBy('type')->orderBy('type')->pluck('n', 'type')->all());
        $this->assertSame('assets/audio/ep-001-en.mp3', MediaItem::where('key', 'ep-001')->sole()->translation('en')->file);
        $this->artisan('content:import-media')->assertSuccessful();
        $this->assertSame(39, MediaItem::count(), 're-runnable');
    }
}
