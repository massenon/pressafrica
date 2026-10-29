<?php

namespace Tests\Feature;

use App\Models\PageView;
use App\Models\Post;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->post = Post::create(['author_id' => User::factory()->create()->id, 'status' => 'published', 'published_at' => now()->subDay()]);
        $this->post->translations()->create(['locale' => 'fr', 'title' => 'Bénin', 'slug' => 'benin', 'body' => '<p>x</p>']);
    }

    private function visit(string $url, array $headers = []): void
    {
        $this->get($url, $headers + ['User-Agent' => 'Mozilla/5.0 (iPhone) Safari', 'Referer' => 'https://www.google.com/search'])->assertOk();
    }

    public function test_public_visits_are_logged_without_ip_or_cookie(): void
    {
        $this->visit('/fr/articles/benin.html', ['CF-IPCountry' => 'BJ']);
        $this->visit('/fr/recherche.html?q=Pétrole');

        $v = PageView::orderBy('id')->first();
        $this->assertSame(['/fr/articles/benin.html', $this->post->id, 'www.google.com', 'BJ', 'mobile'], [$v->path, $v->post_id, $v->referrer_host, $v->country, $v->device]);
        $this->assertSame(16, strlen($v->visitor));
        $this->assertStringNotContainsString('127.0.0.1', json_encode(PageView::all()));
        $this->assertSame('pétrole', PageView::orderByDesc('id')->first()->search);
        $this->assertSame(1, $this->post->fresh()->views);
    }

    public function test_bots_prefetches_staff_and_errors_are_not_counted(): void
    {
        $this->get('/fr/articles/benin.html', ['User-Agent' => 'Googlebot/2.1']);
        $this->get('/fr/articles/benin.html', ['User-Agent' => 'Mozilla/5.0', 'X-Sec-Purpose' => 'prefetch']);
        $this->get('/fr/articles/nope.html', ['User-Agent' => 'Mozilla/5.0']);
        $this->actingAs(User::factory()->create()->assignRole('editor'))->get('/fr/articles/benin.html', ['User-Agent' => 'Mozilla/5.0']);
        $this->assertSame(0, PageView::count());
    }

    public function test_screen_and_exports_show_the_same_totals(): void
    {
        foreach (range(1, 3) as $_) {
            $this->visit('/fr/articles/benin.html');
        }
        $this->actingAs(User::factory()->create()->assignRole('editor'));
        $this->get('/admin/analytics')->assertOk()->assertSee('first-party visit log')->assertSee('Bénin');

        $json = $this->get('/admin/analytics/export/json')->assertOk()->json();
        $this->assertSame(3, $json['totals']['views']);
        $this->assertSame([['Bénin', 3]], $json['posts']);
        $csv = $this->get('/admin/analytics/export/csv')->assertOk()->getContent();
        $this->assertStringContainsString('total,views,3', $csv);
        $this->actingAs(User::factory()->create()->assignRole('author'))->get('/admin/analytics')->assertForbidden();
    }

    public function test_ga4_is_off_by_default_and_consent_gated_when_on(): void
    {
        $this->get('/fr/index.html')->assertDontSee('googletagmanager');
        Settings::save(['ga4Enabled' => true, 'ga4MeasurementId' => 'G-TEST1234']);
        $html = $this->get('/fr/index.html')->getContent();
        $this->assertStringContainsString('<script type="text/plain" data-consent-cat="analytics" data-src="https://www.googletagmanager.com/gtag/js?id=G-TEST1234">', $html);
        $this->assertStringContainsString("analytics_storage:'denied'", $html);
    }

    public function test_ga4_report_through_the_data_api_and_fallback_on_failure(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($key, $pem);
        Settings::save(['ga4Enabled' => true, 'ga4PropertyId' => '123456', 'analyticsSource' => 'ga4',
            'ga4Credentials' => json_encode(['client_email' => 'svc@x.iam.gserviceaccount.com', 'private_key' => $pem])]);
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'tok']),
            'analyticsdata.googleapis.com/*' => Http::sequence()
                ->push(['rows' => [['metricValues' => [['value' => '42'], ['value' => '7']]]]])
                ->push(['rows' => [['dimensionValues' => [['value' => '20261028']], 'metricValues' => [['value' => '42']]]]])
                ->whenEmpty(Http::response(['rows' => []])),
        ]);
        $this->actingAs(User::factory()->create()->assignRole('admin'));
        $json = $this->get('/admin/analytics/export/json')->json();
        $this->assertSame(['ga4', 42, 7, [['2026-10-28', 42]]], [$json['source'], $json['totals']['views'], $json['totals']['visitors'], $json['daily']]);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'properties/123456:runReport') && $r->hasHeader('Authorization', 'Bearer tok'));
    }

    public function test_an_unreachable_ga4_falls_back_to_the_first_party_log(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        openssl_pkey_export($key, $pem);
        Settings::save(['ga4Enabled' => true, 'ga4PropertyId' => '123456', 'analyticsSource' => 'ga4',
            'ga4Credentials' => json_encode(['client_email' => 'svc@x.iam.gserviceaccount.com', 'private_key' => $pem])]);
        Http::fake(['*' => Http::response([], 500)]);
        $this->actingAs(User::factory()->create()->assignRole('admin'));
        $this->get('/admin/analytics')->assertOk()->assertSee('GA4 could not be read')->assertSee('first-party visit log');
    }
}
