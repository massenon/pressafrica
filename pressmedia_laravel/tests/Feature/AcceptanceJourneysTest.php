<?php

namespace Tests\Feature;

use App\Models\AdZone;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Media;
use App\Models\MediaItem;
use App\Models\Post;
use App\Models\User;
use App\Support\Backup;
use App\Support\Settings;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PRD acceptance journeys R1 to R9 (docs/PRD.md §8), end to end through the real routes.
 * R10 (widths and browsers) is a browser check: tools/qa/acceptance/r10.js; see docs/RECETTE.md.
 */
class AcceptanceJourneysTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        $this->seed(RolesSeeder::class);
        foreach ([['magazine', 'magazine', 'landing'], ['radios', 'radios-africaines', 'landing'], ['television', 'television-africaine', 'landing'], ['politics', 'politique', 'thematic'], ['online-press', 'presse-en-ligne', 'archive']] as [$k, $slug, $layout]) {
            $c = Category::create(['key' => $k, 'layout' => $layout]);
            $c->translations()->create(['locale' => 'fr', 'name' => ucfirst($k), 'slug' => $slug]);
            $c->translations()->create(['locale' => 'en', 'name' => 'EN '.$k, 'slug' => "en-$slug"]);
        }
    }

    private function as(string $role, array $extra = []): User
    {
        $u = User::factory()->create($extra)->assignRole($role);
        $this->actingAs($u);

        return $u;
    }

    private function article(string $slug = 'benin'): Post
    {
        $p = Post::create(['author_id' => User::factory()->create()->id, 'status' => 'published', 'published_at' => now()->subDay()]);
        $p->translations()->create(['locale' => 'fr', 'title' => 'Bénin', 'slug' => $slug, 'body' => '<p>Un</p><p>Deux</p><p>Trois</p>']);
        $p->categories()->sync([Category::where('key', 'politics')->value('id')]);

        return $p;
    }

    public function test_r1_a_section_entry_end_to_end(): void
    {
        $this->as('editor');
        $this->post('/admin/media-items/radio', ['key' => 'rad-100', 'status' => 'published', 'data' => ['name' => 'Radio Test', 'country' => 'BJ', 'language' => 'fr', 'genre' => 'news'],
            'translations' => ['fr' => ['description' => 'Radio béninoise'], 'en' => ['description' => 'Beninese radio']]])->assertRedirect();
        $this->get('/fr/radios-africaines.html')->assertSee('Radio Test')->assertDontSee(__('proto.landing_title', [], 'fr'));
        $this->get('/en/en-radios-africaines.html')->assertSee('Beninese radio');
        $this->assertStringContainsString(url('fr/radios-africaines.html'), $this->get('/sitemap.xml')->getContent());

        $item = MediaItem::where('key', 'rad-100')->sole();
        $this->put("/admin/media-items/radio/{$item->id}", ['key' => 'rad-100', 'status' => 'archived', 'data' => ['name' => 'Radio Test'], 'translations' => ['fr' => ['description' => 'x']]])->assertRedirect();
        $this->get('/fr/radios-africaines.html')->assertDontSee('Radio Test');
        $this->get('/en/en-radios-africaines.html')->assertDontSee('Radio Test');
        $this->assertSame(['create', 'update'], AuditLog::where('module', 'radios')->orderBy('id')->pluck('action')->all());
    }

    public function test_r2_measurement_waits_for_consent_and_stops_when_the_id_is_removed(): void
    {
        $this->article();
        $this->get('/fr/articles/benin.html')->assertDontSee('googletagmanager');                        // GA4 off by default (Q7)
        Settings::save(['ga4Enabled' => true, 'ga4MeasurementId' => 'G-R2TEST99']);
        $html = $this->get('/fr/articles/benin.html')->getContent();
        $this->assertStringContainsString('type="text/plain" data-consent-cat="analytics" data-src="https://www.googletagmanager.com/gtag/js?id=G-R2TEST99"', $html);
        $this->assertStringContainsString('id="cookie-banner"', $html);                                   // banner present; inert until consent
        Settings::save(['ga4MeasurementId' => '']);
        $this->get('/fr/articles/benin.html')->assertDontSee('googletagmanager');
    }

    public function test_r3_visits_to_report_with_matching_export(): void
    {
        $this->article();
        foreach (['/fr/articles/benin.html', '/fr/politique.html', '/fr/recherche.html?q=elections'] as $u) {
            $this->get($u, ['User-Agent' => 'Mozilla/5.0', 'CF-IPCountry' => 'BJ'])->assertOk();
        }
        $this->as('editor');
        $this->get('/admin/analytics?days=7')->assertOk()->assertSee('BJ')->assertSee('elections')->assertSee('politique');
        $json = $this->get('/admin/analytics/export/json?days=7')->json();
        $csv = $this->get('/admin/analytics/export/csv?days=7')->getContent();
        $this->assertSame(3, $json['totals']['views']);
        $this->assertStringContainsString('total,views,3', $csv);
    }

    public function test_r4_comment_moderation(): void
    {
        $this->article();
        $this->postJson('/fr/articles/benin/comments', ['comment' => 'Merci pour ce dossier', 'author' => 'Awa', 'email' => 'awa@x.test', 'consent' => '1',
            'ft' => Crypt::encryptString((string) now()->subSeconds(10)->getTimestamp())])->assertCreated();
        $c = Comment::sole();
        $this->as('author');
        $this->get('/admin/comments')->assertForbidden();
        $this->as('moderator');
        $this->get('/admin/comments')->assertSee('Merci pour ce dossier');
        $this->put("/admin/comments/{$c->id}", ['status' => 'approved'])->assertRedirect();
        auth()->logout();
        $this->get('/fr/articles/benin.html')->assertSee('Merci pour ce dossier')->assertDontSee('awa@x.test');
        $this->assertTrue(AuditLog::where(['module' => 'comments', 'action' => 'approve'])->exists());
    }

    public function test_r5_scheduling(): void
    {
        $author = $this->as('author');
        $this->post('/admin/posts', ['status' => 'pending', 'categories' => [Category::where('key', 'politics')->value('id')], 'translations' => ['fr' => ['title' => 'Programmé', 'body' => '<p>x</p>']]])->assertRedirect();
        $post = Post::sole();
        $this->as('editor');
        $this->put("/admin/posts/{$post->id}", ['status' => 'scheduled', 'published_at' => now()->addHour()->format('Y-m-d H:i'), 'categories' => [Category::where('key', 'politics')->value('id')],
            'translations' => ['fr' => ['title' => 'Programmé', 'body' => '<p>x</p>']]])->assertRedirect();
        auth()->logout();
        $this->get('/fr/politique.html')->assertDontSee('Programmé');
        $this->travel(2)->hours();
        $this->artisan('posts:publish-scheduled');
        $this->get('/fr/politique.html')->assertSee('Programmé');
        $this->assertStringContainsString('programme', $this->get('/sitemap.xml')->getContent());
        $this->assertSame(['create', 'update', 'publish'], AuditLog::where('module', 'posts')->orderBy('id')->pluck('action')->all());
    }

    public function test_r6_ad_click_and_ctr_in_both_screens(): void
    {
        $this->article();
        AdZone::ensureDefaults();
        $m = Media::create(['path' => 'media/b.webp', 'mime' => 'image/webp', 'size' => 1, 'width' => 300, 'height' => 250, 'alt_fr' => 'Sponsor']);
        $zone = AdZone::where('key', 'sidebar_article')->sole();
        $zone->update(['active' => true, 'desktop' => ['enabled' => true, 'type' => 'banner', 'media_id' => $m->id, 'url' => 'https://s.example'], 'mobile' => ['enabled' => false, 'type' => 'banner']]);
        $this->get('/fr/articles/benin.html');
        $this->get("/ad/{$zone->id}/desktop")->assertRedirect('https://s.example');
        $this->as('admin');
        $this->get('/admin/ads')->assertSee('100.00 %');
        $this->assertSame([['Article sidebar', 1, 1, 100]], $this->get('/admin/analytics/export/json')->json('ads'));
    }

    public function test_r7_custom_role_sees_only_its_module(): void
    {
        $this->as('admin');
        $this->post('/admin/roles', ['name' => 'radio'])->assertRedirect();
        $this->put('/admin/roles/radio', ['permissions' => ['radios.view', 'radios.create', 'radios.edit']])->assertRedirect();
        $this->as('radio');
        $html = $this->get('/admin')->assertOk()->getContent();
        preg_match('#<nav class="a-nav".*?</nav>#s', $html, $nav);
        $this->assertStringContainsString('African radios', $nav[0]);
        foreach (['>Posts<', '>Settings<', '>Comments<', '>Audit log<', '>Media library<'] as $other) {
            $this->assertStringNotContainsString($other, $nav[0]);
        }
        $this->get('/admin/settings')->assertForbidden();
    }

    public function test_r8_backup_and_restore_keep_the_counts(): void
    {
        $this->article();
        Comment::create(['post_id' => Post::first()->id, 'locale' => 'fr', 'author' => 'A', 'email' => 'a@x.test', 'body' => 'b', 'status' => 'approved', 'consent_at' => now()]);
        $this->as('admin');
        $counts = fn () => [Post::count(), User::count(), Comment::count()];
        $before = $counts();
        $this->post('/admin/backups')->assertRedirect();
        $file = collect(Backup::list())->first()['name'];
        $this->get("/admin/backups/$file")->assertOk();
        Comment::query()->forceDelete();
        Post::query()->forceDelete();
        $this->post("/admin/backups/$file/restore", ['confirm' => 'RESTORE'])->assertRedirect();
        $this->assertSame($before, $counts());
        $this->assertSame(['backup', 'download', 'restore'], AuditLog::where('module', 'backups')->orderBy('id')->pluck('action')->all());
    }

    public function test_r9_export_then_erasure_keeps_articles_under_former_author(): void
    {
        $writer = User::factory()->create(['name' => 'Koffi Auteur', 'email' => 'koffi@x.test'])->assignRole('author');
        $p = $this->article();
        $p->update(['author_id' => $writer->id]);
        $this->as('admin');
        $this->assertSame('Koffi Auteur', $this->get('/admin/privacy/export?email=koffi@x.test')->json('staff_account.name'));
        $this->post('/admin/privacy/erase', ['email' => 'koffi@x.test', 'confirm' => 'koffi@x.test'])->assertRedirect();
        $writer->refresh();
        $this->assertSame([__('site.former_author'), [], false], [$writer->name, $writer->getRoleNames()->all(), str_contains($writer->email, 'koffi')]);
        auth()->logout();
        $this->get('/fr/articles/benin.html')->assertOk()->assertSee(__('site.former_author', [], 'fr'))->assertDontSee('Koffi Auteur');
        $this->assertStringNotContainsString('koffi', (string) AuditLog::where('module', 'privacy')->pluck('target')->implode(' '));
    }
}
