# Normalised diff of <main> between a prototype page and the Laravel response (see sweep.sh).
# usage: python3 htmldiff.py <path>   diff of normalised <main> between prototype file and Laravel response
import sys, re, posixpath, urllib.request, difflib
path = sys.argv[1]
proto = open('/home/user/pressafrica/prototype_html/' + path, encoding='utf8').read()
lara = urllib.request.urlopen('http://127.0.0.1:8123/' + path).read().decode('utf8')
# REGION=chrome compares the header + footer (menus, dialogs) instead of <main>.
import os
REGION = os.environ.get('REGION', 'main')


def norm(s, base):
    if REGION == 'chrome':
        s = s[s.index('<header class="site-header"'):s.index('</header>')] + s[s.index('<footer class="site-footer"'):s.index('<script src="', s.index('<footer class="site-footer"'))]
        s = re.sub(r'<p class="topbar-date"[^>]*>[^<]*</p>', '', s)   # today's date
    else:
        s = s[s.index('<main'):s.index('</main>')]
    def ab(m):
        v = m.group(2)
        if v and not v.startswith(('http', '#', '/', 'mailto:', 'data:')):
            v = posixpath.normpath(posixpath.join(posixpath.dirname('/' + base), v))
        v = v.replace('http://127.0.0.1:8123', '').replace('/storage/media/import/', '/assets/img/posts/')
        return f'{m.group(1)}="{v}"'
    s = re.sub(r'<!--.*?-->', '', s, flags=re.S)
    # The comment section differs on purpose (server form, replies, newsroom answers; spec EX-43 to 46) and is
    # covered by feature tests, so it is left out of the comparison on both sides.
    s = re.sub(r'<section class="comment-box-wrap.*?</section>', '', s, flags=re.S)
    s = re.sub(r'(?<![\w-])(href|src|action|poster|data-poster|data-src|data-read)="([^"]*)"', ab, s)
    s = re.sub(r'\s(srcset|sizes|style|width|height)="[^"]*"', '', s)
    s = re.sub(r'-(420|860|1280)\.webp', '.webp', s)
    s = s.replace('&#x27;', "'").replace('&#039;', "'").replace('&quot;', '"')
    s = s.replace('https%3A%2F%2Fwww.africaglobalpress.example', 'HOST').replace('http%3A%2F%2F127.0.0.1%3A8123', 'HOST')
    s = s.replace('<br/>', '<br>').replace('<br />', '<br>').replace('\u00a0', ' ').replace('&nbsp;', ' ')
    # Laravel URLs have no .html (2026-10-29): compare the prototype's links in that form, plain or URL-encoded.
    s = re.sub(r'((?:/|%2F)(?:fr|en))(?:/|%2F)index\.html', r'\1', s)
    s = re.sub(r'((?:/|%2F)(?:fr|en)(?:(?:/|%2F)[a-z0-9-]+)+)\.html', r'\1', s)
    s = re.sub(r'\s+', ' ', s).replace('> <', '><')
    return re.sub(r'>', '>\n', s).splitlines()
a, b = norm(proto, path), norm(lara, path)
n = 0
for line in difflib.unified_diff(a, b, lineterm='', n=0):
    if line.startswith(('---', '+++', '@@')): continue
    print(line[:220]); n += 1
    if n > int(sys.argv[2] if len(sys.argv) > 2 else 30): break
print('total diff lines', sum(1 for l in difflib.unified_diff(a, b, lineterm='', n=0)))
