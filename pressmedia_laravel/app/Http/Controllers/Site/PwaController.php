<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/** Installable site (Settings > PWA): web manifest and a service worker built for a news site. */
class PwaController extends Controller
{
    public function manifest(): JsonResponse
    {
        abort_unless(Settings::get('pwa'), 404);

        return response()->json([
            'name' => Settings::get('pwaName'), 'short_name' => Settings::get('pwaShort'),
            'description' => __('seo.desc.home', [], 'fr'), 'start_url' => '/fr', 'scope' => '/', 'display' => 'standalone',
            'background_color' => '#ffffff', 'theme_color' => Settings::get('pwaTheme'), 'lang' => 'fr',
            'icons' => [['src' => '/assets/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/assets/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any']],
        ], 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Unlike the prototype's cache-everything worker: pages network-first (fresh news; cached copy only offline),
     * static assets cache-first, never the admin, the API, uploads in progress or anything but GET.
     */
    public function worker(): Response
    {
        abort_unless(Settings::get('pwa'), 404);
        $version = substr(md5((string) @filemtime(public_path('assets/css/app.min.css')).config('app.key')), 0, 8);
        $js = <<<JS
const CACHE = 'agp-$version';
const CORE = ['/fr', '/en', '/assets/css/app.min.css', '/assets/js/app.js', '/assets/js/vendor/turbo.min.js', '/assets/icons/favicon.svg'];
self.addEventListener('install', e => { e.waitUntil(caches.open(CACHE).then(c => c.addAll(CORE)).then(() => self.skipWaiting())); });
self.addEventListener('activate', e => { e.waitUntil(caches.keys().then(k => Promise.all(k.filter(x => x !== CACHE).map(x => caches.delete(x)))).then(() => self.clients.claim())); });
self.addEventListener('fetch', e => {
  const u = new URL(e.request.url);
  if (e.request.method !== 'GET' || u.origin !== location.origin || /^\/(admin|api|livewire|ad)\//.test(u.pathname)) return;
  if (u.pathname.startsWith('/assets/')) {
    e.respondWith(caches.match(e.request).then(hit => hit || fetch(e.request).then(res => { const c = res.clone(); caches.open(CACHE).then(x => x.put(e.request, c)); return res; })));
    return;
  }
  if (e.request.mode === 'navigate') {
    e.respondWith(fetch(e.request).then(res => { const c = res.clone(); caches.open(CACHE).then(x => x.put(e.request, c)); return res; })
      .catch(() => caches.match(e.request).then(hit => hit || caches.match('/fr'))));
  }
});
JS;

        return response($js, 200, ['Content-Type' => 'application/javascript', 'Service-Worker-Allowed' => '/', 'Cache-Control' => 'no-cache']);
    }
}
