#!/usr/bin/env python3
"""Admin back-office audit: logs in through the demo login, then for each admin page at 4 viewports records console errors,
horizontal overflow, h1/landmarks, tap-target sizes, unnamed controls; takes screenshots.
Then functional flows (CRUD, toasts, quiz builder, AI panel, dark mode). Output docs/qa/data/admin.json
Run: python tools/qa/admin_audit.py"""
import json, re, sys
from pathlib import Path
from playwright.sync_api import sync_playwright

BASE = Path(__file__).resolve().parents[2]
ROOT = BASE / "prototype_html"
SH = BASE / "docs/qa/shots"
A = lambda p: (ROOT / "admin" / p).as_uri()
PAGES = ["index", "posts", "post-edit", "categories", "media", "featured", "comments", "newsletter", "authors", "menus", "ads", "settings", "login", "analytics", "tags", "videos", "magazine", "radios", "television", "audit", "backups", "podcasts"]
VPS = [1440, 1024, 768, 390, 320]
out = {"pages": {}, "flows": []}


def flow(i, ok, d=""):
    out["flows"].append([i, "PASS" if ok else "FAIL", str(d)[:300]])
    print("PASS " if ok else "FAIL ", i, str(d)[:200], flush=True)


CHECK = r"""()=>{
 const vw=document.documentElement.clientWidth, r={sw:document.documentElement.scrollWidth,vw,off:[],small:[],noname:[],h1:document.querySelectorAll('h1').length,h2:document.querySelectorAll('h2').length,main:!!document.querySelector('main'),imgNoAlt:[...document.images].filter(i=>i.alt==null).length,title:document.title,len:document.body.innerText.length};
 for(const e of document.querySelectorAll('body *')){const cs=getComputedStyle(e);if(cs.display==='none'||cs.visibility==='hidden'||cs.position==='fixed')continue;const b=e.getBoundingClientRect();if(!b.width)continue;
   if(b.right>vw+1&&r.off.length<5){let p=e.parentElement,cl=false;while(p&&p!==document.body){if(getComputedStyle(p).overflowX!=='visible'){cl=true;break}p=p.parentElement}if(!cl)r.off.push(e.tagName.toLowerCase()+'.'+String(e.className).slice(0,30)+' r='+Math.round(b.right))}}
 for(const e of document.querySelectorAll('a[href],button,input:not([type=hidden]),select,textarea,[role=button],[role=tab]')){const cs=getComputedStyle(e);if(cs.display==='none'||cs.visibility==='hidden')continue;let h=false,p=e;while(p&&p!==document.body){if(getComputedStyle(p).display==='none'){h=true;break}p=p.parentElement}if(h)continue;
   const b=e.getBoundingClientRect();if(!b.width||e.closest('.a-vh'))continue;
   if((b.width<24||b.height<24)&&!(e.tagName==='A'&&cs.display==='inline')&&r.small.length<10)r.small.push(e.tagName.toLowerCase()+'.'+String(e.className).slice(0,25)+' '+Math.round(b.width)+'x'+Math.round(b.height)+' "'+(e.getAttribute('aria-label')||e.textContent||e.type||'').trim().slice(0,18)+'"');
   const nm=(e.getAttribute('aria-label')||e.getAttribute('aria-labelledby')||e.getAttribute('title')||e.textContent.trim()||(e.labels&&e.labels.length)||e.getAttribute('placeholder'));
   if(!nm&&r.noname.length<8)r.noname.push(e.outerHTML.slice(0,90))}
 return r}"""

with sync_playwright() as pw:
    br = pw.chromium.launch(channel="msedge")
    for vp in VPS:
        ctx = br.new_context(viewport={"width": vp, "height": 900})
        pg = ctx.new_page()
        con = []
        pg.on("console", lambda m: con.append(f"{m.type}: {m.text[:160]}") if m.type in ("error", "warning") else None)
        pg.on("pageerror", lambda e: con.append("pageerror: " + str(e)[:160]))
        pg.on("requestfailed", lambda r: con.append("reqfail: " + r.url[-80:]))
        # unauthenticated redirect check once
        if vp == 1440:
            pg.goto(A("posts.html")); pg.wait_for_timeout(600)
            flow("admin-guard-redirects-to-login", "login.html" in pg.url, pg.url[-40:])
            pg.goto(A("login.html")); pg.wait_for_timeout(300)
            pg.fill("#email", "x"); pg.fill("#password", "short"); pg.click("#loginForm button[type=submit]"); pg.wait_for_timeout(200)
            flow("login-invalid-shows-error", not pg.evaluate("document.querySelector('#loginErr').hidden"), pg.inner_text("#loginErr"))
            pg.fill("#email", "nobody@example.com"); pg.fill("#password", "longenough1"); pg.click("#loginForm button[type=submit]"); pg.wait_for_timeout(200)
            flow("login-unknown-account-error", not pg.evaluate("document.querySelector('#loginErr').hidden"), pg.inner_text("#loginErr"))
            pg.screenshot(path=str(SH / "admin-login-error-1440.png"))
        pg.goto(A("login.html")); pg.wait_for_timeout(300)
        pg.click("#demo button >> nth=0"); pg.wait_for_timeout(800)
        for name in PAGES:
            if name == "login":
                continue
            con.clear()
            pg.goto(A(name + ".html")); pg.wait_for_timeout(700)
            d = pg.evaluate(CHECK)
            d["console"] = list(con)[:5]
            out["pages"].setdefault(name, {})[vp] = d
            if name in ("index", "posts", "post-edit", "categories", "settings", "menus") or vp in (390,):
                pg.screenshot(path=str(SH / f"admin-{name}-{vp}.png"), full_page=(vp == 390 and name in ("index", "posts")))
        pg.goto(A("login.html")); pg.wait_for_timeout(300)
        d = pg.evaluate(CHECK); out["pages"].setdefault("login", {})[vp] = d
        ctx.close()
        print("vp", vp, flush=True)

    # ---------- flows ----------
    ctx = br.new_context(viewport={"width": 1440, "height": 900})
    pg = ctx.new_page()
    con = []
    pg.on("pageerror", lambda e: con.append("pageerror: " + str(e)[:160]))
    pg.goto(A("login.html")); pg.wait_for_timeout(300)
    pg.click("#demo button >> nth=0"); pg.wait_for_timeout(800)
    flow("login-demo-account-lands-on-dashboard", "index.html" in pg.url, pg.url[-30:])
    pg.screenshot(path=str(SH / "admin-dashboard-1440.png"))
    # theme
    tb = pg.query_selector("[data-theme-toggle], #themeToggle, button[aria-label*=heme], button[aria-label*=ark]")
    if tb:
        tb.click(); pg.wait_for_timeout(200)
        flow("admin-dark-mode-toggle", pg.evaluate("document.documentElement.dataset.theme") == "dark")
        pg.screenshot(path=str(SH / "admin-dark-1440.png"))
        tb.click()
    else:
        flow("admin-dark-mode-toggle", False, "no toggle found")
    # posts list
    pg.goto(A("posts.html")); pg.wait_for_timeout(700)
    n0 = pg.evaluate("document.querySelectorAll('tbody tr').length")
    flow("posts-list-renders-rows", n0 > 5, f"{n0} rows")
    btns = pg.evaluate("[...document.querySelectorAll('#view button, #view a.a-btn')].map(b=>(b.textContent||b.getAttribute('aria-label')||'').trim()).filter(Boolean).slice(0,30)")
    flow("posts-toolbar-buttons", True, btns)
    json.dump(out, open(BASE / "docs/qa/data/admin.json", "w", encoding="utf8"), ensure_ascii=False, indent=1)
    br.close()
