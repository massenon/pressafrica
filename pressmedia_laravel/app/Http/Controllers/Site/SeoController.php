<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Support\Chrome;
use App\Support\Settings;
use Illuminate\Http\Response;

/** robots.txt, XML sitemaps (pages + images, Google News) and RSS, all generated from the database. */
class SeoController extends Controller
{
    public function robots(): Response
    {
        $lines = ['User-agent: *', 'Allow: /', 'Disallow: /admin/', 'Sitemap: '.url('sitemap.xml')];
        if (Settings::get('newsSitemap')) {
            $lines[] = 'Sitemap: '.url('news-sitemap.xml');
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** Plain-language map of the site for AI agents (llms.txt proposal: Markdown with one H1). */
    public function llms(): Response
    {
        $lines = ['# Africa Global Press', '', '> '.__('seo.desc.home', [], 'fr'), '', '## Sections (français)'];
        foreach (\App\Models\Category::with('translations')->orderBy('id')->get() as $c) {
            if ($c->translation('fr')) {
                $lines[] = '- ['.$c->translation('fr')->name.']('.$c->url('fr').')';
            }
        }
        $lines = array_merge($lines, ['', '## Sections (English)', '- [Home]('.url('en').')', '', '## Plan du site', '- [sitemap.xml]('.url('sitemap.xml').')', '- [RSS (fr)]('.url('fr/feed.xml').')', '- [RSS (en)]('.url('en/feed.xml').')']);

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/markdown; charset=UTF-8']);
    }

    /** Every indexable page with its FR/EN alternates (as the prototype's sitemap.xml) and article cover images. */
    public function sitemap(): Response
    {
        $entries = [];
        $add = function (array $alts, ?string $lastmod = null, ?string $image = null) use (&$entries) {
            $entries[] = compact('alts', 'lastmod', 'image');
        };
        $today = now()->toDateString();

        $add(['fr' => url('fr'), 'en' => url('en')], $today);
        foreach (Category::with('translations')->orderBy('position')->get() as $c) {
            $add(['fr' => $c->url('fr'), 'en' => $c->url('en')], $today);
        }
        foreach (Chrome::pages()['fr'] as $slug => $key) {
            if ($key !== 'search') {   // search results are noindex
                $add(['fr' => url("fr/$slug"), 'en' => url('en/'.array_search($key, Chrome::pages()['en'], true))], $today);
            }
        }
        Post::published()->with(['translations', 'cover'])->orderByDesc('published_at')->get()->each(function ($p) use ($add) {
            $alts = array_filter(['fr' => $p->url('fr'), 'en' => $p->url('en')]);
            $add($alts, $p->updated_at->toDateString(), $p->cover?->url());
        });

        return response()->view('site.seo.sitemap', ['entries' => $entries], 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** Google News sitemap: articles published in the last 48 hours (Publisher Center rule). */
    public function newsSitemap(): Response
    {
        abort_unless(Settings::get('newsSitemap'), 404);
        $posts = Post::published()->where('published_at', '>=', now()->subHours(48))->with('translations')->orderByDesc('published_at')->get();

        return response()->view('site.seo.news', ['posts' => $posts], 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function feed(): Response
    {
        abort_unless(Settings::get('rssFeed'), 404);
        $lang = app()->getLocale();
        $posts = Post::published()->whereHas('translations', fn ($q) => $q->where('locale', $lang))
            ->with(['translations', 'author', 'categories.translations'])->orderByDesc('published_at')->take(30)->get();

        return response()->view('site.seo.rss', ['posts' => $posts, 'lang' => $lang], 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }
}
