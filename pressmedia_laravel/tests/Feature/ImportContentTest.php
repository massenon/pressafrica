<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\HeroSlide;
use App\Models\Media;
use App\Models\Post;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Small synthetic source with one case per import rule; the real 64+64 run is checked by hand (see PROGRESS.md). */
class ImportContentTest extends TestCase
{
    use RefreshDatabase;

    private string $src;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        $this->seed(RolesSeeder::class);

        $root = storage_path('framework/testing/import-'.uniqid());
        $this->src = "$root/data";
        File::ensureDirectoryExists("$this->src/translations/en");
        File::ensureDirectoryExists("$root/assets/img/posts");
        imagewebp(imagecreatetruecolor(8, 6), "$root/assets/img/posts/cover-1280.webp");

        $body = '<!-- wp:paragraph --><p>Corps de l’article, assez long.</p><!-- /wp:paragraph --><script>x()</script>';
        $post = fn ($id, $slug, $title, $cat, $date, array $extra = []) => $extra + [
            'id' => $id, 'type' => 'post', 'slug' => $slug, 'title' => $title, 'date' => $date, 'modified' => $date,
            'author' => "Koffi\u{a0}HOUNNOU", 'excerpt' => '', 'content' => $body, 'image' => null,
            'categories' => [['tax' => 'category', 'slug' => $cat, 'name' => $cat]], 'tags' => [], 'seo_title' => '', 'seo_desc' => '',
        ];
        File::put("$this->src/articles.json", json_encode([
            $post('10', 'benin-petrole', 'Bénin&nbsp;: pétrole', 'economie-africaine', '2026-01-02 10:00:00',
                ['image' => ['path' => '2026/01/x.jpg', 'alt' => 'Puits'], 'content' => '<p><img src="https://pressafrica.media/wp-content/a.jpg"></p>']),
            $post('11', 'benin-oil', 'Benin: oil', 'african-economy', '2026-01-02 10:05:00'),     // native EN of 10
            $post('12', '4321', 'Togo : crise', 'politique-fr', '2026-01-01 09:00:00'),         // numeric slug
            $post('13', 'tchad-gouvernance', 'Tchad : gouvernance', 'inconnue', '2025-12-31 08:00:00'),
            ['id' => '99', 'type' => 'page', 'slug' => 'contact', 'title' => 'Contact'] + $post('99', 'contact', 'Contact', 'x', '2025-01-01 00:00:00'),
        ]));
        File::put("$this->src/laravel-import.json", json_encode([
            'categories' => [
                ['key' => 'economy', 'fr' => 'Économie', 'en' => 'Economy', 'slug_fr' => 'economie', 'slug_en' => 'economy', 'db' => ['economie-africaine', 'african-economy']],
                ['key' => 'politics', 'fr' => 'Politique', 'en' => 'Politics', 'slug_fr' => 'politique', 'slug_en' => 'politics', 'db' => ['politique-fr']],
            ],
            'pairs' => ['10' => '11'],
            'en' => ['12' => ['title' => 'Togo: crisis', 'excerpt' => 'Lomé — a crisis.'], '13' => ['title' => 'Chad/Governance', 'excerpt' => 'Ndjamena.']],
        ]));
        File::put("$this->src/image-manifest.json", json_encode(['2026/01/x.jpg' => ['widths' => ['1280' => 'assets/img/posts/cover-1280.webp']]]));
        File::put("$this->src/translations/en/12.json", json_encode([
            'title' => 'Togo: a deepening crisis', 'excerpt' => 'Full EN excerpt.', 'seo_title' => 'Togo crisis', 'seo_desc' => 'SEO desc',
            'image_alt' => 'Lomé street', 'body_html' => '<p>'.str_repeat('Full English translation. ', 12).'</p><figure><img src="x.jpg"></figure>',
        ]));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->src));
        parent::tearDown();
    }

    private function import(): void
    {
        $this->artisan('content:import', ['--source' => $this->src])->assertSuccessful();
    }

    private function t(int $legacy, string $locale)
    {
        return Post::where('legacy_id', $legacy)->sole()->translations()->where('locale', $locale)->first();
    }

    public function test_counts_pairs_and_language_rules(): void
    {
        $this->import();

        $this->assertSame(3, Post::count(), 'pages and the native EN half of a pair are not separate posts');
        $this->assertSame(['Bénin : pétrole', 'Benin: oil'], [$this->t(10, 'fr')->title, $this->t(10, 'en')->title]);

        $full = $this->t(12, 'en');
        $this->assertSame(['Togo: a deepening crisis', 'togo-a-deepening-crisis', null, 'SEO desc'], [$full->title, $full->slug, $full->body_locale, $full->seo_description]);
        $this->assertStringNotContainsString('<img', $full->body);

        $stub = $this->t(13, 'en');
        $this->assertSame(['Chad/Governance', 'chad-governance', 'Ndjamena.', 'fr'], [$stub->title, $stub->slug, $stub->excerpt, $stub->body_locale]);
        $this->assertStringContainsString('Corps de l’article', $stub->body, 'French body kept under the EN title');
    }

    public function test_slugs_bodies_dates_authors_and_categories(): void
    {
        $this->import();

        $this->assertSame('togo-crise', $this->t(12, 'fr')->slug, 'numeric WordPress slug re-slugged from the title');
        $this->assertSame('benin-petrole', $this->t(10, 'fr')->slug);
        $body = $this->t(13, 'fr')->body;
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('wp:paragraph', $body);
        $this->assertSame('Corps de l’article, assez long.', $this->t(13, 'fr')->excerpt);

        $p = Post::where('legacy_id', 10)->sole();
        $this->assertSame(['published', '2026-01-02 10:00:00'], [$p->status, $p->published_at->format('Y-m-d H:i:s')]);
        $this->assertSame(['Koffi HOUNNOU', 'koffi-hounnou@authors.invalid', true], [$p->author->name, $p->author->email, $p->author->hasRole('author')]);
        $this->assertSame(['economy'], $p->categories->pluck('key')->all());
        $this->assertSame(1, $p->categories()->count(), 'FR and EN WordPress categories map to one section');
    }

    public function test_covers_are_copied_once_with_alt_text_per_language(): void
    {
        $this->import();
        $this->import();

        $this->assertSame(1, Media::where('path', '!=', HeroSlide::INTRO_IMAGE)->count());
        $m = Media::where('path', '!=', HeroSlide::INTRO_IMAGE)->sole();
        Storage::disk('public')->assertExists($m->path);
        $this->assertSame(['Puits', 'Benin: oil'], [$m->alt_fr, $m->alt_en]);
        $this->assertSame($m->id, Post::where('legacy_id', 10)->value('cover_id'));
    }

    public function test_reimport_is_idempotent_and_anomalies_are_reported_not_fixed(): void
    {
        $this->import();
        $this->import();
        $this->assertSame(3, Post::count());

        $report = json_decode(Storage::disk('local')->get('import-report.json'), true);
        $this->assertSame(['fr' => 3, 'en' => 3], $report['counts']);
        $issues = collect($report['anomalies'])->map(fn ($a) => $a['wp_id'].': '.$a['issue'])->all();
        $this->assertContains('13: no category maps to a site section', $issues);
        $this->assertContains("10: fr source body links images on the old WordPress host (left out by the prototype's cleaning)", $issues);
        $this->assertContains('13: EN has title/excerpt only, body stays French (no translations/en file)', $issues);
        $this->assertStringContainsString('pressafrica.media', $this->t(10, 'fr')->body, 'kept as in the source');
        $this->assertSame(2, AuditLog::where(['action' => 'import', 'user_name' => 'System'])->count());
    }

    public function test_a_missing_source_fails_cleanly(): void
    {
        $this->artisan('content:import', ['--source' => '/nonexistent'])->assertFailed();
    }
}
