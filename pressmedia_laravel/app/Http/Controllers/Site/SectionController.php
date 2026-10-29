<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Post;
use App\Support\Seo;
use Illuminate\View\View;

/** Section pages /{locale}/{slug}.html with the prototype's layouts (build_site.py build_category()). */
class SectionController extends Controller
{
    public function show(string $slug): View
    {
        $lang = app()->getLocale();
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
}
