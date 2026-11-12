// Screenshots of the local Laravel site used by social-preview.html (run with `php artisan serve --port=8123`).
const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch();
  const hide = '#cookie-banner,.cookie{display:none!important}';
  const shot = async (url, viewport, file, scale) => {
    const p = await b.newPage({ viewport, deviceScaleFactor: scale });
    await p.goto('http://127.0.0.1:8123' + url, { waitUntil: 'networkidle' });
    await p.addStyleTag({ content: hide });
    // Lazy images below the fold: load them before the shot.
    await p.evaluate(() => document.querySelectorAll('img[loading=lazy]').forEach(i => { i.loading = 'eager'; }));
    await p.waitForLoadState('networkidle');
    await p.waitForTimeout(800);
    await p.screenshot({ path: file });
    await p.close();
  };
  await shot('/fr', { width: 1366, height: 860 }, 'desktop.png', 1);
  await shot('/fr', { width: 390, height: 844 }, 'mobile.png', 2);
  await b.close();
})();
