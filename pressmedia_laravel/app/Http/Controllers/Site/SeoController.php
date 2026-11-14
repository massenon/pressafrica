<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use App\Support\Authors;
use App\Support\Chrome;
use App\Support\Settings;
use Closure;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** robots.txt, XML sitemaps (pages + images, Google News) and RSS, all generated from the database. */
class SeoController extends Controller
{
    /** Articles per sitemap file: two languages each, so at most 10 000 <url> against the protocol's 50 000. */
    public const CHUNK = 5000;

    /** Sitemaps are rebuilt at most hourly, and at once after any content change (the page-cache version moves). */
    private const TTL = 3600;

    public function robots(): Response
    {
        // A copy of the site on a dev or staging host must never be indexed; production and tests get the real file.
        if (app()->environment('local', 'staging')) {
            return response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $lines = ['User-agent: *', 'Allow: /', 'Disallow: /admin/', 'Disallow: /ad/', 'Sitemap: '.url('sitemap.xml')];
        if (Settings::get('newsSitemap')) {
            $lines[] = 'Sitemap: '.url('news-sitemap.xml');
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** Plain-language map of the site for AI agents (llms.txt proposal: Markdown with one H1). */
    public function llms(): Response
    {
        $lines = ['# Africa Global Press', '', '> '.__('seo.desc.home', [], 'fr'), '', '## Sections (français)'];
        foreach (Category::with('translations')->orderBy('id')->get() as $c) {
            if ($c->translation('fr')) {
                $lines[] = '- ['.$c->translation('fr')->name.']('.$c->url('fr').')';
            }
        }
        $lines = array_merge($lines, ['', '## Sections (English)', '- [Home]('.url('en').')', '', '## Plan du site', '- [sitemap.xml]('.url('sitemap.xml').')', '- [RSS (fr)]('.url('fr/feed.xml').')', '- [RSS (en)]('.url('en/feed.xml').')']);

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/markdown; charset=UTF-8']);
    }

    /** One urlset while the site is small; a sitemap index of numbered files once it outgrows CHUNK articles. */
    public function sitemap(): Response
    {
        $parts = $this->parts();
        if ($parts === 1) {
            return $this->xml($this->cached('sitemap:1', fn () => $this->renderPart(1)));
        }

        return $this->xml($this->cached('sitemap:index', fn () => view('site.seo.sitemap-index', [
            'files' => collect(range(1, $parts))->map(fn ($n) => url("sitemap-$n.xml"))->all(),
            'lastmod' => $this->date(Post::published()->max('updated_at')),
        ])->render()));
    }

    /** File $n of the index: part 1 also carries the sections, fixed pages and authors. */
    public function sitemapPart(int $n): Response
    {
        abort_unless($n >= 1 && $n <= $this->parts(), 404);

        return $this->xml($this->cached("sitemap:$n", fn () => $this->renderPart($n)));
    }

    /** Google News sitemap: articles published in the last 48 hours (Publisher Center rule). */
    public function newsSitemap(): Response
    {
        abort_unless(Settings::get('newsSitemap'), 404);

        return $this->xml($this->cached('sitemap:news', function () {
            $posts = Post::published()->where('published_at', '>=', now()->subHours(48))->with('translations')->orderByDesc('published_at')->get();

            return view('site.seo.news', ['posts' => $posts])->render();
        }, 300));
    }

    public function feed(): Response
    {
        abort_unless(Settings::get('rssFeed'), 404);
        $lang = app()->getLocale();
        // Versions whose body is still in the other language are left out, as in the sitemaps.
        $posts = Post::published()->whereHas('translations', fn ($q) => $q->where('locale', $lang)->where(fn ($w) => $w->whereNull('body_locale')->orWhere('body_locale', $lang)))
            ->with(['translations', 'author', 'categories.translations'])->orderByDesc('published_at')->take(30)->get();

        return response()->view('site.seo.rss', ['posts' => $posts, 'lang' => $lang], 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }

    /** Articles per file; configurable (seo.sitemap_chunk) only so the split can be tested without thousands of rows. */
    private function chunk(): int
    {
        return max(1, (int) config('seo.sitemap_chunk', self::CHUNK));
    }

    private function parts(): int
    {
        return max(1, (int) ceil(Post::published()->count() / $this->chunk()));
    }

    private function renderPart(int $n): string
    {
        $entries = [];
        $add = function (array $alts, ?string $lastmod = null, ?string $image = null) use (&$entries) {
            if ($alts) {
                $entries[] = compact('alts', 'lastmod', 'image');
            }
        };

        if ($n === 1) {
            // lastmod is the real date of the newest article behind the page; a page with no such date gets none
            // rather than "today", which Google learns to ignore.
            $add(['fr' => url('fr'), 'en' => url('en')], $this->date(Post::published()->max('updated_at')));

            $byCategory = DB::table('category_post')->join('posts', 'posts.id', '=', 'category_post.post_id')
                ->where('posts.status', 'published')->where('posts.published_at', '<=', now())->whereNull('posts.deleted_at')
                ->groupBy('category_post.category_id')->selectRaw('category_post.category_id as id, max(posts.updated_at) as m')->pluck('m', 'id');
            foreach (Category::with('translations')->orderBy('position')->get() as $c) {
                $add(['fr' => $c->url('fr'), 'en' => $c->url('en')], $this->date($byCategory[$c->id] ?? null));
            }

            foreach (Chrome::pages()['fr'] as $slug => $key) {
                if ($key !== 'search') {   // search results are noindex
                    $add(['fr' => url("fr/$slug"), 'en' => url('en/'.array_search($key, Chrome::pages()['en'], true))]);
                }
            }

            $byAuthor = DB::table('posts')->join('post_translations', 'post_translations.post_id', '=', 'posts.id')
                ->where('posts.status', 'published')->where('posts.published_at', '<=', now())->whereNull('posts.deleted_at')->whereNotNull('posts.author_id')
                ->groupBy('posts.author_id', 'post_translations.locale')->selectRaw('posts.author_id as id, post_translations.locale as locale, max(posts.updated_at) as m')->get();
            foreach (User::whereIn('id', $byAuthor->pluck('id')->unique())->get() as $u) {
                $rows = $byAuthor->where('id', $u->id);   // only the languages this author has articles in
                $add(array_filter(['fr' => $rows->contains('locale', 'fr') ? Authors::url($u, 'fr') : null, 'en' => $rows->contains('locale', 'en') ? Authors::url($u, 'en') : null]), $this->date($rows->max('m')));
            }
        }

        Post::published()->with(['translations', 'cover'])->orderByDesc('published_at')->orderByDesc('id')
            ->skip(($n - 1) * $this->chunk())->take($this->chunk())->get()->each(function ($p) use ($add) {
                $add(array_filter(['fr' => $p->indexableUrl('fr'), 'en' => $p->indexableUrl('en')]), $this->date($p->updated_at), $p->cover?->url());
            });

        return view('site.seo.sitemap', ['entries' => $entries])->render();
    }

    private function date(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->toDateString() : null;
    }

    private function cached(string $key, Closure $render, int $ttl = self::TTL): string
    {
        if (! config('cache.pages_enabled')) {
            return $render();
        }
        $store = Cache::store('pages');

        return $store->remember($key.':'.$store->get('version', 0), $ttl, $render);
    }

    private function xml(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
