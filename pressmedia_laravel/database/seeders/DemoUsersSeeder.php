<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * One staff account per role to try the admin. Never run in production. Password: DEMO_PASSWORD from .env,
 * otherwise a random one printed once (nothing guessable is ever stored).
 */
class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }
        $password = env('DEMO_PASSWORD') ?: Str::password(16, symbols: false);
        foreach (['admin' => 'Admin', 'editor' => 'Éditeur', 'author' => 'Auteur', 'moderator' => 'Modérateur'] as $role => $name) {
            $user = User::updateOrCreate(['email' => "$role@pressmedia.test"], ['name' => "$name (démo)", 'password' => $password]);
            $user->syncRoles([$role]);
        }
        $this->command?->info('Demo staff: admin@, editor@, author@, moderator@pressmedia.test'.(env('DEMO_PASSWORD') ? ' (DEMO_PASSWORD)' : " password: $password"));
    }
}
