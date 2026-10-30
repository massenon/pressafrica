<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Copies the prototype's compiled assets (CSS, fonts, icons, images, app.js) into public/assets and Turbo from
 * node_modules, so the Laravel pages look and behave exactly like the prototype. Run before deploy (with npm ci);
 * public/assets is generated, not committed.
 */
class SyncSiteAssets extends Command
{
    protected $signature = 'site:assets {--source= : prototype_html folder}';

    protected $description = 'Copy the prototype assets and Turbo into public/assets';

    /**
     * Lighthouse "minify JavaScript": the prototype's app.js is readable source and Turbo's npm build is not minified
     * (217 kB). esbuild comes with Vite; this runs on the workstation that prepares the deploy, never on the host.
     */
    private function minify(array $files): void
    {
        $esbuild = base_path('node_modules/.bin/esbuild'.(PHP_OS_FAMILY === 'Windows' ? '.cmd' : ''));
        if (! is_file($esbuild)) {
            $this->warn('esbuild not found (npm ci): JavaScript left unminified.');

            return;
        }
        foreach ($files as $f) {
            $p = new Process([$esbuild, $f, '--minify', '--allow-overwrite', '--outfile='.$f, '--log-level=error']);
            $p->run();
            $p->isSuccessful() ? $this->line('minified '.basename($f).' ('.round(filesize($f) / 1024).' kB)') : $this->warn('could not minify '.basename($f).': '.$p->getErrorOutput());
        }
    }

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
        // The web manifest and service worker are routes (built from Settings > PWA), not copies of the prototype's.
        File::delete([public_path('manifest.webmanifest'), public_path('sw.js')]);
        $this->minify(["$dest/js/app.js", "$dest/js/vendor/turbo.min.js"]);
        $this->info('Assets copied to public/assets.');

        return self::SUCCESS;
    }
}
