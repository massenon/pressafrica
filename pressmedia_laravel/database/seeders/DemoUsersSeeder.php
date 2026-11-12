<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * One staff account per role, also in production (owner decision 2026-10-29: the owner changes them once online).
 * Password: DEMO_PASSWORD from .env, otherwise a random one printed once (nothing guessable is ever stored).
 * Existing accounts are left untouched, so re-running the seed never resets a password changed since.
 */
class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('DEMO_PASSWORD') ?: Str::password(16, symbols: false);
        foreach (['admin' => 'Admin', 'editor' => 'Éditeur', 'author' => 'Auteur', 'moderator' => 'Modérateur'] as $role => $name) {
            $user = User::firstOrCreate(['email' => "$role@pressafrica.media"], ['name' => "$name (démo)", 'password' => $password]);
            if ($user->wasRecentlyCreated) {
                $user->syncRoles([$role]);
            }
        }
        $this->command?->info('Demo staff: admin@, editor@, author@, moderator@pressafrica.media'.(env('DEMO_PASSWORD') ? ' (DEMO_PASSWORD)' : " password: $password"));
    }
}
