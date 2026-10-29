<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\MediaItem;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Imports the prototype's media sections (prototype_html/data: podcasts, videos, magazine, radios, tv).
 * Their files stay where site:assets copies them (/assets/audio, /assets/video, /assets/img). Re-runnable on `key`.
 * Magazine, radio and TV entries are demonstration content awaiting the editor-in-chief (PRD EX-02, Q4).
 */
class ImportMedia extends Command
{
    protected $signature = 'content:import-media {--source= : folder holding the media JSON files}';

    protected $description = 'Import podcasts, videos, magazine issues, radio stations and TV channels from the prototype';

    public function handle(): int
    {
        $src = rtrim($this->option('source') ?: base_path('../prototype_html/data'), '/');
        $read = fn ($f) => is_file("$src/$f") ? json_decode(file_get_contents("$src/$f"), true) : [];
        $post = fn ($wpId) => $wpId ? Post::where('legacy_id', (int) $wpId)->value('id') : null;
        $counts = [];

        DB::transaction(function () use ($read, $post, &$counts) {
            $pod = $read('podcasts.json');
            foreach ($pod['shows'] ?? [] as $s) {
                $this->item('podcast_show', $s['id'], 'published', null, null, [], $s, ['title', 'desc'], $counts);
            }
            foreach ($pod['episodes'] ?? [] as $e) {
                $files = ['fr' => str_replace('{lang}', 'fr', $e['audio']), 'en' => str_replace('{lang}', 'en', $e['audio'])];
                $this->item('podcast', $e['id'], $e['status'], $e['date'], $post($e['article'] ?? null),
                    ['show' => $e['show'], 'duration' => (int) $e['duration']], $e, ['title', 'desc'], $counts, $files);
            }
            foreach (($read('videos.json')['videos'] ?? []) as $v) {
                $this->item('video', $v['id'], $v['status'], $v['date'], $post($v['article'] ?? null),
                    ['topic' => $v['topic'], 'duration' => (int) $v['duration'], 'file' => $v['file'] ?? null, 'poster' => $v['poster'] ?? null], $v, ['title', 'desc'], $counts);
            }
            foreach (($read('magazine.json')['issues'] ?? []) as $i) {
                $item = $this->item('magazine', $i['id'], $i['status'], $i['date'], $post($i['cover_article'] ?? null), ['number' => (int) $i['number']], $i, ['title', 'desc'], $counts);
                $item->posts()->sync(collect($i['articles'] ?? [])->map($post)->filter()->values()->mapWithKeys(fn ($id, $n) => [$id => ['position' => $n + 1]])->all());
            }
            foreach (['radios.json' => ['radio', 'stations'], 'tv.json' => ['tv', 'channels']] as $file => [$type, $list]) {
                foreach (($read($file)[$list] ?? []) as $x) {
                    $this->item($type, $x['id'], $x['status'], null, null,
                        collect($x)->only(['name', 'country', 'language', 'genre', 'website'])->all(), $x, [null, 'desc'], $counts);
                }
            }
        });

        foreach ($counts as $type => $n) {
            $this->line("$type: $n");
        }
        AuditLog::system('import', 'podcasts', 'content:import-media', collect($counts)->map(fn ($n, $t) => "$t $n")->implode(', '));

        return self::SUCCESS;
    }

    /** $fields = [title key, description key] in the JSON language blocks (null when the type has no title). */
    private function item(string $type, string $key, string $status, ?string $date, ?int $postId, array $data, array $row, array $fields, array &$counts, array $files = []): MediaItem
    {
        $item = MediaItem::updateOrCreate(['key' => $key], ['type' => $type, 'status' => $status === 'published' ? 'published' : 'draft',
            'published_on' => $date, 'post_id' => $postId, 'data' => $data]);
        foreach (['fr', 'en'] as $l) {
            $item->translations()->updateOrCreate(['locale' => $l], [
                'title' => $fields[0] ? ($row[$l][$fields[0]] ?? null) : null,
                'description' => $row[$l][$fields[1]] ?? null,
                'file' => $files[$l] ?? null,
            ]);
        }
        $counts[$type] = ($counts[$type] ?? 0) + 1;

        return $item;
    }
}
