"""Round 5 design re-audit probes: type scaling, placeholder contrast, touch targets, admin search, empty state, KPI."""
from playwright.sync_api import sync_playwright
U="http://127.0.0.1:8767/"
SM="""()=>{const o={u44:0,u24:0,n:0};const s='a[href],button,input:not([type=hidden]),select,textarea,[role=button]';for(const e of document.querySelectorAll(s)){const cs=getComputedStyle(e);if(cs.display==='none'||cs.visibility==='hidden'||e.closest('[hidden],.sr-only')||e.matches('.skip-link'))continue;const r=e.getBoundingClientRect();if(!r.width||!r.height)continue;o.n++;if(r.width<44||r.height<44)o.u44++;if(r.width<24||r.height<24)o.u24++}return o}"""
def px(pg,sel,prop="fontSize"): return pg.evaluate(f"(()=>{{const e=document.querySelector('{sel}');return e?getComputedStyle(e).{prop}:null}})()")
with sync_playwright() as p:
    b=p.chromium.launch(channel="msedge")
    pg=b.new_page(viewport={"width":1440,"height":900}); pg.goto(U+"fr/index.html"); pg.evaluate("document.documentElement.style.setProperty('font-size','200%','important')"); pg.wait_for_timeout(500)
    print("P-01 root200:",{s:px(pg,s) for s in ("body",".card-title",".hero-title",".meta",".nav-link",".excerpt",".topbar a",".chip")})
    pg.goto(U+"fr/contact.html"); print("P-02 placeholder", pg.evaluate("getComputedStyle(document.querySelector('.field input'),'::placeholder').color"), pg.evaluate("getComputedStyle(document.querySelector('.field input')).backgroundColor"))
    pg.goto(U+"fr/index.html"); print("P-10", pg.evaluate("[...new Set([...document.querySelectorAll('.meta')].map(e=>e.textContent.trim()))].slice(0,3)"))
    pg.set_viewport_size({"width":390,"height":800}); 
    for u in ("fr/index.html","fr/societe.html","fr/contact.html","fr/articles/cap-vert-sous-le-diktat-des-catastrophes-naturelles.html"):
        pg.goto(U+u); pg.wait_for_timeout(600); print("P-03 targets 390",u[-30:],pg.evaluate(SM))
    for sel in (".chip",".card-mini .chip",".breadcrumb",".brandband-sub",".meta"): print("P-04",sel,px(pg,sel))
    pg.goto(U+"fr/index.html"); print("P-05 reduced-transparency modal bg check skipped")
    ctx=b.new_context(viewport={"width":390,"height":800}); a=ctx.new_page(); a.goto(U+"admin/login.html"); print("A-01 login font",px(a,"label","fontFamily")[:60],px(a,"h1","fontFamily")[:60])
    a.click("#demo button >> nth=0"); a.wait_for_timeout(700)
    a.goto(U+"admin/posts.html"); a.wait_for_timeout(700)
    print("A-02 search 390",a.evaluate("(()=>{const e=document.querySelector('.a-top input,.a-search input');if(!e)return 'none';const r=e.getBoundingClientRect();return [r.width,r.height,getComputedStyle(e).display]})()"))
    print("A-03 targets 390 posts",a.evaluate(SM))
    a.goto(U+"admin/index.html"); a.wait_for_timeout(700)
    print("A-07 kpi",a.evaluate("(()=>{const k=document.querySelectorAll('.a-kpi');return [k.length,k.length?getComputedStyle(k[0],'::after').content:null,[...k].slice(0,2).map(x=>x.innerText.split(String.fromCharCode(10)).join(' / ').slice(0,80))]})()"))
    a.goto(U+"admin/posts.html"); a.fill("input[type=search],.a-top input","zzzzqq"); a.wait_for_timeout(600)
    print("A-09 empty",a.evaluate("document.querySelector('.a-empty')?document.querySelector('.a-empty').innerText.slice(0,120):document.querySelector('main').innerText.slice(0,200)"))
    a.screenshot(path="docs/qa/shots/r5-admin-posts-empty-390.png")
    b.close()
