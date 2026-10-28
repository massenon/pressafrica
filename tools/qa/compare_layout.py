#!/usr/bin/env python3
"""Layout fidelity: screenshot our pages at the reference width and build side-by-side (ref | ours) images,
plus section-order / geometry metrics. Usage: python tools/qa/compare_layout.py
Output: docs/qa/shots/cmp-<page>-<n>.png , docs/qa/data/layout.json"""
import json
from pathlib import Path
from PIL import Image
from playwright.sync_api import sync_playwright

Image.MAX_IMAGE_PIXELS = None
BASE = Path(__file__).resolve().parents[2]
ROOT = BASE / "prototype_html"
SH = BASE / "docs/qa/shots"
SH.mkdir(parents=True, exist_ok=True)
REF = {p.name: p for p in (BASE / "clone").glob("*.png")}


def ref(sub):
    m = [p for n, p in REF.items() if sub in n]
    return sorted(m)[0] if m else None


PAIRS = {
    "home": ("pressafrica-media-2026-10-19-01_56", "fr/index.html"),
    "societe": ("category-societe", "fr/societe.html"),
    "finance": ("category-finance", "fr/finance.html"),
    "presse-en-ligne": ("category-presse-en-ligne", "fr/presse-en-ligne.html"),
    "politique": ("actualites-politiques", "fr/politique.html"),
    "economie": ("actualites-economiques", "fr/economie.html"),
    "culture": ("actualites-culturelles", "fr/culture.html"),
    "sport": ("actualites-sportives", "fr/sport.html"),
    "contact": ("-contact-", "fr/contact.html"),
    "mentions": ("mentions-legales", "fr/mentions-legales.html"),
    "privacy": ("politique-de-confidentialite", "fr/politique-de-confidentialite.html"),
    "cookies": ("politique-en-matiere-de-cookies", "fr/politique-cookies.html"),
    "about": ("qui-sommes-nous", "fr/qui-sommes-nous.html"),
}
W = 1729  # reference captured at 3458 px @2x
out = {}
with sync_playwright() as pw:
    br = pw.chromium.launch(channel="msedge")
    pg = br.new_page(viewport={"width": W, "height": 1000})
    for key, (sub, page) in PAIRS.items():
        rp = ref(sub)
        pg.goto((ROOT / page).as_uri(), wait_until="load")
        pg.evaluate("""async()=>{for(let y=0;y<document.body.scrollHeight;y+=350){window.scrollTo(0,y);await new Promise(r=>setTimeout(r,160))}window.scrollTo(0,0)}""")
        pg.wait_for_timeout(900)
        mine = SH / f"ours-{key}-1729.png"
        pg.screenshot(path=str(mine), full_page=True)
        # section geometry
        geo = pg.evaluate("""()=>{const q=s=>document.querySelector(s);const r=e=>e?{y:Math.round(e.getBoundingClientRect().top+scrollY),h:Math.round(e.getBoundingClientRect().height)}:null;
          const secs=[...document.querySelectorAll('body > header, body > nav, body > main > *, main > section, body > footer, footer, header, .ticker, .topbar')].slice(0,40).map(e=>({tag:e.tagName.toLowerCase(),cls:String(e.className).slice(0,40),id:e.id,...r(e)}));
          return {height:document.documentElement.scrollHeight,secs}}""")
        a = Image.open(rp).convert("RGB") if rp else None
        b = Image.open(mine).convert("RGB")
        rec = {"ref": rp.name if rp else None, "ref_css_h": round(a.height / 2) if a else None, "our_h": b.height, "geo": geo["secs"][:25]}
        out[key] = rec
        if a:
            sc = 700 / a.width
            a2 = a.resize((700, int(a.height * sc)))
            b2 = b.resize((700, int(b.height * 700 / b.width)))
            n = 0
            seg = 1400
            H = max(a2.height, b2.height)
            for y in range(0, H, seg):
                im = Image.new("RGB", (1420, seg), "white")
                im.paste(a2.crop((0, y, 700, y + seg)), (0, 0))
                im.paste(b2.crop((0, y, 700, y + seg)), (720, 0))
                im.save(SH / f"cmp-{key}-{n}.png", optimize=True)
                n += 1
                if n >= 8:
                    break
            rec["segments"] = n
        print(key, rec["ref_css_h"], rec["our_h"], flush=True)
    br.close()
json.dump(out, open(BASE / "docs/qa/data/layout.json", "w", encoding="utf8"), ensure_ascii=False, indent=1)
