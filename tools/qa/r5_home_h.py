from playwright.sync_api import sync_playwright
J="()=>{const c=document.querySelector('main > .container-page:last-of-type, main > div.container-page');return [document.documentElement.scrollHeight,[...document.querySelectorAll('main > div.container-page > *')].map(e=>[e.tagName+'.'+String(e.className).split(' ')[0],Math.round(e.getBoundingClientRect().height),getComputedStyle(e).contentVisibility,getComputedStyle(e).containIntrinsicSize])]}"
with sync_playwright() as p:
    b=p.chromium.launch(channel="msedge")
    for w in (1440,390):
        pg=b.new_page(viewport={"width":w,"height":900}); pg.goto("http://127.0.0.1:8767/fr/index.html"); pg.wait_for_timeout(1000)
        print(w,"initial",pg.evaluate(J))
        for y in range(0,9000,400): pg.evaluate(f"scrollTo(0,{y})"); pg.wait_for_timeout(150)
        pg.wait_for_timeout(500); pg.evaluate("scrollTo(0,0)"); pg.wait_for_timeout(300)
        print(w,"after scroll",pg.evaluate(J))
    b.close()
