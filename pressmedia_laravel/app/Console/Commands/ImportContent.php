<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use App\Support\MediaStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Phase 3: imports the WordPress posts extracted in prototype_html/data (articles.json) with the prototype's rules
 * (laravel-import.json, exported by tools/export_laravel_import.py):
 *  - 8 posts exist natively in both languages (pairs FR id -> EN id): one post, two translations;
 *  - every other post is French; its English row gets the hand-translated title/excerpt and keeps the French body.
 * Re-runnable: posts are matched on legacy_id (the FR WordPress id). Source anomalies are reported, never fixed.
 */
class ImportContent extends Command
{
    protected $signature = 'content:import {--source= : folder holding articles.json, laravel-import.json, image-manifest.json} {--no-images : skip cover images}';

    protected $description = 'Import the 64 FR + 64 EN articles of the prototype into the database';

    private array $anomalies = [];

    private array $usedSlugs = ['fr' => [], 'en' => []];

    /** Bodies as the prototype prints them (laravel-bodies.json), by locale then WordPress id. */
    private array $bodies = [];

    /** EN cover alt text from full translations, by WordPress id. */
    private array $enAlt = [];

    public function handle(): int
    {
        $src = rtrim($this->option('source') ?: base_path('../prototype_html/data'), '/');
        foreach (['articles.json', 'laravel-import.json'] as $f) {
            if (! is_file("$src/$f")) {
                $this->error("Missing $src/$f");

                return self::FAILURE;
            }
        }
        $raw = collect(json_decode(file_get_contents("$src/articles.json"), true))->where('type', 'post')->keyBy('id');
        $rules = json_decode(file_get_contents("$src/laravel-import.json"), true);
        $manifest = is_file("$src/image-manifest.json") ? json_decode(file_get_contents("$src/image-manifest.json"), true) : [];
        $this->bodies = is_file("$src/laravel-bodies.json") ? json_decode(file_get_contents("$src/laravel-bodies.json"), true) : [];
        $pairs = $rules['pairs'];
        $enNative = array_flip($pairs);   // EN id -> FR id

        $catByDbSlug = $this->importCategories($rules['categories']);

        // Same order as the generator (newest first, id as tie-break) so duplicate-slug suffixes land on the same posts.
        $ordered = $raw->sortByDesc(fn ($r) => $r['date'].sprintf('%010d', $r['id']));
        $count = ['fr' => 0, 'en' => 0];

        DB::transaction(function () use ($ordered, $raw, $pairs, $enNative, $rules, $catByDbSlug, $manifest, $src, &$count) {
            foreach ($ordered as $id => $r) {
                if (isset($enNative[$id])) {
                    continue;   // imported as the English side of its French pair
                }
                $post = Post::withTrashed()->firstOrNew(['legacy_id' => (int) $id]);
                $post->fill([
                    'author_id' => $this->author($r['author'] ?? '')->id,
                    'status' => 'published',
                    'published_at' => $r['date'],
                ]);
                $post->created_at = $r['date'];
                $post->updated_at = $r['modified'];
                $post->save();

                $this->translation($post, 'fr', $r, $r['title'], null, null);
                $count['fr']++;

                if (isset($pairs[$id])) {
                    $en = $raw[$pairs[$id]] ?? null;
                    if (! $en) {
                        $this->anomaly($id, "EN pair {$pairs[$id]} missing from source");
                    } else {
                        $this->translation($post, 'en', $en, $en['title'], null, null);
                        $count['en']++;
                    }
                } elseif ($full = $this->fullTranslation($src, $id)) {
                    // Hand-made full EN translation (same file and checks as build_site.py load_translation()).
                    // Images are dropped from translated bodies, as build_site.py sanitize_translation() does.
                    $body = preg_replace(['#<figure\b.*?</figure>#is', '#<img\b[^>]*>#i'], '', $full['body_html']);
                    $this->translation($post, 'en', ['content' => $body, 'excerpt' => ''] + $r, $full['title'], $full['excerpt'], null, $full);
                    $this->enAlt[$id] = $full['image_alt'];
                    $count['en']++;
                } elseif (isset($rules['en'][$id])) {
                    $this->anomaly($id, 'EN has title/excerpt only, body stays French (no translations/en file)');
                    $this->translation($post, 'en', $r, $rules['en'][$id]['title'], $rules['en'][$id]['excerpt'], 'fr');
                    $count['en']++;
                } else {
                    $this->anomaly($id, 'no English title/excerpt: post stays French-only');
                }

                $catIds = collect($r['categories'] ?? [])->where('tax', 'category')->pluck('slug')
                    ->map(fn ($s) => $catByDbSlug[$s] ?? null)->filter()->unique()->values()->all();
                if (! $catIds) {
                    $this->anomaly($id, 'no category maps to a site section');
                }
                $post->categories()->sync($catIds);

                if (! $this->option('no-images')) {
                    $this->cover($post, $r, $manifest, $src);
                }
            }
        });

        $sourceFr = $raw->count() - count($enNative);
        $this->table(['', 'source', 'imported'], [['FR', $sourceFr, $count['fr']], ['EN', $sourceFr, $count['en']]]);
        Storage::disk('local')->put('import-report.json', json_encode(['counts' => $count, 'anomalies' => $this->anomalies], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info(count($this->anomalies).' source anomalies kept as is, listed in storage/app/private/import-report.json');
        AuditLog::system('import', 'posts', 'content:import', "FR {$count['fr']}, EN {$count['en']}, anomalies ".count($this->anomalies));

        return $count['fr'] === $sourceFr ? self::SUCCESS : self::FAILURE;
    }

    /** Creates the site sections and returns WordPress category slug => category id. */
    private function importCategories(array $cats): array
    {
        $map = [];
        foreach ($cats as $i => $c) {
            $cat = Category::updateOrCreate(['key' => $c['key']], ['position' => $i]);
            foreach (['fr', 'en'] as $l) {
                $cat->translations()->updateOrCreate(['locale' => $l], ['name' => $c[$l], 'slug' => $c['slug_'.$l]]);
            }
            foreach ($c['db'] as $dbSlug) {
                $map[$dbSlug] = $cat->id;
            }
        }

        return $map;
    }

    private function translation(Post $post, string $locale, array $r, string $title, ?string $excerpt, ?string $bodyLocale, ?array $full = null): void
    {
        $title = $this->cleanTitle($title);
        // Slug rules of the generator (build_site.py mk()), so URLs match the prototype: numeric WP slugs and
        // translated titles are re-slugged, over-long ones shortened, others kept as WordPress stored them.
        $slug = (string) $r['slug'];
        if ($full || $bodyLocale || ctype_digit($slug)) {
            $slug = $this->slugify($title);
        } elseif (strlen($slug) > 90) {
            $slug = $this->slugify($slug);
        }
        if (in_array($slug, $this->usedSlugs[$locale], true)) {
            $this->anomaly($r['id'], "duplicate $locale slug '$slug', suffixed with the post id");
            $slug .= '-'.$r['id'];
        }
        $this->usedSlugs[$locale][] = $slug;

        if (str_contains($r['content'] ?? '', 'pressafrica.media/wp-content')) {
            $this->anomaly($r['id'], "$locale source body links images on the old WordPress host (left out by the prototype's cleaning)");
        }
        // Prefer the body the prototype prints (build_site.clean_body()); fall back to the source without WP comments.
        $body = clean($this->bodies[$locale][(string) $r['id']] ?? preg_replace('/<!--.*?-->/s', '', $r['content'] ?? ''));

        $post->translations()->updateOrCreate(['locale' => $locale], [
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $excerpt ?? (trim($r['excerpt'] ?? '') ?: Str::limit(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($body)))), 165)),
            'body' => $body,
            'body_locale' => $bodyLocale,
            'seo_title' => ($full['seo_title'] ?? $r['seo_title'] ?? '') ?: null,
            'seo_description' => ($full['seo_desc'] ?? $r['seo_desc'] ?? '') ?: null,
        ]);
    }

    private function cover(Post $post, array $r, array $manifest, string $src): void
    {
        $path = $r['image']['path'] ?? null;
        $file = $path && isset($manifest[$path]['widths']['1280']) ? dirname($src).'/'.$manifest[$path]['widths']['1280'] : null;
        if (! $file || ! is_file($file)) {
            $this->anomaly($r['id'], $path ? "cover image $path not found locally" : 'no cover image');

            return;
        }
        // Reuse the media row on re-import instead of piling up copies.
        $media = $post->cover_id ? Media::find($post->cover_id) : null;
        if (! $media) {
            $dest = 'media/import/'.basename($file);
            // The smaller widths go along so public pages get the prototype's srcset (Media::srcset()).
            foreach ($manifest[$path]['widths'] as $w => $rel) {
                if (is_file(dirname($src).'/'.$rel)) {
                    Storage::disk(MediaStore::DISK)->put('media/import/'.basename($rel), file_get_contents(dirname($src).'/'.$rel));
                }
            }
            [$w, $h] = getimagesize($file) ?: [null, null];
            $media = Media::create(['path' => $dest, 'mime' => 'image/webp', 'size' => filesize($file), 'width' => $w, 'height' => $h,
                'alt_fr' => $this->cleanTitle($r['image']['alt'] ?? $r['title']), 'alt_en' => $this->enAlt[$r['id']] ?? $post->translations()->where('locale', 'en')->value('title')]);
        }
        $post->update(['cover_id' => $media->id]);
    }

    /** Authors keep their byline: one author account per name, with an undeliverable address and no usable password. */
    private function author(string $name): User
    {
        $name = trim(str_replace("\u{a0}", ' ', $name)) ?: 'Africa Global Press';
        $user = User::firstOrCreate(['email' => Str::slug($name).'@authors.invalid'], ['name' => $name, 'password' => Str::random(64)]);
        if (! $user->hasAnyRole()) {
            $user->assignRole('author');
        }

        return $user;
    }

    /** translations/en/<id>.json if present and complete, else null (the generator then falls back to the stub too). */
    private function fullTranslation(string $src, string|int $id): ?array
    {
        $file = "$src/translations/en/$id.json";
        $d = is_file($file) ? json_decode(file_get_contents($file), true) : null;
        if (! is_array($d) || ! is_string($d['body_html'] ?? null)) {
            return null;
        }
        foreach (['title', 'excerpt', 'seo_title', 'seo_desc', 'image_alt'] as $k) {
            if (! is_string($d[$k] ?? null) || trim($d[$k]) === '') {
                return null;
            }
        }

        return mb_strlen(trim(strip_tags($d['body_html']))) >= 200 ? $d : null;
    }

    /** Port of build_site.py slugify(): ASCII-fold, every non-alphanumeric run becomes one dash, 90 chars max. */
    private function slugify(string $s): string
    {
        $ascii = preg_replace('/[^\x00-\x7F]/', '', \Normalizer::normalize($s, \Normalizer::FORM_KD));
        $slug = trim(substr(trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii)), '-'), 0, 90), '-');

        return $slug ?: 'article';
    }

    private function cleanTitle(string $t): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace("\u{a0}", ' ', html_entity_decode($t, ENT_QUOTES | ENT_HTML5))));
    }

    private function anomaly(string|int $id, string $what): void
    {
        $this->anomalies[] = ['wp_id' => (string) $id, 'issue' => $what];
    }
}
