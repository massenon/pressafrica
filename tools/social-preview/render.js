// Renders social-preview.html to docs/social-preview.png (1280x640, GitHub Social Preview size).
const { chromium } = require('playwright');
const path = require('path');
(async () => {
  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 1280, height: 640 } });
  await p.goto('file://' + path.join(__dirname, 'social-preview.html'), { waitUntil: 'networkidle' });
  await p.evaluate(() => document.fonts.ready);
  await p.screenshot({ path: path.join(__dirname, '../../docs/social-preview.png') });
  await b.close();
})();
