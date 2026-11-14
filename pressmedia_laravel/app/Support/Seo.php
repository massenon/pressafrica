<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/** Title and description rules of the prototype generator (build_site.py truncate(), build_article()). */
class Seo
{
    public const SUFFIX = ' | Africa Global Press';

    /** Prototype date format (build_site.py fmt_date): "1er janvier 2026", "7 janvier 2026", "January 7, 2026". */
    public static function date(\DateTimeInterface $d, ?string $lang = null): string
    {
        $lang ??= app()->getLocale();
        $c = Carbon::instance($d)->locale($lang);

        return $lang === 'fr' ? ($c->day === 1 ? '1er' : $c->day).' '.$c->isoFormat('MMMM YYYY') : $c->isoFormat('MMMM D, YYYY');
    }

    /** FR or EN text from the URL (/en/... = English), for pages rendered without the locale middleware (errors). */
    public static function byUrl(string $fr, string $en): string
    {
        return request()->segment(1) === 'en' ? $en : $fr;
    }

    /** URL of page $n of a paginated list: page 1 is the bare path (no ?page=1 duplicate), null outside the range. */
    public static function pageUrl(LengthAwarePaginator $p, int $n): ?string
    {
        if ($n < 1 || $n > $p->lastPage()) {
            return null;
        }

        return $n === 1 ? $p->path() : $p->path().'?page='.$n;
    }

    public static function truncate(string $t, int $n = 160): string
    {
        $t = trim(preg_replace('/\s+/u', ' ', $t));
        if (mb_strlen($t) <= $n) {
            return $t;
        }
        $cut = mb_substr($t, 0, $n);
        $cut = str_contains($cut, ' ') ? mb_substr($cut, 0, mb_strrpos($cut, ' ')) : $cut;

        return rtrim($cut, ' ,;:-—').'…';
    }

    /** Google shows about 60 characters of a title; longer ones are cut with an ellipsis in the results. */
    public const TITLE_MAX = 60;

    /**
     * Title + brand when it fits in 60 characters; otherwise the title alone (never cut to a stump to keep the brand),
     * and only a title longer than 60 characters is shortened, at a word boundary.
     */
    public static function title(string $title, ?string $seoTitle = null): string
    {
        $tt = trim(preg_replace('/\s+/u', ' ', $seoTitle ?: $title));
        $full = $tt.self::SUFFIX;

        return mb_strlen($full) <= self::TITLE_MAX ? $full : self::bound($tt);
    }

    /** A hand-written title kept as typed, shortened only past $max characters. */
    public static function bound(string $title, int $max = self::TITLE_MAX): string
    {
        return mb_strlen($title) <= $max ? $title : self::truncate($title, $max);
    }

    public static function description(?string $seoDescription, ?string $excerpt, string $body): string
    {
        return self::truncate($seoDescription ?: ($excerpt ?: html_entity_decode(strip_tags($body))));
    }
}
