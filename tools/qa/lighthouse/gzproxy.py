!/usr/bin/env python3
# Local stand-in for Apache mod_deflate + cache headers in front of `artisan serve` (measurement only).
import gzip, urllib.request, sys
from http.server import ThreadingHTTPServer, BaseHTTPRequestHandler
UP = 'http://127.0.0.1:8123'
class H(BaseHTTPRequestHandler):
    def log_message(self, *a): pass
    def do_GET(self):
        req = urllib.request.Request(UP + self.path, headers={k: v for k, v in self.headers.items() if k.lower() not in ('host', 'accept-encoding')})
        try:
            r = urllib.request.urlopen(req)
        except urllib.error.HTTPError as e:
            r = e
        body = r.read(); ct = r.headers.get('Content-Type', '')
        self.send_response(r.status if hasattr(r, 'status') else r.code)
        for k, v in r.headers.items():
            if k.lower() not in ('content-length', 'transfer-encoding', 'connection', 'content-encoding'): self.send_header(k, v)
        if any(t in ct for t in ('text/', 'javascript', 'json', 'xml', 'svg')) and 'gzip' in self.headers.get('Accept-Encoding', ''):
            body = gzip.compress(body, 6); self.send_header('Content-Encoding', 'gzip')
        if self.path.startswith('/assets/'): self.send_header('Cache-Control', 'public, max-age=2592000')
        self.send_header('Content-Length', str(len(body))); self.end_headers(); self.wfile.write(body)
ThreadingHTTPServer(('127.0.0.1', 8125), H).serve_forever()
