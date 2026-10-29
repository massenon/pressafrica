/* Africa Global Press service worker (works only over http(s); ignored on file://) */
const CACHE = 'agp-v20261029';
const CORE = ["index.html", "fr/index.html", "en/index.html", "assets/css/app.min.css", "assets/js/app.js", "assets/js/vendor/turbo.min.js", "assets/icons/favicon.svg", "assets/icons/favicon.ico", "assets/icons/icon-192.png", "assets/fonts/Alegreya-normal-400-latin.woff2", "assets/fonts/EncodeSans-normal-400-latin.woff2"];
self.addEventListener('install', e => { e.waitUntil(caches.open(CACHE).then(c => c.addAll(CORE)).then(() => self.skipWaiting())); });
self.addEventListener('activate', e => { e.waitUntil(caches.keys().then(k => Promise.all(k.filter(x => x !== CACHE).map(x => caches.delete(x)))).then(() => self.clients.claim())); });
self.addEventListener('fetch', e => {
  if (e.request.method !== 'GET') return;
  e.respondWith(caches.match(e.request).then(hit => hit || fetch(e.request).then(res => {
    const copy = res.clone(); caches.open(CACHE).then(c => c.put(e.request, copy)); return res;
  }).catch(() => caches.match('index.html'))));
});
