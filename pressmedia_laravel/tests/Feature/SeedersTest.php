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
            $this->assertTrue(User::where('email', "$role@pressafrica.media")->sole()->hasRole($role));
        }

        $this->get('/fr/index.html')->assertOk();
        $this->get('/fr/podcasts.html')->assertOk()->assertDontSee(__('proto.landing_title', [], 'fr'));
    }

    public function test_production_gets_the_staff_accounts_but_no_demo_comments(): void
    {
        $this->seed(\Database\Seeders\RolesSeeder::class);
        $this->app['env'] = 'production';
        (new \Database\Seeders\DemoUsersSeeder)->run();
        (new \Database\Seeders\DemoCommentsSeeder)->run();
        $this->assertSame(4, User::count());
        $this->assertSame(0, Comment::count());
    }

    public function test_re_seeding_never_resets_a_changed_password_or_role(): void
    {
        $this->seed(\Database\Seeders\RolesSeeder::class);
        (new \Database\Seeders\DemoUsersSeeder)->run();
        $admin = User::where('email', 'admin@pressafrica.media')->sole();
        $admin->update(['password' => 'Changed-Online-2026!', 'name' => 'Rédacteur en chef']);

        (new \Database\Seeders\DemoUsersSeeder)->run();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Changed-Online-2026!', $admin->fresh()->password));
        $this->assertSame('Rédacteur en chef', $admin->fresh()->name);
    }
}
