#!/usr/bin/env python3
"""Round-2 targeted re-verification (D2 D3 D5 D6 D7 D8 D9). Run: python tools/qa/round2_checks.py"""
import re
from pathlib import Path
from bs4 import BeautifulSoup
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[2] / "prototype_html"
FR = re.compile(r"[àâçéèêëîïôùûü]|\b(le|la|les|des|une|est|pour|dans|sur|avec|qui|nous|vous)\b", re.I)

# D5 EN alt/og:image:alt
bad = []
for p in (ROOT / "en/articles").glob("*.html"):
    s = BeautifulSoup(p.read_text(encoding="utf8"), "lxml")
    for im in s.select("article.article img, .article-hero img, figure img"):
        if FR.search(im.get("alt", "")): bad.append((p.name, "alt", im["alt"][:60]))
    m = s.find("meta", property="og:image:alt")
    if m and FR.search(m["content"]): bad.append((p.name, "og:image:alt", m["content"][:60]))
print("D5 French alt/og alt on EN articles:", len(bad), bad[:6])
# D6
for f in ("en/privacy-policy.html", "en/legal-notices.html", "en/cookie-policy.html"):
    t = BeautifulSoup((ROOT / f).read_text(encoding="utf8"), "lxml")
    txt = [x.strip() for x in t.main.stripped_strings if FR.search(x) and not re.search(r"Africa Global Press|Côte d|Cameroun|Sénégal", x)]
    print("D6", f, len(txt), txt[:4])
# D7 + D8
s = BeautifulSoup((ROOT / "fr/politique-cookies.html").read_text(encoding="utf8"), "lxml")
toc = s.select_one(".toc ol")
print("D7 TOC", [a.get_text()[:25] for a in toc.find_all("a")][:3] if toc else None, "list-style:", None)
empty = [f for f in list(ROOT.glob("fr/*.html")) + list(ROOT.glob("en/*.html")) for a in BeautifulSoup(f.read_text(encoding="utf8"), "lxml").find_all("a") if not a.get_text(strip=True) and not a.get("aria-label") and not a.find("img") and not a.find("svg")]
print("D8 empty links:", len(empty), [e.name for e in empty][:4])

with sync_playwright() as pw:
    br = pw.chromium.launch(channel="msedge")
    pg = br.new_page(viewport={"width": 1729, "height": 900})
    pg.goto((ROOT / "fr/index.html").as_uri()); pg.wait_for_timeout(500)
    print("D9 ticker label:", pg.evaluate("(document.querySelector('.ticker')||document.body).innerText.slice(0,30)"))
    print("D9 hero slide1:", pg.evaluate("(document.querySelector('[data-hero] h2, [data-hero] .slide h1, [data-hero] .slide-title')||{}).textContent"))
    for w in (1440, 390, 320):
        pg.set_viewport_size({"width": w, "height": 900})
        for rel in ("fr/index.html", "fr/societe.html", "fr/contact.html", "fr/articles/science-et-technologie-linnovation-africaine-prend-corps-en-2025.html", "en/articles/science-and-technology-african-innovation-takes-shape-in-2025.html"):
            pg.goto((ROOT / rel).as_uri()); pg.wait_for_timeout(250)
            pg.evaluate("document.querySelector('#cookie-banner')&&(document.querySelector('#cookie-banner').hidden=true)")
            sw = pg.evaluate("[document.documentElement.scrollWidth,innerWidth]")
            small = pg.evaluate("""()=>[...document.querySelectorAll('a[href],button,input:not([type=hidden]):not(.hp),select,textarea')].filter(e=>{const c=getComputedStyle(e);if(c.display==='none'||c.visibility==='hidden'||e.closest('[hidden],.sr-only')||e.offsetParent===null&&c.position!=='fixed')return false;const r=e.getBoundingClientRect();if(!r.width)return false;if(e.tagName==='A'&&c.display==='inline'&&e.closest('p,li,span,td'))return false;return r.width<24||r.height<24}).slice(0,6).map(e=>e.tagName+'.'+String(e.className).slice(0,18)+' '+Math.round(e.getBoundingClientRect().width)+'x'+Math.round(e.getBoundingClientRect().height))""")
            print(f"{w} {rel[-45:]} sw={sw} small={small}")
    br.close()
