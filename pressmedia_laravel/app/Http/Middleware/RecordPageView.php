<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use App\Models\Post;
use App\Models\PostTranslation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Counts public page views server-side (Q7 option b). Skipped: bots, Turbo hover prefetches (they are not visits),
 * signed-in staff, non-HTML or failed responses. A logging failure never breaks the page.
 */
class RecordPageView
{
    private const BOTS = '/bot|crawl|spider|slurp|facebookexternalhit|preview|headless|lighthouse|curl|wget|python|monitor/i';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && $response->getStatusCode() === 200 && str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            && ! $request->user() && ! preg_match(self::BOTS, (string) $request->userAgent())
            && ! in_array(Str::lower((string) ($request->header('Sec-Purpose') ?? $request->header('X-Sec-Purpose') ?? $request->header('Purpose'))), ['prefetch', 'prefetch;prerender'], true)) {
            try {
                $this->record($request);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $response;
    }

    private function record(Request $request): void
    {
        $locale = app()->getLocale();
        $path = '/'.ltrim($request->path(), '/');
        $postId = null;
        $section = null;
        if (preg_match('#^/(fr|en)/articles/([a-z0-9-]+)\.html$#', $path, $m)) {
            $postId = PostTranslation::where('locale', $m[1])->where('slug', $m[2])->value('post_id');
            if ($postId) {
                Post::whereKey($postId)->increment('views');
            }
        } elseif (preg_match('#^/(fr|en)/([a-z0-9-]+)\.html$#', $path, $m)) {
            $section = $m[2];
        }
        $ref = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);
        $ua = (string) $request->userAgent();

        PageView::create([
            'path' => Str::limit($path, 250, ''),
            'locale' => $locale,
            'post_id' => $postId,
            'section' => $section,
            'referrer_host' => $ref && $ref !== $request->getHost() ? Str::limit($ref, 120, '') : null,
            'country' => preg_match('/^[A-Z]{2}$/', (string) $request->header('CF-IPCountry')) ? $request->header('CF-IPCountry') : null,
            'device' => preg_match('/iPad|Tablet/i', $ua) ? 'tablet' : (preg_match('/Mobi|Android|iPhone/i', $ua) ? 'mobile' : 'desktop'),
            'search' => $request->filled('q') ? Str::limit(Str::lower(trim((string) $request->query('q'))), 100, '') : null,
            'visitor' => substr(hash('sha256', $request->ip().'|'.$ua.'|'.now()->toDateString().'|'.config('app.key')), 0, 16),
        ]);
    }
}
