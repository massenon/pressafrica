<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Crawler- and cache-facing headers, set on the final response (after the session and cookie middleware ran):
 * - the admin and any dev/staging copy answer "noindex" (robots.txt alone only blocks crawling, not indexing);
 * - robots.txt, sitemaps, llms.txt, RSS and the PWA files carry no session or CSRF cookie, a public cache lifetime
 *   and an ETag, so crawlers and shared caches can revalidate them with a cheap 304.
 */
class PublicHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('admin', 'admin/*') || app()->environment('local', 'staging')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        if ($request->isMethod('GET') && $response->isSuccessful() && $this->isStaticFile($request)) {
            foreach ($response->headers->getCookies() as $cookie) {
                $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
            }
            // A service worker script must always be re-fetched; the others may be reused for an hour.
            $response->headers->set('Cache-Control', $request->is('sw.js') ? 'public, no-cache' : 'public, max-age=3600');
            if (! $request->is('sw.js') && is_string($body = $response->getContent())) {
                $response->setEtag(md5($body));
                $response->isNotModified($request);
            }
        }

        return $response;
    }

    private function isStaticFile(Request $request): bool
    {
        return $request->is('robots.txt', 'llms.txt', 'sitemap.xml', 'sitemap-*.xml', 'news-sitemap.xml', 'manifest.webmanifest', 'sw.js', '*/feed.xml');
    }
}
