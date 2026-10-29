<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\HomeSlot;
use App\Models\MediaItem;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\User;
use Database\Seeders\DemoCommentsSeeder;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_full_seed_populates_every_part_of_the_site(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $this->seed();

        $this->assertSame(64, Post::published()->count());
        $this->assertSame(['en' => 64, 'fr' => 64], PostTranslation::selectRaw('locale, count(*) n')->groupBy('locale')->pluck('n', 'locale')->all());
        $this->assertSame(64, Post::whereNotNull('cover_id')->count());
        $this->assertSame(15, HomeSlot::count());
        $this->assertSame(39, MediaItem::count());
        $this->assertSame(9, Comment::count());
        foreach (['admin', 'editor', 'author', 'moderator'] as $role) {
            $this->assertTrue(User::where('email', "$role@pressmedia.test")->sole()->hasRole($role));
        }

        $this->get('/fr/index.html')->assertOk();
        $this->get('/fr/podcasts.html')->assertOk()->assertDontSee(__('proto.landing_title', [], 'fr'));
    }

    public function test_demo_accounts_and_comments_are_never_seeded_in_production(): void
    {
        $this->seed(RolesSeeder::class);
        $this->app['env'] = 'production';
        (new DemoUsersSeeder)->run();
        (new DemoCommentsSeeder)->run();
        $this->assertSame(0, User::count());
        $this->assertSame(0, Comment::count());
    }
}
