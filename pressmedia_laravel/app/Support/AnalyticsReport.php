<?php

namespace App\Support;

use App\Models\PageView;
use App\Models\Post;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One report shape for both sources (EX-15 to EX-18): the screen and the exports read the same array, so the
 * totals of an export always equal the screen's (PRD R3).
 */
class AnalyticsReport
{
    public static function build(string $source, Carbon $from, Carbon $to): array
    {
        return $source === 'ga4' ? self::ga4($from, $to) : self::internal($from, $to);
    }

    private static function internal(Carbon $from, Carbon $to): array
    {
        $q = fn () => PageView::whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
        $top = fn (string $col, int $n = 10) => $q()->whereNotNull($col)->select($col.' as label', DB::raw('count(*) as views'))
            ->groupBy($col)->orderByDesc('views')->take($n)->get()->map(fn ($r) => [$r->label, (int) $r->views])->all();
        $titles = Post::with('translations')->whereIn('id', array_column($top('post_id'), 0))->get()->mapWithKeys(fn ($p) => [$p->id => $p->translation('fr')?->title]);

        return [
            'source' => 'internal',
            'totals' => ['views' => $q()->count(), 'visitors' => $q()->distinct()->count('visitor')],   // the hash includes the day: daily unique visitors, summed
            'daily' => $q()->select(DB::raw('date(created_at) as d'), DB::raw('count(*) as views'))->groupBy('d')->orderBy('d')->get()->map(fn ($r) => [$r->d, (int) $r->views])->all(),
            'posts' => array_map(fn ($r) => [$titles[$r[0]] ?? '#'.$r[0], $r[1]], $top('post_id')),
            'sections' => $top('section'),
            'referrers' => $top('referrer_host'),
            'countries' => $top('country'),
            'devices' => $top('device', 3),
            'searches' => $top('search'),
            'ads' => self::ads($from, $to),
        ];
    }

    /** Ad zones: impressions, clicks and CTR (%) per zone over the period (EX-20, PRD R6); same for both sources. */
    public static function ads(Carbon $from, Carbon $to): array
    {
        return DB::table('ad_stats')->join('ad_zones', 'ad_zones.id', '=', 'ad_stats.ad_zone_id')
            ->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('ad_zones.name as zone, sum(impressions) as impressions, sum(clicks) as clicks')->groupBy('ad_zones.name')->orderByDesc('clicks')->get()
            ->map(fn ($r) => [$r->zone, (int) $r->impressions, (int) $r->clicks, $r->impressions ? round($r->clicks / $r->impressions * 100, 2) : 0.0])->all();
    }

    private static function ga4(Carbon $from, Carbon $to): array
    {
        [$f, $t] = [$from->toDateString(), $to->toDateString()];
        $pairs = fn ($dim, $n = 10) => array_map(fn ($r) => [$r[$dim], (int) $r['screenPageViews']], Ga4Client::report([$dim], ['screenPageViews'], $f, $t, $n));
        $tot = Ga4Client::report([], ['screenPageViews', 'totalUsers'], $f, $t, 1)[0] ?? ['screenPageViews' => 0, 'totalUsers' => 0];

        return [
            'source' => 'ga4',
            'totals' => ['views' => (int) $tot['screenPageViews'], 'visitors' => (int) $tot['totalUsers']],
            'daily' => array_map(fn ($r) => [substr($r['date'], 0, 4).'-'.substr($r['date'], 4, 2).'-'.substr($r['date'], 6, 2), (int) $r['screenPageViews']],
                Ga4Client::report(['date'], ['screenPageViews'], $f, $t, 400)),
            'posts' => $pairs('pageTitle'),
            'sections' => [],
            'referrers' => $pairs('sessionSource'),
            'countries' => $pairs('country'),
            'devices' => $pairs('deviceCategory', 3),
            'searches' => [],
            'ads' => self::ads($from, $to),
        ];
    }
}
