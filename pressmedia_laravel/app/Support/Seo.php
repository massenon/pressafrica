<?php

namespace App\Support;

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

    public static function title(string $title, ?string $seoTitle = null): string
    {
        $tt = $seoTitle ?: $title;
        $full = $tt.self::SUFFIX;
        if (mb_strlen($full) <= 70) {
            return $full;
        }

        return $seoTitle && mb_strlen($tt) <= 70 ? $tt : self::truncate($tt, 40).self::SUFFIX;
    }

    public static function description(?string $seoDescription, ?string $excerpt, string $body): string
    {
        return self::truncate($seoDescription ?: ($excerpt ?: html_entity_decode(strip_tags($body))));
    }
}
