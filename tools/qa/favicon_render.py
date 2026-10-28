import sys
from pathlib import Path
from playwright.sync_api import sync_playwright
R=Path(__file__).resolve().parents[2]
html='''<body style="margin:0;font:12px sans-serif"><div style="display:flex">'''
for bg,fg in (("#fff","#000"),("#202124","#fff")):
    html+=f'<div style="background:{bg};color:{fg};padding:16px;width:420px">'
    for n,s in (("favicon-16.png",16),("favicon-32.png",32),("favicon-16.png",64),("favicon-32.png",64),("favicon.svg",16),("favicon.svg",32),("apple-touch-icon.png",90),("icon-maskable-512.png",90)):
        html+=f'<div style="display:inline-block;margin:6px;text-align:center"><img src="http://127.0.0.1:8767/assets/icons/{n}" width="{s}" height="{s}"><br>{n[:12]}@{s}</div>'
    html+='</div>'
html+='</div></body>'
with sync_playwright() as p:
    b=p.chromium.launch(channel="msedge"); pg=b.new_page(viewport={"width":880,"height":260}); pg.set_content(html); pg.wait_for_timeout(500)
    pg.screenshot(path=str(R/"docs/qa/shots/r5-favicons.png")); b.close()
