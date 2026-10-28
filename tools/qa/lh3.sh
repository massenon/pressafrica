#!/bin/bash
# usage: lh3.sh port outfile path...   (3 runs per page x mobile/desktop; prints one JSON line per run)
export CHROME_PATH="C:/Program Files/Google/Chrome/Application/chrome.exe"
port=$1; out=$2; shift; shift
for pg in "$@"; do for pre in mobile desktop; do for i in 1 2 3; do
 P=""; [ $pre = desktop ] && P="--preset=desktop"
 o="${TMP:-/tmp}/lh_r5.json"; rm -f $o
 timeout 150 npx --yes lighthouse http://127.0.0.1:$port/$pg --only-categories=performance,accessibility,best-practices,seo $P --chrome-flags="--headless=new" --output=json --output-path=$o --quiet >/dev/null 2>&1
 python -c "
import json;d=json.load(open(r'$o'));a=d['audits'];c=d['categories']
print(json.dumps(dict(page='$pg',preset='$pre',run=$i,perf=c['performance']['score'],a11y=c['accessibility']['score'],bp=c['best-practices']['score'],seo=c['seo']['score'],lcp=a['largest-contentful-paint']['numericValue'],cls=a['cumulative-layout-shift']['numericValue'],tbt=a['total-blocking-time']['numericValue'])))" >> $out
done; done; done
echo DONE >> $out
