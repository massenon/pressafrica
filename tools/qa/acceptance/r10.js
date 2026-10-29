// PRD R10: no horizontal scroll at 1440, 1024, 768 and 390 px on the main pages (Chromium; Firefox and Safari
// need a machine with those browsers). Needs `php artisan serve --port=8123`. Usage: node r10.js
const { chromium } = require('playwright');
(async () => {
  const pages = ['/fr/index.html', '/fr/articles/cap-vert-sous-le-diktat-des-catastrophes-naturelles.html', '/fr/politique.html',
    '/fr/podcasts.html', '/en/index.html', '/admin/login'];
  const b = await chromium.launch(); let bad = 0;
  for (const w of [1440, 1024, 768, 390]) {
    const p = await b.newPage({ viewport: { width: w, height: 900 } });
    for (const path of pages) {
      await p.goto('http://127.0.0.1:8123' + path, { waitUntil: 'networkidle' });
      const o = await p.evaluate(() => ({ sw: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth }));
      const ok = o.sw <= o.cw + 1; if (!ok) bad++;
      console.log(String(w).padEnd(5), ok ? 'ok  ' : 'OVER', path, ok ? '' : `${o.sw} > ${o.cw}`);
    }
    await p.close();
  }
  await b.close(); process.exit(bad ? 1 : 0);
})();
