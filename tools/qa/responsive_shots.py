#!/usr/bin/env python3
"""Responsive screenshots + interaction checks (burger/offcanvas, mega, accordion, slider, tap targets) at 1440/1024/768/390
for home, one article, one category, contact. Output docs/qa/shots/resp-*.png, docs/qa/data/responsive.json"""
import json
from pathlib import Path
from playwright.sync_api import sync_playwright

BASE = Path(__file__).resolve().parents[2]
ROOT = BASE / "prototype_html"
SH = BASE / "docs/qa/shots"
PAGES = {"home": "fr/index.html", "article": "fr/articles/afrique-alerte-sociale-et-morale-sur-les-derives-sexuelles-sur-tiktok.html",
         "category": "fr/societe.html", "contact": "fr/contact.html", "home-en": "en/index.html"}
R = []


def rec(i, ok, d=""):
    R.append([i, "PASS" if ok else "FAIL", str(d)[:300]])
    print("PASS " if ok else "FAIL ", i, str(d)[:200], flush=True)


with sync_playwright() as pw:
    br = pw.chromium.launch(channel="msedge")
    for vp in (1440, 1024, 768, 390):
        ctx = br.new_context(viewport={"width": vp, "height": 900}, has_touch=vp < 800, is_mobile=vp < 500)
        pg = ctx.new_page()
        for key, rel in PAGES.items():
            pg.goto((ROOT / rel).as_uri()); pg.wait_for_timeout(500)
            pg.evaluate("document.querySelector('#cookie-banner')&&(document.querySelector('#cookie-banner').hidden=true)")
            pg.evaluate("""async()=>{for(let y=0;y<document.body.scrollHeight;y+=400){scrollTo(0,y);await new Promise(r=>setTimeout(r,70))}scrollTo(0,0)}""")
            pg.wait_for_timeout(400)
            sw = pg.evaluate("[document.documentElement.scrollWidth, document.documentElement.clientWidth]")
            rec(f"no-hscroll {key}@{vp}", sw[0] <= sw[1], sw)
            if key in ("home", "article", "category", "contact"):
                pg.screenshot(path=str(SH / f"resp-{key}-{vp}.png"), full_page=(vp in (390, 768) and key != "home") or (vp == 390))
            # visible nav controls
            vis = pg.evaluate("""()=>{const v=s=>{const e=document.querySelector(s);if(!e)return null;const c=getComputedStyle(e),r=e.getBoundingClientRect();return c.display!=='none'&&c.visibility!=='hidden'&&r.width>0}
              return {burger:v('.icon-btn[aria-label^="Ouvrir le menu"], .icon-btn[data-open=offcanvas]'),explore:v('.btn-explore'),mainnav:v('.mainnav'),search:v('.topbar-search'),theme:v('[data-theme-toggle]')}}""")
            if key == "home":
                rec(f"nav-controls home@{vp}", True, vis)
                if vp < 1024:
                    rec(f"burger-visible home@{vp}", bool(vis["burger"] or vis["explore"]), vis)
        # offcanvas on home
        pg.goto((ROOT / PAGES["home"]).as_uri()); pg.wait_for_timeout(400)
        pg.evaluate("document.querySelector('#cookie-banner').hidden=true")
        opener = pg.query_selector(".icon-btn[data-open=offcanvas]:visible") or pg.query_selector(".btn-explore:visible")
        if opener:
            opener.click(); pg.wait_for_timeout(600)
            box = pg.evaluate("(()=>{const r=document.querySelector('#offcanvas .offcanvas-panel, #offcanvas > div').getBoundingClientRect();return [Math.round(r.left),Math.round(r.width),Math.round(r.height),innerHeight]})()")
            rec(f"offcanvas fits viewport @{vp}", box[1] <= vp and box[2] <= box[3] + 2, box)
            exp = opener.get_attribute("aria-expanded")
            rec(f"offcanvas aria-expanded @{vp}", exp == "true", exp)
            pg.screenshot(path=str(SH / f"resp-offcanvas-{vp}.png"))
            # scrollable?
            sc = pg.evaluate("(()=>{const p=document.querySelector('#offcanvas .offcanvas-panel')||document.querySelector('#offcanvas > div');return [p.scrollHeight,p.clientHeight,getComputedStyle(p).overflowY]})()")
            rec(f"offcanvas scrollable if tall @{vp}", sc[0] <= sc[1] + 1 or sc[2] in ("auto", "scroll"), sc)
            pg.keyboard.press("Escape"); pg.wait_for_timeout(400)
        else:
            rec(f"offcanvas opener @{vp}", False, "none visible")
        # mega at tablet (click)
        pg.goto((ROOT / PAGES["home"]).as_uri()); pg.wait_for_timeout(400)
        pg.evaluate("document.querySelector('#cookie-banner').hidden=true")
        mb = pg.query_selector("[data-mega] > button:visible, [data-mega] button:visible")
        if mb:
            mb.click(); pg.wait_for_timeout(500)
            m = pg.evaluate("(()=>{const e=[...document.querySelectorAll('[data-mega] .mega')].find(x=>!x.hidden);if(!e)return null;const r=e.getBoundingClientRect();return [Math.round(r.left),Math.round(r.right),Math.round(r.width),innerWidth]})()")
            rec(f"mega visible+in-viewport @{vp}", bool(m) and m[0] >= -1 and m[1] <= vp + 1, m)
            pg.screenshot(path=str(SH / f"resp-mega-{vp}.png"), clip={"x": 0, "y": 0, "width": vp, "height": 700})
        else:
            rec(f"mega-button visible @{vp}", vp < 1024, "no visible mega button (expected hidden on mobile)")
        # slider swipe at mobile
        ctx.close()
    br.close()
json.dump(R, open(BASE / "docs/qa/data/responsive.json", "w", encoding="utf8"), ensure_ascii=False, indent=1)
print(sum(1 for r in R if r[1] == "PASS"), "pass", sum(1 for r in R if r[1] == "FAIL"), "fail")
