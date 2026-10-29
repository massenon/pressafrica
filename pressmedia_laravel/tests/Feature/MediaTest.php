<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(RolesSeeder::class);
        $this->actingAs(User::factory()->create()->assignRole('editor'));
    }

    public function test_jpeg_and_png_are_converted_to_webp_with_random_names_and_capped_width(): void
    {
        $this->post('/admin/media', [
            'files' => [UploadedFile::fake()->image('Photo du Président.jpg', 3000, 2000), UploadedFile::fake()->image('logo.png', 400, 200)],
            'alt_fr' => 'Le président', 'alt_en' => 'The president',
        ])->assertRedirect();

        $this->assertSame(2, Media::count());
        foreach (Media::all() as $m) {
            $this->assertSame('image/webp', $m->mime);
            $this->assertMatchesRegularExpression('#^media/\d{4}/\d{2}/[A-Za-z0-9]{32}\.webp$#', $m->path);
            Storage::disk('public')->assertExists($m->path);
            $this->assertSame('image/webp', getimagesizefromstring(Storage::disk('public')->get($m->path))['mime']);
        }
        $big = Media::orderBy('id')->first();
        $this->assertSame([2400, 1600], [$big->width, $big->height]);
        $this->assertSame('The president', $big->alt_en);
        $this->assertSame(2, AuditLog::where(['module' => 'media', 'action' => 'create'])->count());
    }

    public function test_gif_and_pdf_are_kept_as_uploaded(): void
    {
        $this->post('/admin/media', ['files' => [UploadedFile::fake()->image('anim.gif', 10, 10), UploadedFile::fake()->createWithContent('rapport.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF")]]);
        $this->assertEqualsCanonicalizing(['image/gif', 'application/pdf'], Media::pluck('mime')->all());
    }

    public function test_disguised_or_oversized_files_are_rejected(): void
    {
        $php = UploadedFile::fake()->createWithContent('photo.jpg', '<?php echo "pwned";');
        $this->post('/admin/media', ['files' => [$php]])->assertSessionHasErrors('files.0');
        $this->post('/admin/media', ['files' => [UploadedFile::fake()->create('big.pdf', 11000, 'application/pdf')]])->assertSessionHasErrors('files.0');
        $this->assertSame(0, Media::count());
    }

    public function test_alt_text_is_edited_and_files_are_deleted_unless_used_as_cover(): void
    {
        $this->post('/admin/media', ['files' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]]);
        [$a, $b] = Media::orderBy('id')->get()->all();

        $this->put("/admin/media/{$a->id}", ['alt_fr' => 'Cotonou', 'alt_en' => 'Cotonou city']);
        $this->assertSame('Cotonou city', $a->fresh()->alt_en);
        $this->get('/admin/media?q=Cotonou')->assertOk()->assertSee('Cotonou city')->assertDontSee($b->path);

        Post::create(['author_id' => auth()->id(), 'status' => 'draft', 'cover_id' => $a->id]);
        $this->delete("/admin/media/{$a->id}")->assertSessionHasErrors('media');
        $this->delete("/admin/media/{$b->id}")->assertRedirect();
        Storage::disk('public')->assertMissing($b->path);
        $this->assertNull($b->fresh());
    }

    public function test_moderators_cannot_open_the_library(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('moderator'))->get('/admin/media')->assertForbidden();
    }
}
