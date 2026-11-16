<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Support\PostStats;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Detail view of one article's audience (admin > Posts > Stats); same right as the site Analytics screen. */
class PostStatsController extends Controller
{
    public function show(Request $request, Post $post): View
    {
        $days = in_array((int) $request->query('days', 30), [7, 30, 90, 365], true) ? (int) $request->query('days', 30) : 30;
        // Nothing before the publication date to count, so a young article does not show weeks of empty days.
        $from = now()->subDays($days - 1)->startOfDay();
        if ($post->published_at && $post->published_at->gt($from) && $post->published_at->lte(now())) {
            $from = $post->published_at->copy()->startOfDay();
        }
        $post->load(['translations', 'author', 'cover', 'categories.translations']);

        return view('admin.posts.stats', [
            'post' => $post,
            'stats' => PostStats::build($post, $from, now()),
            'from' => $from,
            'days' => $days,
        ]);
    }
}
