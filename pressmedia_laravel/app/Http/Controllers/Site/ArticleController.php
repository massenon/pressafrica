<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostTranslation;
use Illuminate\View\View;

/** Article page, same blocks as the prototype (EX-37): share bars, author, prev/next, newsletter, comments, sidebar. */
class ArticleController extends Controller
{
    public function show(string $slug): View
    {
        $locale = app()->getLocale();
        $t = PostTranslation::where('locale', $locale)->where('slug', $slug)->firstOrFail();
        $post = Post::published()->with(['translations', 'categories.translations', 'cover', 'author'])->findOrFail($t->post_id);

        // Newest first, as on the prototype; prev = newer neighbour on the list, next = older one.
        $list = Post::published()->whereHas('translations', fn ($q) => $q->where('locale', $locale))
            ->with(['translations', 'cover', 'author', 'categories.translations'])
            ->orderByDesc('published_at')->orderByDesc('id')->get();
        $i = $list->search(fn ($p) => $p->id === $post->id);
        $others = $list->reject(fn ($p) => $p->id === $post->id);

        // Prototype rules (build_site.py build_article()): "online-press" is the catch-all section, so the
        // breadcrumb and the related posts prefer any other shared section.
        $cats = $post->categories->pluck('key', 'id');
        $specific = $cats->reject(fn ($k) => $k === 'online-press');
        $crumbCat = $post->sortedCategories($locale)->first(fn ($c) => $c->key !== 'online-press') ?? $post->sortedCategories($locale)->first();
        $sharing = $others->filter(fn ($p) => $p->categories->pluck('id')->intersect($cats->keys())->isNotEmpty());
        $related = $sharing->filter(fn ($p) => $p->categories->pluck('id')->intersect($specific->keys())->isNotEmpty());

        return view('site.articles.show', [
            'post' => $post,
            't' => $t,
            'alternates' => $this->alternates($post),
            'prev' => $i > 0 ? $list[$i - 1] : null,
            'next' => $list[$i + 1] ?? null,
            'mustRead' => $others->take(5)->values(),
            'related' => ($related->isNotEmpty() ? $related : $sharing)->take(3)->values(),
            'crumbCat' => $crumbCat,
            'comments' => $post->comments()->approved()->where('locale', $locale)->oldest()->get(),
            'minutes' => Post::minutes($t->body),
        ]);
    }

    /** URL of this article in each language; a language without a translation points to its home page. */
    private function alternates(Post $post): array
    {
        $out = [];
        foreach (['fr', 'en'] as $l) {
            $tr = $post->translations->firstWhere('locale', $l);
            $out[$l] = $tr ? url("$l/articles/{$tr->slug}.html") : url("$l/index.html");
        }

        return $out;
    }
}
