<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Post;
use App\Support\Chrome;
use App\Support\Seo;
use Illuminate\View\View;

/** Section pages /{locale}/{slug}.html with the prototype's layouts (build_site.py build_category()). */
class SectionController extends Controller
{
    public function show(string $slug): View
    {
        $lang = app()->getLocale();
        if ($key = Chrome::pages()[$lang][$slug] ?? null) {
            return $this->page($key, $lang);
        }
        $category = Category::with('translations')->findOrFail(
            CategoryTranslation::where('locale', $lang)->where('slug', $slug)->value('category_id') ?? 0
        );
        abort_if($category->layout === 'landing', 404);   // podcasts, videos, magazine, radios, TV: own pages (phase 5)

        $posts = Post::published()
            ->whereHas('categories', fn ($q) => $q->whereKey($category->id))
            ->whereHas('translations', fn ($q) => $q->where('locale', $lang))
            ->with(['translations', 'cover', 'author', 'categories.translations'])
            ->orderByDesc('published_at')->orderByDesc('id')->get();

        $t = $category->translation($lang);
        $crumbs = [[__('site.home_crumb'), url("$lang/index.html")]];
        if ($category->layout === 'archive' && $category->key !== 'online-press' && ($parent = Category::where('key', 'online-press')->first())) {
            $crumbs[] = [$parent->translation($lang)->name, $parent->url($lang)];   // prototype crumbs(): archive sections sit under Online press
        }
        $crumbs[] = [$t->name, null];
        $label = $lang === 'fr' ? mb_strtolower($t->name) : $t->name;

        return view('site.sections.'.$category->layout, [
            'category' => $category,
            't' => $t,
            'posts' => $posts,
            'crumbs' => $crumbs,
            'faq' => collect(__('site.cat_faq'))->map(fn ($qa) => [str_replace('{c}', $label, $qa[0]), str_replace('{c}', $label, $qa[1])])->all(),
            'meta' => ['title' => $t->seo_title ?: $t->name.Seo::SUFFIX, 'description' => $t->description ?: '',
                'canonical' => $category->url($lang), 'image' => $posts->first()?->cover?->url()],
            'alternates' => collect(['fr', 'en'])->mapWithKeys(fn ($l) => [$l => $category->url($l)])->all(),
        ]);
    }

    /** Fixed pages (about, contact, legal, privacy, cookies, search): the prototype's own markup. */
    private function page(string $key, string $lang): View
    {
        $slugs = collect(Chrome::pages())->map(fn ($m) => array_search($key, $m, true));
        $extra = [];
        if ($key === 'search') {
            $extra['%%SEARCH_INDEX%%'] = $this->searchIndex($lang);
        }

        return view('site.page', [
            'key' => $key,
            'html' => Chrome::render("page-$key", $lang, [], $extra),
            'jsonld' => Chrome::render("page-$key", "$lang.jsonld"),
            'bodyClass' => ['about' => 'page-about', 'contact' => 'page-contact', 'search' => 'page-search'][$key] ?? 'page-legal',
            'meta' => ['title' => __("seo.title.$key"), 'description' => __("seo.desc.$key"), 'canonical' => url("$lang/{$slugs[$lang]}.html"),
                // Search result pages are not indexed (crawl budget), as on the prototype.
                'robots' => $key === 'search' ? 'noindex,follow' : null],
            'alternates' => $slugs->map(fn ($s, $l) => url("$l/$s.html"))->all(),
        ]);
    }

    /** Client-side search index, same fields as the prototype (build_site.py build_search()). */
    private function searchIndex(string $lang): string
    {
        $posts = Post::published()->whereHas('translations', fn ($q) => $q->where('locale', $lang))
            ->with(['translations', 'cover', 'author', 'categories.translations'])->orderByDesc('published_at')->orderByDesc('id')->get();

        return json_encode($posts->map(function ($p) use ($lang) {
            $t = $p->translation($lang);
            $cats = $p->sortedCategories($lang);

            return ['t' => $t->title, 'e' => $t->excerpt, 'u' => $p->url($lang), 'k' => $cats->pluck('key')->all(),
                'c' => $cats->map(fn ($c) => $c->translation($lang)?->name)->all(), 'd' => $p->published_at->toDateString(),
                'df' => Seo::date($p->published_at, $lang), 'i' => $p->cover?->url(),
                'r' => __('site.min_read', ['n' => $t->minutes()]), 'a' => $p->author?->name];
        })->values(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    }
}
