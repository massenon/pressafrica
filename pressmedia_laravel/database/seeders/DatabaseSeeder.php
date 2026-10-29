<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Full database: `php artisan migrate:fresh --seed`. Roles, real content and one staff account per role always;
 * demo comments outside production only. Site settings need no rows: App\Support\Settings falls back to the prototype defaults.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolesSeeder::class, ContentSeeder::class, DemoUsersSeeder::class, DemoCommentsSeeder::class]);
    }
}
