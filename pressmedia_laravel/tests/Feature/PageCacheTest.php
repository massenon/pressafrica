<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PageView;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.pages_enabled' => true, 'cache.stores.pages' => ['driver' => 'array']]);
        $this->seed(RolesSeeder::class);
    }

    public function test_public_pages_are_served_from_cache_with_fresh_tokens_and_still_counted(): void
    {
        $this->get('/fr')->assertOk()->assertHeader('X-Page-Cache', 'miss');
        $hit = $this->get('/fr')->assertOk()->assertHeader('X-Page-Cache', 'hit');

        $this->assertStringNotContainsString('%%PAGE_CACHE_CSRF%%', $hit->getContent());
        $this->assertStringContainsString('<meta name="csrf-token" content="'.csrf_token().'">', $hit->getContent());
        $this->assertSame(2, PageView::count());
    }

    public function test_a_content_change_empties_the_cache_and_staff_bypass_it(): void
    {
        $this->get('/fr')->assertHeader('X-Page-Cache', 'miss');
        $this->get('/fr')->assertHeader('X-Page-Cache', 'hit');

        Category::create(['key' => 'test-cache', 'layout' => 'archive', 'position' => 99]);
        $this->get('/fr')->assertHeader('X-Page-Cache', 'miss');

        $this->actingAs(User::factory()->create()->assignRole('admin'));
        $this->assertFalse($this->get('/fr')->headers->has('X-Page-Cache'));
    }

    public function test_page_views_do_not_empty_the_cache(): void
    {
        // Every visit writes a page view; that write must not invalidate the page it is counting.
        $this->get('/en')->assertHeader('X-Page-Cache', 'miss');
        $this->get('/en')->assertHeader('X-Page-Cache', 'hit');
        $this->get('/en')->assertHeader('X-Page-Cache', 'hit');
        $this->assertSame(3, PageView::count());
    }
}
