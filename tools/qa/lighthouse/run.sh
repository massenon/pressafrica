#!/bin/bash
# Lighthouse (mobile) on the Laravel site. Needs `php artisan serve --port=8123` and, to approximate the production
# Apache (gzip + cache headers), `python3 gzproxy.py` (port 8125). Lighthouse: `npm i lighthouse@12.2.1` anywhere.
# Usage: LH=<path to node_modules/.bin/lighthouse> tools/qa/lighthouse/run.sh [port] [out-dir]
PORT=${1:-8125}; OUT=${2:-/tmp}; LH=${LH:-npx lighthouse}
for p in fr fr/articles/cap-vert-sous-le-diktat-des-catastrophes-naturelles fr/politique fr/podcasts; do
  f="$OUT/lh-$(basename "$p").json"
  CHROME_PATH=${CHROME_PATH:-/opt/pw-browsers/chromium} $LH "http://127.0.0.1:$PORT/$p" --quiet --chrome-flags="--headless=new --no-sandbox" \
    --only-categories=performance,accessibility,best-practices,seo --output=json --output-path="$f" >/dev/null 2>&1
  python3 -c "import json,sys; r=json.load(open(sys.argv[1])); c=r['categories']; a=r['audits']; print(sys.argv[2].ljust(24), ' '.join(f'{k[:4]}={round(v[\"score\"]*100)}' for k,v in c.items()), 'LCP=%.1fs' % (a['largest-contentful-paint']['numericValue']/1000), 'CLS=%.3f' % a['cumulative-layout-shift']['numericValue'], 'TBT=%dms' % a['total-blocking-time']['numericValue'])" "$f" "$(basename "$p" | cut -c1-24)"
done
