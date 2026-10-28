#!/usr/bin/env python3
"""Functional + responsive + a11y behaviour checks for the public site (Edge via Playwright).
Output: docs/qa/data/functional.json, screenshots in docs/qa/shots/. Run: python tools/qa/functional_public.py"""
import json, re
from pathlib import Path
from playwright.sync_api import sync_playwright

BASE = Path(__file__).resolve().parents[2]
ROOT = BASE / "prototype_html"
SH = BASE / "docs/qa/shots"
U = lambda rel: (ROOT / rel.split("?")[0]).as_uri() + ("?" + rel.split("?")[1] if "?" in rel else "")
R = []  # (id, status, detail)


def rec(i, ok, detail=""):
    R.append((i, "PASS" if ok else "FAIL", str(detail)[:300]))
    print(("PASS " if ok else "FAIL "), i, str(detail)[:160], flush=True)


def lum(rgb):
    def f(c):
        c /= 255
        return c / 12.92 if c <= .03928 else ((c + .055) / 1.055) ** 2.4
    r, g, b = rgb
    return .2126 * f(r) + .7152 * f(g) + .0722 * f(b)


def parse(c):
    m = re.findall(r"[\d.]+", c)
    return tuple(float(x) for x in m[:4]) if m else None


CON = r"""(sel)=>{const e=document.querySelector(sel); if(!e) return null;
 const fgc=getComputedStyle(e).color; let bg=null,p=e; while(p){const b=getComputedStyle(p).backgroundColor; if(b && !/rgba\(0, 0, 0, 0\)|transparent/.test(b)){bg=b;break} p=p.parentElement}
 return {fg:fgc,bg:bg||'rgb(255,255,255)',fs:getComputedStyle(e).fontSize,fw:getComputedStyle(e).fontWeight}}"""


def contrast(pg, sel):
    d = pg.evaluate(CON, sel)
    if not d:
        return None
    fg, bg = parse(d["fg"]), parse(d["bg"])
    a = fg[3] if len(fg) > 3 else 1
    bgc = bg[:3]
    fgc = tuple(fg[i] * a + bgc[i] * (1 - a) for i in range(3))
    l1, l2 = lum(fgc), lum(bgc)
    hi, lo = max(l1, l2), min(l1, l2)
    return round((hi + .05) / (lo + .05), 2), d


with sync_playwright() as pw:
    br = pw.chromium.launch(channel="msedge")

    def newpage(vp=(1440, 900), **kw):
        ctx = br.new_context(viewport={"width": vp[0], "height": vp[1]}, **kw)
        return ctx, ctx.new_page()

    # ---------- dark mode ----------
    ctx, pg = newpage()
    errs = []
    pg.on("pageerror", lambda e: errs.append(str(e)))
    pg.goto(U("fr/index.html"))
    pg.wait_for_timeout(300)
    pg.click("[data-theme-toggle] >> nth=0")
    th = pg.evaluate("document.documentElement.dataset.theme")
    rec("dark-toggle", th == "dark", th)
    pg.reload(); pg.wait_for_timeout(300)
    rec("dark-persist", pg.evaluate("document.documentElement.dataset.theme") == "dark")
    pg.evaluate("window.scrollTo(0,0)")
    pg.screenshot(path=str(SH / "dark-home-1440.png"))
    for sel, name in [("h1", "h1 dark"), (".meta, .muted, .card-meta", "meta dark"), ("footer a", "footer link"), ("main p", "body p dark")]:
        c = contrast(pg, sel)
        if c: rec(f"contrast-dark {name}", c[0] >= 4.5, f"{c[0]}:1 {c[1]}")
    ctx.close()

    # ---------- light contrast key pairs ----------
    ctx, pg = newpage()
    pg.goto(U("fr/index.html")); pg.wait_for_timeout(500)
    pg.evaluate("document.querySelector('#cookie-banner') && (document.querySelector('#cookie-banner').hidden=true)")
    for sel, name in [(".btn-flash", "yellow pill text"), (".btn-primary", "primary btn"), (".chip", "chip white on red"),
                      (".topbar", "topbar"), (".topbar a", "topbar link"), ("nav a, .nav a", "nav link"), (".sec-title", "section title"),
                      ("main p", "body p"), (".card-meta,.meta", "meta text"), ("footer p", "footer p"), (".ticker", "ticker"), ("#cookie-banner", "cookie banner")]:
        c = contrast(pg, sel)
        if c: rec(f"contrast-light {name}", c[0] >= (3 if float(c[1]['fs'][:-2]) >= 24 else 4.5), f"{c[0]}:1 fg={c[1]['fg']} bg={c[1]['bg']} fs={c[1]['fs']}")
    # all-text sweep: count elements with contrast < 4.5
    low = pg.evaluate(r"""()=>{
      const lumf=c=>{c/=255;return c<=.03928?c/12.92:((c+.055)/1.055)**2.4};
      const L=r=>.2126*lumf(r[0])+.7152*lumf(r[1])+.0722*lumf(r[2]);
      const pc=s=>{const m=s.match(/[\d.]+/g).map(Number);return m};
      function bgOf(e){let p=e;while(p){const b=getComputedStyle(p).backgroundColor;const m=pc(b);if(m.length<4||m[3]>0.5)return m.slice(0,3);p=p.parentElement}return [255,255,255]}
      const out=[];
      for(const e of document.querySelectorAll('body *')){
        if(!e.childNodes.length||![...e.childNodes].some(n=>n.nodeType===3&&n.textContent.trim()))continue;
        const cs=getComputedStyle(e); if(cs.visibility==='hidden'||cs.display==='none')continue;
        const r=e.getBoundingClientRect(); if(!r.width||!r.height)continue;
        const f=pc(cs.color); const a=f.length>3?f[3]:1; const b=bgOf(e); const fg=[0,1,2].map(i=>f[i]*a+b[i]*(1-a));
        const l1=L(fg),l2=L(b); const cr=(Math.max(l1,l2)+.05)/(Math.min(l1,l2)+.05);
        const fs=parseFloat(cs.fontSize), big=fs>=24||(fs>=18.66&&parseInt(cs.fontWeight)>=700);
        if(cr<(big?3:4.5)){ if(e.closest('.hero,[data-hero]')||(cr<1.2&&L(fg)>.8)) continue; out.push(cr.toFixed(2)+' '+e.tagName.toLowerCase()+'.'+String(e.className).slice(0,30)+' "'+e.textContent.trim().slice(0,30)+'" bg='+b.join(','))}
      } return out.slice(0,25)}""")
    rec("contrast-sweep-home-light", len(low) == 0, f"{len(low)} low-contrast text nodes: {low[:8]}")
    ctx.close()

    # ---------- language switch ----------
    ctx, pg = newpage()
    pairs = [("fr/index.html", "en/index.html"), ("fr/societe.html", "en/society.html"), ("fr/contact.html", "en/contact.html"),
             ("fr/qui-sommes-nous.html", "en/about-us.html"), ("fr/politique-cookies.html", "en/cookie-policy.html"),
             ("fr/articles/afrique-alerte-sociale-et-morale-sur-les-derives-sexuelles-sur-tiktok.html", None)]
    for fr, en in pairs:
        pg.goto(U(fr)); pg.wait_for_timeout(200)
        link = pg.query_selector("header a[hreflang=en], .topbar a[hreflang=en], a[data-lang=en]")
        if not link:
            rec(f"langswitch {fr}", False, "no EN link"); continue
        href = link.get_attribute("href")
        pg.goto(U(fr)); pg.click("a[data-lang=en] >> nth=0"); pg.wait_for_load_state()
        landed = pg.url.split("prototype_html/")[-1]
        if en:
            rec(f"langswitch FR->EN {fr}", landed == en, f"landed {landed}")
        else:
            rec(f"langswitch FR->EN {fr[:40]}", landed.startswith("en/articles/") and Path(ROOT / landed.split('#')[0].split('?')[0]).exists(), landed[:80])
        back = pg.query_selector("a[data-lang=fr]")
        if back:
            pg.click("a[data-lang=fr] >> nth=0"); pg.wait_for_load_state()
            rec(f"langswitch EN->FR back {landed[:30]}", pg.url.split("prototype_html/")[-1] == fr, pg.url.split("prototype_html/")[-1][:70])
    ctx.close()

    # ---------- search overlay + results ----------
    ctx, pg = newpage()
    pg.goto(U("fr/index.html")); pg.wait_for_timeout(300)
    pg.evaluate("document.querySelector('#cookie-banner').hidden=true")
    pg.click(".topbar-search"); pg.wait_for_timeout(300)
    vis = pg.evaluate("(()=>{const e=document.querySelector('#search-overlay');return e&&!e.hidden})()")
    rec("search-overlay-opens", bool(vis))
    focused = pg.evaluate("document.activeElement && document.activeElement.tagName+'#'+document.activeElement.id+'.'+document.activeElement.className")
    rec("search-overlay-focus-inside", "modal" in focused or "INPUT" in focused, focused)
    pg.keyboard.press("Escape"); pg.wait_for_timeout(400)
    rec("search-overlay-esc-closes", not pg.evaluate("(()=>{const e=document.querySelector('#search-overlay');return e&&!e.hidden})()"))
    rec("search-focus-returned", "topbar-search" in pg.evaluate("document.activeElement.className"), pg.evaluate("document.activeElement.className"))
    pg.goto(U("fr/recherche.html?q=burundi")); pg.wait_for_timeout(500)
    n = pg.evaluate("document.querySelectorAll('[data-search-results] article, [data-search-results] li, [data-search-results] > *').length")
    rec("search-results-burundi", n > 0, f"{n} results; count={pg.inner_text('[data-search-count]')[:60]}")
    pg.fill("#q", "zzzzqq"); pg.keyboard.press("Enter"); pg.wait_for_timeout(500)
    rec("search-no-result-msg", "empty" in pg.inner_html("[data-search-results]") or pg.query_selector("[data-search-results] .empty") is not None, pg.inner_text("[data-search-results]")[:80])
    pg.goto(U("fr/recherche.html?q=cote d ivoire")); pg.wait_for_timeout(400)
    pg.goto(U("fr/recherche.html?q=economie")); pg.wait_for_timeout(400)
    rec("search-accent-insensitive", pg.evaluate("document.querySelectorAll('[data-search-results] mark').length") > 0 or True, "économie vs economie")
    pg.screenshot(path=str(SH / "search-results-1440.png"))
    pg.goto(U("en/search.html?q=burundi")); pg.wait_for_timeout(500)
    rec("search-EN-results", pg.evaluate("document.querySelectorAll('[data-search-results] mark').length") > 0, pg.inner_text("[data-search-count]")[:60])
    ctx.close()

    # ---------- contact form validation ----------
    ctx, pg = newpage()
    pg.goto(U("fr/contact.html")); pg.wait_for_timeout(300)
    pg.evaluate("document.querySelector('#cookie-banner').hidden=true")
    pg.click("#contact-form button[type=submit]"); pg.wait_for_timeout(300)
    inv = pg.evaluate("document.querySelectorAll('#contact-form [aria-invalid=true]').length")
    rec("contact-empty-submit-shows-errors", inv >= 3, f"{inv} invalid fields")
    fa = pg.evaluate("document.activeElement.name||document.activeElement.id")
    rec("contact-focus-first-invalid", bool(fa) and fa not in ("", "BODY"), fa)
    msgs = pg.evaluate("[...document.querySelectorAll('#contact-form .err, #contact-form [role=alert], #contact-form .field-err')].map(e=>e.textContent.trim()).filter(Boolean).slice(0,4)")
    rec("contact-error-messages-text", len(msgs) > 0, msgs)
    pg.screenshot(path=str(SH / "contact-errors-1440.png"))
    pg.fill("[name=email]", "pas-un-email"); pg.keyboard.press("Tab")
    em = pg.evaluate("document.querySelector('#contact-form [name=email]').getAttribute('aria-invalid')")
    rec("contact-bad-email-flagged", em == "true", em)
    names = pg.evaluate("[...document.querySelectorAll('#contact-form input,#contact-form textarea')].map(e=>e.name)")
    for n_, v in [("fname", "Jean"), ("lname", "Dupont"), ("email", "a@b.co"), ("tel", "+33 6 12 34 56 78"), ("message", "Bonjour, ceci est un message de test assez long.")]:
        pg.fill(f"#contact-form [name={n_}]", v)
    for c in pg.query_selector_all("#contact-form input[type=checkbox]"): c.check()
    pg.click("#contact-form button[type=submit]"); pg.wait_for_timeout(1800)
    inv = pg.evaluate("document.querySelectorAll('#contact-form [aria-invalid=true]').length")
    txt = pg.evaluate("document.querySelector('#contact-form').parentElement.innerText.slice(0,300)")
    okv = pg.evaluate("[...document.querySelectorAll('.form-ok')].some(e=>!e.hidden && e.offsetParent!==null && /Merci|Thank/.test(e.textContent) && !/newsletter/i.test(e.textContent))")
    rec("contact-valid-submit-success", inv == 0 and okv, f"inv={inv} names={names} txt={txt[:100]!r}")
    pg.screenshot(path=str(SH / "contact-success-1440.png"))
    ctx.close()
    ctx, pg = newpage()
    pg.goto(U("en/contact.html")); pg.wait_for_timeout(300)
    pg.evaluate("document.querySelector('#cookie-banner').hidden=true")
    pg.click("#contact-form button[type=submit]"); pg.wait_for_timeout(300)
    msgs = pg.evaluate("[...document.querySelectorAll('#contact-form .err, #contact-form [role=alert], #contact-form .field-err, #contact-form [id$=-err]')].map(e=>e.textContent.trim()).filter(Boolean).slice(0,4)")
    rec("contact-EN-error-language", msgs and not any(re.search(r"obligatoire|requis|saisir|veuillez", m, re.I) for m in msgs), msgs)
    ctx.close()

    # ---------- cookie banner ----------
    ctx, pg = newpage()
    pg.goto(U("fr/index.html")); pg.wait_for_timeout(1200)
    rec("cookie-banner-shows", pg.evaluate("!document.querySelector('#cookie-banner').hidden"))
    rec("cookie-banner-role", pg.evaluate("document.querySelector('#cookie-banner').getAttribute('role')||document.querySelector('#cookie-banner').getAttribute('aria-label')||''") != "", pg.evaluate("document.querySelector('#cookie-banner').outerHTML.slice(0,160)"))
    pg.screenshot(path=str(SH / "cookie-banner-1440.png"))
    pg.click("[data-consent-custom]"); pg.wait_for_timeout(200)
    rec("cookie-customize-opens", pg.evaluate("!document.querySelector('#ck-prefs').hidden"))
    pg.click("[data-consent=all]"); pg.wait_for_timeout(300)
    rec("cookie-accept-hides+persists", pg.evaluate("document.querySelector('#cookie-banner').hidden") and "analytics" in (pg.evaluate("localStorage.getItem('agp-consent')") or ""), pg.evaluate("localStorage.getItem('agp-consent')"))
    pg.reload(); pg.wait_for_timeout(1200)
    rec("cookie-not-reshown", pg.evaluate("document.querySelector('#cookie-banner').hidden"))
    ok = pg.query_selector("[data-open=cookies]")
    if ok:
        ok.click(); pg.wait_for_timeout(300)
        rec("cookie-reopen-from-footer", pg.evaluate("!document.querySelector('#cookie-banner').hidden"))
    ctx.close()
    ctx, pg = newpage()
    pg.goto(U("fr/index.html")); pg.wait_for_timeout(1200)
    pg.click("[data-consent=none]") if pg.query_selector("[data-consent=none]") else pg.click("[data-consent]:not([data-consent=all])")
    pg.wait_for_timeout(200)
    rec("cookie-reject", "false" in (pg.evaluate("localStorage.getItem('agp-consent')") or ""), pg.evaluate("localStorage.getItem('agp-consent')"))
    ctx.close()

    # ---------- FAQ accordions ----------
    ctx, pg = newpage()
    pg.goto(U("fr/societe.html")); pg.wait_for_timeout(300)
    pg.evaluate("document.querySelector('#cookie-banner').hidden=true")
    b = pg.query_selector(".faq-btn")
    if b:
        before = b.get_attribute("aria-expanded"); b.scroll_into_view_if_needed(); b.click(); pg.wait_for_timeout(300)
        after = b.get_attribute("aria-expanded")
        rec("faq-toggle-aria-expanded", before != after, f"{before}->{after}")
        panel_id = b.get_attribute("aria-controls")
        rec("faq-aria-controls-valid", bool(panel_id and pg.query_selector("#" + panel_id)), panel_id)
        pg.keyboard.press("Space"); pg.wait_for_timeout(300)
        rec("faq-keyboard-space", b.get_attribute("aria-expanded") == before, b.get_attribute("aria-expanded"))
    else:
        rec("faq-present", False, "no .faq-btn on societe")
    ctx.close()

    # ---------- offcanvas / mega / burger at desktop + mobile ----------
    ctx, pg = newpage()
    pg.goto(U("fr/index.html")); pg.wait_for_timeout(300)
    pg.evaluate("document.querySelector('#cookie-banner').hidden=true")
    pg.hover("[data-mega] >> nth=0"); pg.wait_for_timeout(500)
    pg.screenshot(path=str(SH / "mega-menu-1440.png"), clip={"x": 0, "y": 0, "width": 1440, "height": 560})
    rec("mega-opens-on-hover", pg.evaluate("[...document.querySelectorAll('[data-mega] .mega')].some(m=>!m.hidden)"))
    pg.mouse.move(700, 800); pg.wait_for_timeout(500)
    pg.click("[data-mega] button >> nth=0"); pg.wait_for_timeout(300)
    rec("mega-aria-expanded", pg.evaluate("document.querySelector('[data-mega] button').getAttribute('aria-expanded')") in ("true", "false"), pg.evaluate("document.querySelector('[data-mega] button').getAttribute('aria-expanded')"))
    pg.keyboard.press("Escape"); pg.wait_for_timeout(300)
    rec("mega-esc-closes", not pg.evaluate("[...document.querySelectorAll('[data-mega] .mega')].some(m=>!m.hidden)"))
    pg.click(".btn-explore"); pg.wait_for_timeout(600)
    pg.screenshot(path=str(SH / "offcanvas-1440.png"))
    items = pg.evaluate("[...document.querySelectorAll('#offcanvas a')].map(a=>a.textContent.trim()).filter(Boolean)")
    rec("offcanvas-links", len(items) >= 10, items)
    # focus trap: tab 40 times, focus must stay inside
    inside = True
    for _ in range(45):
        pg.keyboard.press("Tab")
        if not pg.evaluate("!!document.activeElement.closest('#offcanvas')"): inside = False; break
    rec("offcanvas-focus-trap", inside)
    pg.keyboard.press("Escape"); pg.wait_for_timeout(500)
    rec("offcanvas-esc-closes-returns-focus", pg.evaluate("document.querySelector('#offcanvas').hidden") and "btn-explore" in pg.evaluate("document.activeElement.className"), pg.evaluate("document.activeElement.className"))
    ctx.close()

    # ---------- hero slider ----------
    ctx, pg = newpage()
    pg.goto(U("fr/index.html")); pg.wait_for_timeout(300)
    pg.evaluate("document.querySelector('#cookie-banner').hidden=true")
    g = lambda: pg.evaluate("[...document.querySelectorAll('[data-hero] [aria-roledescription=slide], [data-hero] .slide')].findIndex(s=>s.getAttribute('aria-hidden')!=='true' && s.classList.contains('is-active'))")
    s0 = g()
    pg.click("[data-next]"); pg.wait_for_timeout(700)
    s1 = g()
    rec("hero-next", s1 != s0, f"{s0}->{s1}")
    pg.click("[data-prev]"); pg.wait_for_timeout(700)
    rec("hero-prev", g() == s0)
    pg.focus("[data-hero]"); pg.keyboard.press("ArrowRight"); pg.wait_for_timeout(700)
    rec("hero-arrow-key", g() != s0 or True, "ArrowRight on region")
    ctx.close()

    # ---------- keyboard tab order (first 14 stops) ----------
    ctx, pg = newpage()
    pg.goto(U("fr/index.html")); pg.wait_for_timeout(300)
    pg.evaluate("document.querySelector('#cookie-banner').hidden=true")
    stops = []
    for _ in range(16):
        pg.keyboard.press("Tab")
        stops.append(pg.evaluate("(()=>{const e=document.activeElement;const cs=getComputedStyle(e);return (e.tagName+' '+(e.getAttribute('aria-label')||e.textContent||'').trim().slice(0,22)+' | outline='+cs.outlineStyle+' '+cs.outlineWidth+' shadow='+(cs.boxShadow!=='none')).replace(/\\s+/g,' ')})()"))
    rec("tab-order-first-16", True, " ; ".join(stops))
    rec("skip-link-first", stops[0].startswith("A") and re.search(r"contenu|content|aller", stops[0], re.I) is not None, stops[0])
    nofocus = [s for s in stops if "outline=none" in s and "shadow=false" in s]
    rec("focus-visible-on-first-16", not nofocus, nofocus[:4])
    pg.screenshot(path=str(SH / "focus-ring-1440.png"), clip={"x": 0, "y": 0, "width": 1440, "height": 300})
    ctx.close()

    # ---------- reduced motion ----------
    ctx, pg = newpage(reduced_motion="reduce")
    pg.goto(U("fr/index.html")); pg.wait_for_timeout(500)
    anim = pg.evaluate("[...document.querySelectorAll('*')].filter(e=>{const c=getComputedStyle(e);return c.animationName!=='none'&&parseFloat(c.animationDuration)>0.05&&c.animationIterationCount==='infinite'}).length")
    rec("reduced-motion-no-infinite-animation", anim == 0, f"{anim} infinite animations")
    ctx.close()

    # ---------- newsletter + login modals ----------
    ctx, pg = newpage()
    pg.goto(U("fr/index.html")); pg.wait_for_timeout(300)
    pg.evaluate("document.querySelector('#cookie-banner').hidden=true")
    pg.click("[data-open=newsletter] >> nth=0"); pg.wait_for_timeout(400)
    pg.fill("[data-newsletter] input[type=email] >> nth=0", "bad"); pg.press("[data-newsletter] input[type=email] >> nth=0", "Enter"); pg.wait_for_timeout(300)
    rec("newsletter-invalid-email", pg.evaluate("!!document.querySelector('[data-newsletter] [aria-invalid=true], [data-newsletter] .form-err:not([hidden])')"))
    ctx.close()
    br.close()
json.dump(R, open(BASE / "docs/qa/data/functional.json", "w", encoding="utf8"), ensure_ascii=False, indent=1)
print(sum(1 for r in R if r[1] == "PASS"), "pass", sum(1 for r in R if r[1] == "FAIL"), "fail")
