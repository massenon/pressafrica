#!/usr/bin/env python3
"""Admin functional flows: posts quick-add / delete+undo / toasts, categories CRUD, quiz builder, AI panel, editor save,
roles/permissions switch, settings. Output docs/qa/data/admin_flows.json. Run: python tools/qa/admin_flows.py"""
import json, re
from pathlib import Path
from playwright.sync_api import sync_playwright

BASE = Path(__file__).resolve().parents[2]
ROOT = BASE / "prototype_html"
SH = BASE / "docs/qa/shots"
A = lambda p: (ROOT / "admin" / p).as_uri()
R = []


def rec(i, ok, d=""):
    R.append([i, "PASS" if ok else "FAIL", str(d)[:300]])
    print("PASS " if ok else "FAIL ", i, str(d)[:220], flush=True)


def toast(pg):
    return pg.evaluate("[...document.querySelectorAll('.a-toast, [role=status].a-toast, .a-toasts > *')].map(e=>e.textContent.trim()).slice(0,3)")


with sync_playwright() as pw:
    br = pw.chromium.launch(channel="msedge")
    ctx = br.new_context(viewport={"width": 1440, "height": 900})
    pg = ctx.new_page()
    errs = []
    pg.on("pageerror", lambda e: errs.append(str(e)[:200]))
    pg.on("console", lambda m: errs.append("console: " + m.text[:160]) if m.type == "error" else None)
    pg.goto(A("login.html")); pg.wait_for_timeout(300)
    pg.click("#demo button >> nth=0"); pg.wait_for_timeout(800)

    # --- posts quick add
    pg.goto(A("posts.html")); pg.wait_for_timeout(600)
    n0 = pg.evaluate("document.querySelectorAll('tbody tr').length")
    pg.click("text=Quick add"); pg.wait_for_timeout(400)
    modal = pg.evaluate("!!document.querySelector('[role=dialog]')")
    rec("posts-quick-add-opens-dialog", modal)
    pg.screenshot(path=str(SH / "admin-posts-quickadd-1440.png"))
    # empty submit
    pg.click("[role=dialog] button.a-btn-primary, [role=dialog] button:has-text('Save'), [role=dialog] button:has-text('Create')"); pg.wait_for_timeout(300)
    still = pg.evaluate("!!document.querySelector('[role=dialog]')")
    rec("posts-quick-add-empty-blocked", still, f"dialog stays open; toasts={toast(pg)}")
    inputs = pg.evaluate("[...document.querySelectorAll('[role=dialog] input,[role=dialog] select,[role=dialog] textarea')].map(e=>e.name||e.id||e.type)")
    pg.fill("[role=dialog] input[type=text] >> nth=0", "QA test article " + "x" * 5)
    pg.click("[role=dialog] button.a-btn-primary, [role=dialog] button:has-text('Save'), [role=dialog] button:has-text('Create')"); pg.wait_for_timeout(500)
    t = toast(pg)
    rec("posts-quick-add-saves-and-toasts", any("created" in x.lower() for x in t), f"toasts={t} inputs={inputs}")
    pg.reload(); pg.wait_for_timeout(600)
    found = pg.evaluate("document.body.innerText.includes('QA test article')")
    rec("posts-persist-after-reload", found)
    # delete row + undo
    pg.fill("#view input[type=search]", "QA test article") if pg.query_selector("#view input[type=search]") else None
    pg.wait_for_timeout(400)
    row_del = pg.query_selector("button[aria-label^='Delete']") or pg.query_selector("button:has-text('Delete')")
    if row_del:
        row_del.click(); pg.wait_for_timeout(300)
        rec("posts-delete-asks-confirmation", pg.evaluate("!!document.querySelector('[role=dialog],[role=alertdialog]')"))
        pg.click("[role=dialog] button.a-btn-danger, [role=alertdialog] button.a-btn-danger"); pg.wait_for_timeout(400)
        t = toast(pg)
        rec("posts-delete-toast-with-undo", any("deleted" in x.lower() for x in t) and any("undo" in x.lower() for x in t), t)
        pg.screenshot(path=str(SH / "admin-toast-undo-1440.png"))
        ub = pg.query_selector(".a-toast button:has-text('Undo')")
        if ub:
            ub.click(); pg.wait_for_timeout(400)
            rec("posts-undo-restores", pg.evaluate("document.body.innerText.includes('QA test article')"))
        # cleanup: delete again
        pg.click("button[aria-label^='Delete']"); pg.wait_for_timeout(300)
        pg.click("[role=dialog] button.a-btn-danger, [role=alertdialog] button.a-btn-danger"); pg.wait_for_timeout(300)
    else:
        rec("posts-row-delete-control", False, "not found; row actions: " + str(pg.evaluate("[...document.querySelectorAll('tbody tr:first-child button')].map(b=>b.getAttribute('aria-label')||b.textContent)")))
    # sorting / search
    pg.goto(A("posts.html")); pg.wait_for_timeout(500)
    first = pg.inner_text("tbody tr >> nth=0")[:40]
    pg.click("th button:has-text('Post')"); pg.wait_for_timeout(300)
    rec("posts-sort-changes-order", pg.inner_text("tbody tr >> nth=0")[:40] != first)
    srch = pg.query_selector("#view input[type=search], #view input[placeholder*=earch]")
    if srch:
        srch.fill("zzzzzzzz"); pg.wait_for_timeout(500)
        rec("posts-search-empty-state", "No posts match" in pg.inner_text("#view"), pg.inner_text("#view")[-120:].replace("\n", " "))
    else:
        rec("posts-search-present", False, "no search input")

    # --- post editor
    pg.goto(A("post-edit.html")); pg.wait_for_timeout(800)
    pg.screenshot(path=str(SH / "admin-editor-1440.png"), full_page=True)
    rec("editor-has-h1", pg.evaluate("document.querySelectorAll('h1').length") == 1)
    for fmt in ("quiz", "poll", "recipe", "video", "audio", "gallery", "event"):
        try:
            pg.check(f"input[name=fmt][value={fmt}]", force=True); pg.wait_for_timeout(250)
            ok = pg.evaluate("document.querySelector('h2.text-xl') && true")
            rec(f"editor-format-{fmt}-switches", ok, pg.evaluate("[...document.querySelectorAll('h2')].map(h=>h.textContent).filter(t=>/settings/.test(t))"))
        except Exception as e:
            rec(f"editor-format-{fmt}-switches", False, str(e)[:120])
    pg.check("input[name=fmt][value=quiz]", force=True); pg.wait_for_timeout(300)
    pg.screenshot(path=str(SH / "admin-quiz-builder-1440.png"), full_page=True)
    nq0 = pg.evaluate("document.querySelectorAll('.a-opt').length")
    addq = pg.query_selector("button:has-text('Add question')")
    if addq:
        addq.click(); pg.wait_for_timeout(300)
    adda = pg.query_selector(".add-ans")
    if adda:
        adda.click(); pg.wait_for_timeout(300)
    nq1 = pg.evaluate("document.querySelectorAll('.a-opt').length")
    rec("quiz-add-question-and-answer-updates-preview", nq1 > nq0, f"{nq0}->{nq1}")
    # fill question + answers and click through preview
    qin = pg.query_selector_all("input[placeholder*=uestion], textarea[placeholder*=uestion]")
    if qin:
        qin[0].fill("Quelle est la capitale du Senegal ?")
        pg.wait_for_timeout(300)
        rec("quiz-live-preview-reflects-input", pg.evaluate("document.body.innerText.includes('Quelle est la capitale')"))
    opt = pg.query_selector(".a-opt")
    if opt:
        opt.click(); pg.wait_for_timeout(200)
        rec("quiz-preview-answer-feedback", pg.evaluate("!!document.querySelector('.a-opt.right, .a-opt.wrong')"))
    # AI panel
    ai = pg.query_selector("#aiP")
    if ai:
        ai.fill("Solar micro-grids in rural Senegal")
        pg.click("button:has-text('Generate')"); pg.wait_for_timeout(600)
        mid = pg.evaluate("document.querySelector('.a-stream').textContent.length")
        busy = pg.evaluate("document.querySelector('.a-stream').getAttribute('aria-busy')")
        rec("ai-generate-streams", mid > 0 and busy == "true", f"len={mid} busy={busy}")
        pg.click("button:has-text('Stop')"); pg.wait_for_timeout(200)
        rec("ai-stop-works", pg.evaluate("document.querySelector('.a-stream').getAttribute('aria-busy')") == "false")
        pg.click("button:has-text('Generate')"); pg.wait_for_timeout(4500)
        rec("ai-finishes", pg.evaluate("document.querySelector('.a-stream').getAttribute('aria-busy')") == "false")
        ins = pg.query_selector("button:has-text('Insert')")
        if ins and not ins.is_disabled():
            ins.click(); pg.wait_for_timeout(300)
            rec("ai-insert-into-editor", "Inserted" in " ".join(toast(pg)), toast(pg))
        pg.screenshot(path=str(SH / "admin-ai-panel-1440.png"))
    else:
        rec("ai-panel-present", False, "no #aiP")
    # save with missing title
    sb = pg.query_selector("button:has-text('Save draft'), button:has-text('Save')")
    if sb:
        sb.click(); pg.wait_for_timeout(400)
        t = toast(pg)
        rec("editor-save-without-title-warns", bool(t) or pg.evaluate("!!document.querySelector('[aria-invalid=true]')"), t)
    # unsaved-changes guard
    # --- categories CRUD
    pg.goto(A("categories.html")); pg.wait_for_timeout(600)
    n0 = pg.evaluate("document.querySelectorAll('#view li, #view tbody tr, #view .a-cat').length")
    ab = pg.query_selector("button:has-text('Add category'), button:has-text('New category'), a:has-text('New category')")
    if ab:
        ab.click(); pg.wait_for_timeout(400)
        pg.click("[role=dialog] button.a-btn-primary"); pg.wait_for_timeout(300)
        rec("category-empty-name-rejected", any("name" in x.lower() for x in toast(pg)) or pg.evaluate("!!document.querySelector('[role=dialog]')"), toast(pg))
        ins_ = pg.query_selector_all("[role=dialog] input[type=text]")
        ins_[0].fill("QA Category"); pg.click("[role=dialog] button.a-btn-primary"); pg.wait_for_timeout(500)
        rec("category-create-toast", any("created" in x.lower() for x in toast(pg)), toast(pg))
        rec("category-appears", pg.evaluate("document.body.innerText.includes('QA Category')"))
        pg.screenshot(path=str(SH / "admin-categories-1440.png"))
    else:
        rec("category-add-control", False, pg.evaluate("[...document.querySelectorAll('#view button')].map(b=>b.textContent.trim()).slice(0,12)"))
    # --- settings, menus smoke
    for pgname in ("settings", "menus", "featured", "media", "newsletter", "comments", "authors", "ads", "index"):
        errs.clear()
        pg.goto(A(pgname + ".html")); pg.wait_for_timeout(700)
        rec(f"admin-{pgname}-renders-no-errors", pg.evaluate("document.querySelector('#view').children.length") > 0 and not errs, errs[:2])
    # --- role switch: author should lose modules
    pg.goto(A("index.html")); pg.wait_for_timeout(500)
    sel = pg.query_selector("select[aria-label*=iew], #roleSwitch, header select")
    if sel:
        opts = pg.evaluate("[...document.querySelectorAll('header select option')].map(o=>o.value+':'+o.textContent)")
        rec("role-switcher-present", True, opts[:6])
    else:
        rec("role-switcher-present", False)
    # a11y: keyboard through nav
    pg.goto(A("posts.html")); pg.wait_for_timeout(500)
    st = []
    for _ in range(10):
        pg.keyboard.press("Tab")
        st.append(pg.evaluate("(()=>{const e=document.activeElement;const c=getComputedStyle(e);return e.tagName+' '+(e.getAttribute('aria-label')||e.textContent||'').trim().slice(0,18)+' outline='+c.outlineStyle+c.outlineWidth})()"))
    rec("admin-tab-order-first-10", True, " ; ".join(st))
    rec("admin-focus-visible", not any("outline=none" in s for s in st), [s for s in st if "outline=none" in s][:3])
    ctx.close()
    # --- mobile nav
    ctx = br.new_context(viewport={"width": 390, "height": 800})
    pg = ctx.new_page()
    pg.goto(A("login.html")); pg.click("#demo button >> nth=0"); pg.wait_for_timeout(700)
    pg.goto(A("posts.html")); pg.wait_for_timeout(600)
    burger = pg.query_selector("button[aria-label*=enu], button[aria-controls*=side], #navToggle")
    rec("admin-mobile-burger-present", bool(burger), pg.evaluate("[...document.querySelectorAll('header button, .a-top button')].map(b=>b.getAttribute('aria-label')||b.textContent.trim()).slice(0,6)"))
    if burger:
        burger.click(); pg.wait_for_timeout(400)
        pg.screenshot(path=str(SH / "admin-mobile-menu-390.png"))
        rec("admin-mobile-menu-expanded-aria", burger.get_attribute("aria-expanded") == "true", burger.get_attribute("aria-expanded"))
    ctx.close()
    br.close()
json.dump(R, open(BASE / "docs/qa/data/admin_flows.json", "w", encoding="utf8"), ensure_ascii=False, indent=1)
print(sum(1 for r in R if r[1] == "PASS"), "pass", sum(1 for r in R if r[1] == "FAIL"), "fail")
