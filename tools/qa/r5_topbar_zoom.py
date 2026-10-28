from playwright.sync_api import sync_playwright
U="http://127.0.0.1:8767/"
with sync_playwright() as p:
    b=p.chromium.launch(channel="msedge")
    for w in (1440,1280):
        pg=b.new_page(viewport={"width":w,"height":900}); pg.goto(U+"fr/index.html"); pg.evaluate("document.documentElement.style.setProperty('font-size','200%','important')"); pg.wait_for_timeout(800)
        print(w,pg.evaluate("""()=>{const vw=innerWidth;const o=[];for(const e of document.querySelectorAll('.topbar *,.mainnav *')){const r=e.getBoundingClientRect();if(r.width&&r.right>vw-0.5)o.push([e.tagName+'.'+String(e.className).slice(0,25),Math.round(r.left),Math.round(r.right)])}const t=document.querySelector('.topbar');const c=getComputedStyle(t);return [o.slice(0,6),c.overflow,t.scrollWidth,t.clientWidth,document.querySelector('.topbar-in').scrollWidth,document.querySelector('.topbar-in').clientWidth]}"""))
    b.close()
