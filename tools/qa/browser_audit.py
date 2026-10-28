#!/usr/bin/env python3
"""Console errors / failed requests / horizontal overflow / broken images / target size on every page at several viewports.
Usage: python tools/qa/browser_audit.py [--vp 1440,390] [--limit N] [--only substr]
Output: docs/qa/data/browser.json  (uses installed Microsoft Edge via Playwright channel=msedge)"""
import json, sys, argparse, time
from pathlib import Path
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[2] / "prototype_html"
OUT = Path(__file__).resolve().parents[2] / "docs/qa/data"
ap = argparse.ArgumentParser()
ap.add_argument("--vp", default="1440,1024,768,390")
ap.add_argument("--limit", type=int, default=0)
ap.add_argument("--only", default="")
ap.add_argument("--out", default="browser.json")
a = ap.parse_args()
VPS = [int(x) for x in a.vp.split(",")]
pages = [ROOT / "index.html"] + sorted(ROOT.glob("fr/**/*.html")) + sorted(ROOT.glob("en/**/*.html")) + sorted(ROOT.glob("admin/*.html"))
if a.only:
    pages = [p for p in pages if a.only in p.as_posix()]
if a.limit:
    pages = pages[:a.limit]

JS = r"""() => {
  const vw = document.documentElement.clientWidth;
  const out = {sw: document.documentElement.scrollWidth, vw, off: [], small: [], brokenImg: [], noFocusStyle: 0};
  const all = document.querySelectorAll('body *');
  for (const e of all) {
    const cs = getComputedStyle(e);
    if (cs.display === 'none' || cs.visibility === 'hidden' || cs.position === 'fixed') continue;
    const r = e.getBoundingClientRect();
    if (r.width === 0 || r.height === 0) continue;
    if (r.right > vw + 1 && out.off.length < 5) {
      // skip if inside an overflow-x clip container
      let p = e.parentElement, clipped = false;
      while (p && p !== document.body) { const o = getComputedStyle(p).overflowX; if (o !== 'visible') { clipped = true; break; } p = p.parentElement; }
      if (!clipped) out.off.push(e.tagName.toLowerCase() + '.' + String(e.className).toString().slice(0, 40) + ' right=' + Math.round(r.right));
    }
  }
  const sel = 'a[href],button,input:not([type=hidden]),select,textarea,[role=button],[tabindex="0"]';
  for (const e of document.querySelectorAll(sel)) {
    const cs = getComputedStyle(e);
    if (cs.display === 'none' || cs.visibility === 'hidden') continue;
    let hidden = false, p = e;
    while (p && p !== document.body) { const c = getComputedStyle(p); if (c.display === 'none' || c.visibility === 'hidden' || c.opacity === '0' && false) { hidden = true; break; } p = p.parentElement; }
    if (hidden) continue;
    const r = e.getBoundingClientRect();
    if (r.width === 0 || r.height === 0) continue;
    if (e.classList.contains('hp') || e.closest('.sr-only')) continue;
    if (e.tagName === 'A' && cs.display === 'inline' && e.closest('p,li,span,.prose,td')) continue; // inline-in-text exemption (WCAG 2.5.8)
    if ((r.width < 24 || r.height < 24) && out.small.length < 8) out.small.push(e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + '.' + String(e.className).slice(0, 30) + ' ' + Math.round(r.width) + 'x' + Math.round(r.height) + ' "' + (e.getAttribute('aria-label') || e.textContent || '').trim().slice(0, 20) + '"');
  }
  for (const im of document.images) { if (im.complete && im.naturalWidth === 0 && im.currentSrc) out.brokenImg.push(im.currentSrc.slice(-70)); }
  out.h1 = document.querySelectorAll('h1').length;
  out.dom = document.querySelectorAll('*').length;
  return out;
}"""

results = {}
t0 = time.time()
with sync_playwright() as pw:
    br = pw.chromium.launch(channel="msedge")
    for vp in VPS:
        ctx = br.new_context(viewport={"width": vp, "height": 900}, device_scale_factor=1)
        for p in pages:
            rel = p.relative_to(ROOT).as_posix()
            pg = ctx.new_page()
            con, fail, reqs = [], [], [0, 0]
            pg.on("console", lambda m, con=con: con.append(f"{m.type}: {m.text[:200]}") if m.type in ("error", "warning") else None)
            pg.on("pageerror", lambda e, con=con: con.append(f"pageerror: {str(e)[:200]}"))
            pg.on("requestfailed", lambda r, fail=fail: fail.append(f"{r.url[-90:]} {r.failure}"))
            pg.on("response", lambda r, fail=fail, reqs=reqs: (fail.append(f"HTTP {r.status} {r.url[-90:]}") if r.status >= 400 else None, reqs.__setitem__(0, reqs[0] + 1)))
            try:
                pg.goto(p.as_uri(), wait_until="load", timeout=30000)
                pg.wait_for_timeout(250)
                # scroll to trigger lazy images
                pg.evaluate("window.scrollTo(0, document.body.scrollHeight)")
                pg.wait_for_timeout(250)
                pg.evaluate("window.scrollTo(0,0)")
                d = pg.evaluate(JS)
            except Exception as e:
                d = {"error": str(e)[:200]}
            ext = [u for u in fail if "http" in u and "file:" not in u]
            d.update(console=con[:6], failed=fail[:6])
            results.setdefault(rel, {})[vp] = d
            pg.close()
        ctx.close()
        print(f"vp {vp} done {time.time()-t0:.0f}s", flush=True)
    br.close()
json.dump(results, open(OUT / a.out, "w", encoding="utf8"), ensure_ascii=False, indent=1)
# summary
agg = {"console": 0, "failed": 0, "overflow": 0, "broken": 0}
for rel, v in results.items():
    for vp, d in v.items():
        if d.get("console"): agg["console"] += 1
        if d.get("failed"): agg["failed"] += 1
        if d.get("sw", 0) > d.get("vw", 0) + 1: agg["overflow"] += 1
        if d.get("brokenImg"): agg["broken"] += 1
print(agg)
