<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\HeroSlide;
use App\Models\HomeSlot;
use App\Models\Post;
use Illuminate\View\View;

/** Home page with the prototype's blocks and rules (build_site.py build_home(), hero_block()). */
class HomeController extends Controller
{
    public function show(): View
    {
        $lang = app()->getLocale();
        $all = Post::published()->whereHas('translations', fn ($q) => $q->where('locale', $lang))
            ->with(['translations', 'cover', 'author', 'categories.translations'])
            ->orderByDesc('published_at')->orderByDesc('id')->get();
        $byId = $all->keyBy('id');
        $slot = fn (string $name) => HomeSlot::where('slot', $name)->orderBy('position')->pluck('post_id')
            ->map(fn ($id) => $byId[$id] ?? null)->filter()->values();

        $slides = HeroSlide::live()->with(['translations', 'media', 'post.translations', 'post.cover', 'post.author'])
            ->take(HeroSlide::MAX_LIVE)->get()->map(fn ($s) => $s->view($lang));
        $latest = $slot('latest');
        [$econBig, $econSmall] = [$slot('econ_big'), $slot('econ_small')];
        $inCat = fn (string $key) => $all->filter(fn ($p) => $p->categories->contains('key', $key))->values();

        // Economy pages: the picked first page, then economy + finance posts not used yet, 2 large + 4 small per page.
        $used = $econBig->merge($econSmall)->pluck('id');
        $rest = $all->filter(fn ($p) => $p->categories->whereIn('key', ['economy', 'finance'])->isNotEmpty())->whereNotIn('id', $used)->values();
        $econPages = collect([[$econBig, $econSmall]])->concat($rest->chunk(6)->map(fn ($c) => [$c->take(2)->values(), $c->slice(2)->values()]));

        $shown = $latest->merge($econBig)->merge($econSmall)->pluck('id')->all();
        $mostRead = $all->whereNotIn('id', $shown)->take(4)->values();
        $topics = [];
        foreach (['society' => 'blue', 'politics' => 'red', 'culture' => 'blue'] as $key => $color) {
            $items = $inCat($key)->whereNotIn('id', $shown)->take(4)->values();
            if ($items->count() < 2) {
                continue;
            }
            $shown = array_merge($shown, $items->pluck('id')->all());
            $topics[] = ['category' => $items[0]->categories->firstWhere('key', $key), 'color' => $color, 'posts' => $items];
        }

        return view('site.home', [
            'slides' => $slides, 'latest' => $latest, 'econPages' => $econPages, 'mostRead' => $mostRead, 'topics' => $topics,
            'ticker' => $all->take(10),
            'meta' => ['title' => __('seo.title.home'), 'description' => __('seo.desc.home'), 'canonical' => url("$lang/index.html"),
                'image' => ($slides->first()['image'] ?? null)?->url()],
            'alternates' => ['fr' => url('fr/index.html'), 'en' => url('en/index.html')],
        ]);
    }
}
