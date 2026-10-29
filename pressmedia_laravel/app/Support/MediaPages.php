<?php

namespace App\Support;

use App\Models\MediaItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Data of the media section pages, with the prototype's rules (build_site.py pod_episodes, vid_list, mag_issues, dir_list). */
class MediaPages
{
    public static function for(string $section, string $lang): array
    {
        return match ($section) {
            'podcasts' => ['episodes' => self::items('podcast'), 'shows' => MediaItem::ofType('podcast_show')->published()->with('translations')->orderBy('id')->get()],
            'videos' => ['videos' => self::items('video')],
            'magazine' => ['issues' => self::items('magazine', ['posts.translations', 'posts.cover'])],
            'radios' => ['entries' => self::directory('radio'), 'videos' => collect()],
            'television' => ['entries' => self::directory('tv'), 'videos' => self::items('video')->take(4)],
            default => [],
        };
    }

    /** Published items, newest first (by date, then key so equal dates keep a stable order). */
    public static function items(string $type, array $with = []): Collection
    {
        return MediaItem::ofType($type)->published()->with(array_merge(['translations', 'post.translations', 'post.cover'], $with))
            ->orderByDesc('published_on')->orderByDesc('key')->get();
    }

    /** Directories keep the editorial order of the source (no date). */
    public static function directory(string $type): Collection
    {
        return MediaItem::ofType($type)->published()->with('translations')->orderBy('id')->get();
    }

    /** "26:05" clock of a duration in seconds, as on the video cards. */
    public static function clock(int $seconds): string
    {
        return intdiv($seconds, 60).':'.str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
    }

    public static function month(\DateTimeInterface $d, string $lang): string
    {
        return Carbon::instance($d)->locale($lang)->isoFormat('MMMM YYYY');
    }
}
