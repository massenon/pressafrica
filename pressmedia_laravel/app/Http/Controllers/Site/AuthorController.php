<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Support\Authors;
use App\Support\Seo;
use Illuminate\View\View;

/** Author page: byline target (E-E-A-T) listing the newsroom member's articles in the current language. */
class AuthorController extends Controller
{
    public const PER_PAGE = 24;

    public function show(string $slug): View
    {
        $lang = app()->getLocale();
        $author = Authors::find($slug) ?? abort(404);

        $paginator = Post::published()->where('author_id', $author->id)
            ->whereHas('translations', fn ($q) => $q->where('locale', $lang))
            ->with(['translations', 'cover', 'author', 'categories.translations'])
            ->orderByDesc('published_at')->orderByDesc('id')->paginate(self::PER_PAGE);
        abort_if($paginator->isEmpty() && $paginator->currentPage() > 1, 404);

        $page = $paginator->currentPage();
        $url = Authors::url($author, $lang);
        $desc = __('site.bio');

        return view('site.authors.show', [
            'author' => $author,
            'paginator' => $paginator,
            'posts' => $paginator->getCollection(),
            'crumbs' => [[__('site.home_crumb'), url($lang)], [$author->name, null]],
            'meta' => [
                'title' => Seo::title($author->name.($page > 1 ? ' – '.__('site.page_of').' '.$page : '')),
                'description' => Seo::truncate($author->name.' – '.__('site.editorial_team').'. '.$desc),
                'canonical' => $page > 1 ? $url.'?page='.$page : $url,
                'image' => $paginator->first()?->cover?->url(),
                'prev' => Seo::pageUrl($paginator, $page - 1),
                'next' => Seo::pageUrl($paginator, $page + 1),
                'robots' => $paginator->total() === 0 ? 'noindex,follow' : null,
            ],
            'alternates' => array_filter(['fr' => Authors::url($author, 'fr'), 'en' => Authors::url($author, 'en')]),
        ]);
    }
}
