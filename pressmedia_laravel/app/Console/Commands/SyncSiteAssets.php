<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Copies the prototype's compiled assets (CSS, fonts, icons, images, app.js) into public/assets and Turbo from
 * node_modules, so the Laravel pages look and behave exactly like the prototype. Run before deploy (with npm ci);
 * public/assets is generated, not committed.
 */
class SyncSiteAssets extends Command
{
    protected $signature = 'site:assets {--source= : prototype_html folder}';

    protected $description = 'Copy the prototype assets and Turbo into public/assets';

    public function handle(): int
    {
        $src = rtrim($this->option('source') ?: base_path('../prototype_html'), '/');
        $dest = public_path('assets');
        if (! is_dir("$src/assets")) {
            $this->error("Missing $src/assets");

            return self::FAILURE;
        }
        foreach (['css', 'fonts', 'icons', 'img', 'js', 'audio', 'video'] as $dir) {
            if (is_dir("$src/assets/$dir")) {
                File::copyDirectory("$src/assets/$dir", "$dest/$dir");
            }
        }
        File::delete(File::glob("$dest/js/admin*.js"));   // the admin console is Laravel's own

        $turbo = base_path('node_modules/@hotwired/turbo/dist/turbo.es2017-umd.js');
        if (! is_file($turbo)) {
            $this->error('Turbo missing: run npm ci first.');

            return self::FAILURE;
        }
        File::ensureDirectoryExists("$dest/js/vendor");
        File::copy($turbo, "$dest/js/vendor/turbo.min.js");
        // Admin rich-text editor (spec EX-48), served as a static file like Turbo: the host has no Node.
        File::ensureDirectoryExists("$dest/vendor/quill");
        foreach (['quill.js', 'quill.snow.css'] as $f) {
            File::copy(base_path("node_modules/quill/dist/$f"), "$dest/vendor/quill/$f");
        }
        if (is_file("$src/manifest.webmanifest")) {
            File::copy("$src/manifest.webmanifest", public_path('manifest.webmanifest'));
        }
        $this->info('Assets copied to public/assets.');

        return self::SUCCESS;
    }
}
