"""Round 5: 200% text zoom (root font-size 200%) at 1440 and 720 css px (= browser zoom 200% on 1440) and emulated media screenshots."""
import json
from pathlib import Path
from playwright.sync_api import sync_playwright
B=Path(__file__).resolve().parents[2]; SH=B/"docs/qa/shots"; U="http://127.0.0.1:8767/"
PUB={"home":"fr/index.html","article":"fr/articles/cap-vert-sous-le-diktat-des-catastrophes-naturelles.html","contact":"fr/contact.html"}
ADM={"login":"admin/login.html","posts":"admin/posts.html"}
CLIP="""()=>{const o=[];const vw=document.documentElement.clientWidth;
for(const e of document.querySelectorAll('body *')){const cs=getComputedStyle(e);if(cs.display==='none'||cs.visibility==='hidden')continue;
 const r=e.getBoundingClientRect();if(!r.width||!r.height)continue;
 const txt=[...e.childNodes].some(n=>n.nodeType===3&&n.textContent.trim());
 if(txt&&(cs.overflow==='hidden'||cs.overflowX==='hidden'||cs.overflowY==='hidden')&&cs.textOverflow!=='ellipsis'&&cs.webkitLineClamp==='none'&&(e.scrollHeight>e.clientHeight+2||e.scrollWidth>e.clientWidth+2)&&!e.closest('.sr-only,[hidden]'))o.push(['clip',e.tagName+'.'+String(e.className).slice(0,30),e.scrollWidth+'x'+e.scrollHeight,e.clientWidth+'x'+e.clientHeight,(e.textContent||'').trim().slice(0,30)]);}
return {sw:document.documentElement.scrollWidth,vw,clip:o.slice(0,8),fs:getComputedStyle(document.documentElement).fontSize}}"""
res={}
def login(pg):
    pg.goto(U+"admin/login.html"); pg.click("#demo button >> nth=0"); pg.wait_for_timeout(700)
with sync_playwright() as p:
    b=p.chromium.launch(channel="msedge")
    for w in (1440,720):
        ctx=b.new_context(viewport={"width":w,"height":900}); pg=ctx.new_page()
        pg.add_init_script("try{localStorage.setItem('cookie-consent','x')}catch(e){}")
        login(pg)
        for k,u in {**PUB,**ADM}.items():
            pg.goto(U+u); pg.evaluate("document.documentElement.style.setProperty('font-size','200%','important')"); pg.wait_for_timeout(900)
            pg.evaluate("document.querySelector('#cookie-banner')&&(document.querySelector('#cookie-banner').hidden=true)")
            r=pg.evaluate(CLIP); res[f"{k}@{w}"]=r; print(k,w,r,flush=True)
            pg.screenshot(path=str(SH/f"r5-zoom200-{k}-{w}.png"),full_page=False)
        ctx.close()
    # emulated media
    for name,kw in (("forced-colors",dict(forced_colors="active")),("contrast-more",dict(contrast="more")),("reduced-motion",dict(reduced_motion="reduce")),("reduced-transparency",dict(reduced_motion="reduce"))):
        if name=="reduced-transparency": continue
        ctx=b.new_context(viewport={"width":1440,"height":900},**kw); pg=ctx.new_page(); login(pg)
        for k,u in (("home","fr/index.html"),("admin-posts","admin/posts.html")):
            pg.goto(U+u); pg.wait_for_timeout(1000)
            pg.evaluate("document.querySelector('#cookie-banner')&&(document.querySelector('#cookie-banner').hidden=true)")
            pg.screenshot(path=str(SH/f"r5-{name}-{k}.png")); 
            print(name,k,pg.evaluate("[document.documentElement.scrollWidth,matchMedia('(forced-colors: active)').matches,matchMedia('(prefers-contrast: more)').matches]"),flush=True)
        ctx.close()
    b.close()
json.dump(res,open(B/"docs/qa/data/r5_zoom.json","w"))
