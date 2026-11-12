<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Real content of the site, from the prototype data (prototype_html/data): 64 FR + 64 EN articles, sections,
 * covers, home selection, podcasts, videos, magazine, radios, TV. Same commands as a manual import, so a fresh
 * database and an imported one cannot drift apart. Skipped with a warning when the prototype folder is absent.
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $source = base_path('../prototype_html/data');
        if (! is_file("$source/articles.json")) {
            $this->command?->warn("Content not seeded: $source/articles.json not found.");

            return;
        }
        foreach (['content:import', 'content:import-media'] as $command) {
            Artisan::call($command);
            $this->command?->info(trim(Artisan::output()));
        }
    }
}
