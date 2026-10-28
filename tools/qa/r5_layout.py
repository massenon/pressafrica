"""Round 5 layout regression: full-page shots + top-level section geometry vs R4 baseline (docs/qa/data/layout.json)."""
import json
from pathlib import Path
from playwright.sync_api import sync_playwright
B=Path(__file__).resolve().parents[2]; SH=B/"docs/qa/shots"
PG={"fr-index":"fr/index.html","en-index":"en/index.html","cat-societe":"fr/societe.html","article":"fr/articles/cap-vert-sous-le-diktat-des-catastrophes-naturelles.html","contact":"fr/contact.html"}
JS="""()=>{const o=[];const sel='.site-header,.topbar,.brandband,.mainnav-wrap,.ticker,main > *,.site-footer';
document.querySelectorAll(sel).forEach(e=>{const r=e.getBoundingClientRect();o.push([e.tagName.toLowerCase()+'.'+String(e.className).split(' ')[0],Math.round(r.top+scrollY),Math.round(r.height)])});
return {h:document.documentElement.scrollHeight,sw:document.documentElement.scrollWidth,vw:innerWidth,g:o}}"""
out={}
with sync_playwright() as p:
    b=p.chromium.launch(channel="msedge")
    for w in (1440,390):
        pg=b.new_page(viewport={"width":w,"height":900})
        for k,u in PG.items():
            pg.goto(f"http://127.0.0.1:8767/{u}"); pg.wait_for_timeout(1200)
            for y in range(0,12000,400): pg.evaluate(f"scrollTo(0,{y})"); pg.wait_for_timeout(120)
            pg.wait_for_timeout(700); pg.evaluate("scrollTo(0,0)"); pg.wait_for_timeout(400)
            pg.screenshot(path=str(SH/f"r5-{k}-{w}.png"),full_page=True)
            r=pg.evaluate(JS); out[f"{k}@{w}"]=r; print(k,w,r["h"],"sw",r["sw"],r["vw"])
        pg.close()
    b.close()
json.dump(out,open(B/"docs/qa/data/r5_layout.json","w"))
