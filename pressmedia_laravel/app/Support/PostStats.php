<?php

namespace App\Support;

use App\Models\PageView;
use App\Models\Post;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** First-party statistics of one article, from the same visit log as the site report (no IP, no cookie). */
class PostStats
{
    /** @return array<string, mixed> */
    public static function build(Post $post, Carbon $from, Carbon $to): array
    {
        $range = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
        $q = fn () => PageView::where('post_id', $post->id)->whereBetween('created_at', $range);
        $top = fn (string $expr, int $n = 10) => $q()->selectRaw("$expr as label, count(*) as views")->groupBy('label')->orderByDesc('views')->take($n)->get()
            ->map(fn ($r) => [$r->label, (int) $r->views])->all();

        // Every day of the period, empty ones included, so the bars show gaps instead of hiding them.
        $byDay = $q()->select(DB::raw('date(created_at) as d'), DB::raw('count(*) as views'), DB::raw('count(distinct visitor) as visitors'))
            ->groupBy('d')->get()->keyBy('d');
        $daily = [];
        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            $row = $byDay[$day->toDateString()] ?? null;
            $daily[] = [$day->toDateString(), (int) ($row->views ?? 0), (int) ($row->visitors ?? 0)];
        }

        // Position among all articles read in the same period.
        $order = PageView::whereNotNull('post_id')->whereBetween('created_at', $range)->groupBy('post_id')
            ->selectRaw('post_id, count(*) as c')->orderByDesc('c')->pluck('c', 'post_id');
        $pos = $order->keys()->search($post->id);

        return [
            'totals' => [
                'views' => array_sum(array_column($daily, 1)),
                'visitors' => array_sum(array_column($daily, 2)),   // daily uniques summed: the visitor hash changes every day
                'all_time' => (int) $post->views,
                'best_day' => max(array_column($daily, 1) ?: [0]),
            ],
            'daily' => $daily,
            'locales' => $top('upper(locale)'),
            'referrers' => $top("coalesce(referrer_host, '')"),
            'countries' => $top("coalesce(country, '')"),
            'devices' => $top('device'),
            'rank' => $pos === false ? null : $pos + 1,
            'ranked' => $order->count(),
            'comments' => $post->comments()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n)->all(),
        ];
    }
}
