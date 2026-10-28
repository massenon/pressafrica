<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesSeeder::class);

        // Local demo account only; never seeded in production. Set DEMO_ADMIN_PASSWORD in .env to use it.
        if (app()->isLocal() && env('DEMO_ADMIN_PASSWORD')) {
            User::firstOrCreate(['email' => 'admin@pressmedia.test'], ['name' => 'Admin', 'password' => env('DEMO_ADMIN_PASSWORD')])->assignRole('admin');
        }
    }
}
