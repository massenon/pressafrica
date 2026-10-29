<?php

namespace App\Support;

use App\Models\Category;
use App\Models\MediaItem;
use App\Models\MenuItem;
use App\Models\Post;
use Illuminate\Support\Collection;

/** Dynamic parts of the public chrome: main menu with mega panels and the "Explore by topic" columns. */
class Navigation
{
    /** @return array{'%%NAV%%': string, '%%TOPICS%%': string} */
    public static function markers(string $lang): array
    {
        $posts = self::posts($lang);

        return [
            '%%NAV%%' => view('site.partials.mainnav', [
                'items' => MenuItem::where('location', 'main')->where('active', true)->with(['translations', 'category.translations'])->orderBy('position')->get(),
                'posts' => $posts, 'lang' => $lang,
                'episodes' => MediaPages::items('podcast')->take(4), 'shows' => MediaItem::ofType('podcast_show')->with('translations')->get()->keyBy('key'),
                'videos' => MediaPages::items('video')->filter(fn ($v) => $v->post)->take(4),
            ])->render(),
            '%%AD_HEADER%%' => ($ad = trim(view('site.partials.ad', ['key' => 'header_top', 'placeholder' => false])->render()))
                ? '<div class="container-page ad-header" data-ad-zone="header_top"><span class="ad-label">'.e(__('site.ad', [], $lang)).'</span>'.$ad.'</div>' : '',
            '%%TOPICS%%' => view('site.partials.topics', ['lang' => $lang, 'posts' => $posts,
                'sections' => Category::with('translations')->whereIn('key', ['politics', 'economy', 'society', 'finance', 'culture', 'sports', 'health', 'science'])->get()->sortBy(fn ($c) => array_search($c->key, ['politics', 'economy', 'society', 'finance', 'culture', 'sports', 'health', 'science']))])->render(),
        ];
    }

    /** Published posts of $lang, newest first, loaded once per page for every panel. */
    private static function posts(string $lang): Collection
    {
        return Post::published()->whereHas('translations', fn ($q) => $q->where('locale', $lang))
            ->with(['translations', 'cover', 'categories'])->orderByDesc('published_at')->orderByDesc('id')->get();
    }
}
