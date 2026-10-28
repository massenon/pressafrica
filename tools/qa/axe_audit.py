#!/usr/bin/env python3
"""axe-core via Playwright (axe from AXE_JS env var path or scratchpad npm install). Usage: AXE_JS=.../axe.min.js python tools/qa/axe_audit.py"""
import os, json
from pathlib import Path
from playwright.sync_api import sync_playwright
ROOT = Path(__file__).resolve().parents[2] / "prototype_html"
AXE = open(os.environ["AXE_JS"], encoding="utf8").read()
T = [("fr/index.html", False), ("en/index.html", False), ("en/articles/cape-verde-at-the-mercy-of-natural-disasters.html", False), ("en/society.html", False), ("fr/societe.html", False), ("fr/contact.html", False), ("en/contact.html", False), ("admin/login.html", True), ("admin/posts.html", True), ("admin/post-edit.html", True)]
out = {}
with sync_playwright() as pw:
    br = pw.chromium.launch(channel="msedge")
    for vp in (1440, 390):
        ctx = br.new_context(viewport={"width": vp, "height": 900}); pg = ctx.new_page()
        pg.goto((ROOT / "admin/login.html").as_uri()); pg.click("#demo button >> nth=0"); pg.wait_for_timeout(700)
        for rel, adm in T:
            pg.goto((ROOT / rel).as_uri()); pg.wait_for_timeout(700)
            pg.evaluate("document.querySelector('#cookie-banner')&&(document.querySelector('#cookie-banner').hidden=true)")
            pg.evaluate(AXE)
            r = pg.evaluate("axe.run(document,{runOnly:['wcag2a','wcag2aa','wcag21aa','wcag22aa','best-practice']}).then(r=>r.violations.map(v=>({id:v.id,impact:v.impact,n:v.nodes.length,ex:v.nodes[0].html.slice(0,110)})))")
            out[f"{rel}@{vp}"] = r
            print(rel[-40:], vp, [(v['id'], v['impact'], v['n']) for v in r])
        ctx.close()
    br.close()
json.dump(out, open(ROOT.parent / "docs/qa/data/axe.json", "w", encoding="utf8"), ensure_ascii=False, indent=1)
