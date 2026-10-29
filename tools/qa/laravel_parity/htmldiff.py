# Normalised diff of <main> between a prototype page and the Laravel response (see sweep.sh).
# usage: python3 htmldiff.py <path>   diff of normalised <main> between prototype file and Laravel response
import sys, re, posixpath, urllib.request, difflib
path = sys.argv[1]
proto = open('/home/user/pressafrica/prototype_html/' + path, encoding='utf8').read()
lara = urllib.request.urlopen('http://127.0.0.1:8123/' + path).read().decode('utf8')
def norm(s, base):
    s = s[s.index('<main'):s.index('</main>')]
    def ab(m):
        v = m.group(2)
        if v and not v.startswith(('http', '#', '/', 'mailto:', 'data:')):
            v = posixpath.normpath(posixpath.join(posixpath.dirname('/' + base), v))
        v = v.replace('http://127.0.0.1:8123', '').replace('/storage/media/import/', '/assets/img/posts/')
        return f'{m.group(1)}="{v}"'
    s = re.sub(r'\b(href|src)="([^"]*)"', ab, s)
    s = re.sub(r'\s(srcset|sizes|style|width|height)="[^"]*"', '', s)
    s = re.sub(r'-(420|860|1280)\.webp', '.webp', s)
    s = s.replace('&#x27;', "'").replace('&#039;', "'").replace('&quot;', '"')
    s = s.replace('https%3A%2F%2Fwww.africaglobalpress.example', 'HOST').replace('http%3A%2F%2F127.0.0.1%3A8123', 'HOST')
    s = s.replace('<br/>', '<br>').replace('<br />', '<br>').replace('\u00a0', ' ').replace('&nbsp;', ' ')
    s = re.sub(r'\s+', ' ', s).replace('> <', '><')
    return re.sub(r'>', '>\n', s).splitlines()
a, b = norm(proto, path), norm(lara, path)
n = 0
for line in difflib.unified_diff(a, b, lineterm='', n=0):
    if line.startswith(('---', '+++', '@@')): continue
    print(line[:220]); n += 1
    if n > int(sys.argv[2] if len(sys.argv) > 2 else 30): break
print('total diff lines', sum(1 for l in difflib.unified_diff(a, b, lineterm='', n=0)))
