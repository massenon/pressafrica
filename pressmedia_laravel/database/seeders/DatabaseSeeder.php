<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Full database: `php artisan migrate:fresh --seed`. Roles and real content always; demo staff and comments
 * outside production only. Site settings need no rows: App\Support\Settings falls back to the prototype defaults.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolesSeeder::class, ContentSeeder::class, DemoUsersSeeder::class, DemoCommentsSeeder::class]);
    }
}
