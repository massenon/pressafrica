from playwright.sync_api import sync_playwright
U="http://127.0.0.1:8767/"
with sync_playwright() as p:
    b=p.chromium.launch(channel="msedge")
    for fs in (None,"200%"):
      for w in (720,):
        pg=b.new_page(viewport={"width":w,"height":900}); pg.goto(U+"admin/login.html"); pg.click("#demo button >> nth=0"); pg.wait_for_timeout(600)
        pg.goto(U+"admin/posts.html"); 
        if fs: pg.evaluate(f"document.documentElement.style.setProperty('font-size','{fs}','important')")
        pg.wait_for_timeout(800)
        print(fs,w,pg.evaluate("""()=>{const vw=document.documentElement.clientWidth;const o=[];for(const e of document.querySelectorAll('body *')){const cs=getComputedStyle(e);if(cs.display==='none'||cs.position==='fixed')continue;const r=e.getBoundingClientRect();if(r.right>vw+1&&r.width>0){let p=e.parentElement,c=false;while(p&&p!==document.body){if(getComputedStyle(p).overflowX!=='visible'){c=true;break}p=p.parentElement}o.push([e.tagName+'.'+String(e.className).slice(0,30),Math.round(r.left),Math.round(r.right),c])}}return [document.documentElement.scrollWidth,o.filter(x=>!x[3]).slice(0,8)]}"""))
    b.close()
