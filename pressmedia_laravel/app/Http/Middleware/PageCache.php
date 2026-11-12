<?php

namespace App\Http\Middleware;

use App\Support\CommentGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public pages rendered once and served from a file cache to visitors (production showed 2-3 s per page on the shared
 * host, ~45 queries each). Signed-in staff, flash messages and non-HTML answers bypass it. Per-visitor tokens (CSRF,
 * the comment form's signed render time) are put back on every hit. Any content change empties it (AppServiceProvider).
 */
class PageCache
{
    public const TTL = 600;

    private const CSRF = '%%PAGE_CACHE_CSRF%%';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->cacheable($request)) {
            return $next($request);
        }
        $key = 'page:'.Cache::store('pages')->get('version', 0).':'.sha1($request->getRequestUri());
        if (is_string($html = Cache::store('pages')->get($key))) {
            return response($this->personalise($html), 200, ['Content-Type' => 'text/html; charset=UTF-8', 'X-Page-Cache' => 'hit']);
        }

        $response = $next($request);
        if ($response->getStatusCode() === 200 && str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) {
            Cache::store('pages')->put($key, str_replace(csrf_token(), self::CSRF, (string) $response->getContent()), self::TTL);
            $response->headers->set('X-Page-Cache', 'miss');
        }

        return $response;
    }

    /** Empties the cache: a new version makes every stored page unreachable; old files are pruned daily. */
    public static function flush(): void
    {
        $store = Cache::store('pages');
        $store->forever('version', (int) $store->get('version', 0) + 1);
    }

    /** Deletes stored pages older than a day (scheduled): entries of past versions are never read again. */
    public static function prune(): int
    {
        $dir = config('cache.stores.pages.path');
        $n = 0;
        foreach (is_dir($dir) ? File::allFiles($dir) : [] as $f) {
            if ($f->getMTime() < time() - 86400 && $f->getFilename() !== '.gitignore') {
                File::delete($f->getPathname());
                $n++;
            }
        }

        return $n;
    }

    private function cacheable(Request $request): bool
    {
        return $request->isMethod('GET') && config('cache.pages_enabled') && $request->user() === null
            && empty($request->session()->get('_flash.old')) && ! $request->session()->has('errors');
    }

    private function personalise(string $html): string
    {
        $html = str_replace(self::CSRF, csrf_token(), $html);

        // CommentGuard rejects forms filled in under 3 s after render; give each visitor a fresh render time.
        return preg_replace_callback('/(name="ft" value=")[^"]*(")/', fn ($m) => $m[1].CommentGuard::token().$m[2], $html);
    }
}
