#!/usr/bin/env python3
"""QA static audit over every public page (fr/ en/ index.html) and admin pages.
Output: docs/qa/data/static.json   Run: python -I tools/qa/static_audit.py"""
import json, re, sys
from pathlib import Path
from urllib.parse import urlparse, unquote
from collections import defaultdict
from bs4 import BeautifulSoup

ROOT = Path(__file__).resolve().parents[2] / "prototype_html"
OUT = Path(__file__).resolve().parents[2] / "docs/qa/data"
OUT.mkdir(parents=True, exist_ok=True)
SITE = "https://www.africaglobalpress.example"
pages = sorted(ROOT.glob("fr/**/*.html")) + sorted(ROOT.glob("en/**/*.html")) + [ROOT / "index.html"]
admin = sorted(ROOT.glob("admin/*.html"))
res = defaultdict(list)


def bad(kind, p, msg):
    res[kind].append(f"{p.relative_to(ROOT).as_posix()} :: {msg}")


titles = defaultdict(list)
descs = defaultdict(list)
stats = dict(pages=len(pages), admin=len(admin))
sm = (ROOT / "sitemap.xml").read_text(encoding="utf8")
sm_locs = set(re.findall(r"<loc>([^<]+)</loc>", sm))
cache = {}


def soup_of(path):
    path = Path(path)
    if path not in cache:
        cache[path] = BeautifulSoup(path.read_text(encoding="utf8"), "lxml")
    return cache[path]


for p in pages + admin:
    isadmin = "admin" in p.parts
    src = p.read_text(encoding="utf8")
    s = soup_of(p)
    rel = p.relative_to(ROOT).as_posix()
    lang = s.html.get("lang")
    t = s.title.get_text(strip=True) if s.title else None
    dm = s.find("meta", attrs={"name": "description"})
    d = dm.get("content") if dm else None
    if not t:
        bad("no_title", p, "")
    if not isadmin:
        if not d:
            bad("no_description", p, "")
        titles[(lang, t)].append(rel)
        descs[(lang, d)].append(rel)
        if t and len(t) > 70:
            bad("title_long", p, f"{len(t)} chars")
        if d and len(d) > 170:
            bad("desc_long", p, f"{len(d)} chars")
        if d and len(d) < 50:
            bad("desc_short", p, f"{len(d)} chars")
    for tag in s.find_all(["a", "link", "script", "img", "source", "form", "iframe", "video", "audio"]):
        for a in ("href", "src", "action", "srcset", "poster"):
            v = tag.get(a)
            if not v:
                continue
            urls = [x.strip().split(" ")[0] for x in v.split(",")] if a == "srcset" else [v]
            for u in urls:
                if not u or u.startswith(("mailto:", "tel:", "data:", "javascript:", "blob:")) or u == "#":
                    continue
                if re.search(r"pressafrica\.media", u):
                    bad("pressafrica_media", p, f"{tag.name}[{a}]={u[:90]}")
                pu = urlparse(u)
                if pu.scheme in ("http", "https"):
                    if tag.name == "link" and tag.get("rel") and any(r in ("canonical", "alternate") for r in tag["rel"]) and u.startswith(SITE):
                        continue
                    if tag.name == "a" and pu.netloc.startswith(("www.facebook", "x.com", "www.pinterest", "www.instagram", "twitter.com")):
                        continue
                    bad("absolute_host", p, f"{tag.name}[{a}]={u[:100]}")
                    continue
                if u.startswith("//"):
                    bad("absolute_host", p, u)
                    continue
                path = pu.path
                if not path:
                    if pu.fragment and pu.fragment != "top":
                        if not s.find(id=pu.fragment) and not s.find("a", attrs={"name": pu.fragment}):
                            bad("broken_anchor", p, f"#{pu.fragment}")
                    continue
                tgt = (p.parent / unquote(path)).resolve()
                if not tgt.exists():
                    bad("broken_link", p, f"{tag.name}[{a}]={u}")
                    continue
                if pu.fragment and tgt.suffix == ".html" and tgt != p.resolve():
                    if not soup_of(tgt).find(id=pu.fragment):
                        bad("broken_anchor", p, f"{u}")
    for im in s.find_all("img"):
        if im.get("alt") is None:
            bad("img_no_alt", p, im.get("src", "")[:80])
        if not im.get("width") or not im.get("height"):
            bad("img_no_dimensions", p, im.get("src", "")[:80])
        if not isadmin and im.get("loading") != "lazy" and im.get("fetchpriority") != "high":
            res["img_not_lazy"].append(f"{rel} :: {im.get('src','')[:70]}")
        if not isadmin and not im.get("srcset") and not im.get("src", "").startswith("data:"):
            res["img_no_srcset"].append(f"{rel} :: {im.get('src','')[:70]}")
    for a in s.find_all("a"):
        if not a.get_text(strip=True) and not a.get("aria-label") and not a.get("title") and not a.find("img", alt=lambda x: x):
            bad("link_no_name", p, str(a)[:100])
        if a.get("target") == "_blank" and "noopener" not in (a.get("rel") or []):
            bad("blank_no_noopener", p, a.get("href", ""))
    for b in s.find_all("button"):
        if not b.get_text(strip=True) and not b.get("aria-label") and not b.get("title"):
            bad("button_no_name", p, str(b)[:100])
    for i in s.find_all(["input", "select", "textarea"]):
        if i.get("type") in ("hidden", "submit", "button"):
            continue
        iid = i.get("id")
        if not (i.get("aria-label") or i.get("aria-labelledby") or i.get("title") or (iid and s.find("label", attrs={"for": iid})) or i.find_parent("label")):
            bad("input_no_label", p, str(i)[:100])
    hs = [(int(h.name[1]), h.get_text(" ", strip=True)[:50]) for h in s.find_all(re.compile("^h[1-6]$"))]
    h1 = [h for h in hs if h[0] == 1]
    if len(h1) != 1:
        bad("h1_count", p, f"{len(h1)} h1")
    prev = 0
    for lv, tx in hs:
        if prev and lv > prev + 1:
            bad("heading_skip", p, f"h{prev}->h{lv} '{tx}'")
            break
        prev = lv
    ids = [x.get("id") for x in s.find_all(id=True)]
    dup = {i for i in ids if ids.count(i) > 1}
    if dup:
        bad("dup_ids", p, ",".join(sorted(dup))[:120])
    if not s.find("meta", attrs={"name": "viewport"}):
        bad("no_viewport", p, "")
    if not isadmin:
        if not s.find("main"):
            bad("no_main", p, "")
        if not s.find("a", href=re.compile(r"^#(main|content|contenu)")):
            bad("no_skip_link", p, "")
    if isadmin or p == ROOT / "index.html":
        continue
    can = s.find("link", rel="canonical")
    can = can.get("href") if can else None
    if not can:
        bad("no_canonical", p, "")
    else:
        if can != f"{SITE}/{rel}":
            bad("canonical_mismatch", p, f"{can}")
        if can not in sm_locs and not any(x in rel for x in ("recherche.html", "search.html")):
            bad("not_in_sitemap", p, can)
    hl = {l.get("hreflang"): l.get("href") for l in s.find_all("link", rel="alternate") if l.get("hreflang")}
    if not all(hl.get(k) for k in ("fr", "en", "x-default")):
        bad("hreflang_incomplete", p, str(hl))
    else:
        for lg in ("fr", "en"):
            tp = ROOT / urlparse(hl[lg]).path.lstrip("/")
            if not tp.exists():
                bad("hreflang_target_missing", p, hl[lg])
            else:
                bs = soup_of(tp)
                bl = {l.get("hreflang"): l.get("href") for l in bs.find_all("link", rel="alternate") if l.get("hreflang")}
                if bl.get(lang) != can:
                    bad("hreflang_not_reciprocal", p, f"{lg}->{hl[lg]} back={bl.get(lang)}")
    og = {m.get("property"): m.get("content") for m in s.find_all("meta", property=True)}
    for k in ("og:title", "og:description", "og:image", "og:url", "og:type", "og:locale"):
        if not og.get(k):
            bad("og_missing", p, k)
    tw = {m.get("name"): m.get("content") for m in s.find_all("meta", attrs={"name": re.compile("^twitter:")})}
    for k in ("twitter:card", "twitter:title", "twitter:image"):
        if not tw.get(k):
            bad("twitter_missing", p, k)
    if og.get("og:image", "").startswith(SITE):
        if not (ROOT / og["og:image"][len(SITE) + 1:]).exists():
            bad("og_image_missing_file", p, og["og:image"])
    if og.get("og:locale") and og["og:locale"][:2] != lang:
        bad("og_locale_lang", p, og["og:locale"])
    if og.get("og:url") and og["og:url"] != can:
        bad("og_url_mismatch", p, og["og:url"])
    for sc in s.find_all("script", type="application/ld+json"):
        try:
            j = json.loads(sc.string)
        except Exception as e:
            bad("jsonld_invalid", p, str(e)[:80])
            continue
        ty = j.get("@type")
        req = {"NewsArticle": ["headline", "datePublished", "image", "author", "publisher", "mainEntityOfPage"],
               "BreadcrumbList": ["itemListElement"], "WebSite": ["url", "name"], "Organization": ["name", "url"],
               "FAQPage": ["mainEntity"]}.get(ty, [])
        for r in req:
            if not j.get(r):
                bad("jsonld_missing_field", p, f"{ty}.{r}")
        if ty == "BreadcrumbList":
            items = j.get("itemListElement", [])
            for n, it in enumerate(items):
                if "item" not in it and n != len(items) - 1:
                    bad("jsonld_breadcrumb_item", p, str(it)[:80])
            if len(items) < 2:
                res["jsonld_breadcrumb_lt2"].append(rel)
        if ty == "NewsArticle":
            if j.get("headline") and len(j["headline"]) > 110:
                res["jsonld_headline_gt110"].append(f"{rel} :: {len(j['headline'])}")
            if j.get("inLanguage") and j["inLanguage"] != lang:
                bad("jsonld_lang", p, j["inLanguage"])
        res["_jsonld_types"].append(ty)
    res["_html_size"].append((rel, len(src.encode())))
    for sc in s.head.find_all("script", src=True):
        if not sc.get("defer") and not sc.get("async") and sc.get("type") != "module":
            bad("render_blocking_script", p, sc["src"])

for (lg, t), v in titles.items():
    if len(v) > 1 and t:
        res["dup_title"].append(f"{lg} '{t[:60]}' x{len(v)}: {', '.join(v[:4])}")
for (lg, d), v in descs.items():
    if len(v) > 1 and d:
        res["dup_description"].append(f"{lg} '{d[:50]}' x{len(v)}: {', '.join(v[:4])}")
for loc in sm_locs:
    if not (ROOT / loc[len(SITE) + 1:]).exists():
        res["sitemap_dead"].append(loc)
files = {SITE + "/" + p.relative_to(ROOT).as_posix() for p in pages if p != ROOT / "index.html"}
for f in sorted(files - sm_locs):
    res["sitemap_missing"].append(f)
stats["sitemap_urls"] = len(sm_locs)
big = []
tot = 0
for f in (ROOT / "assets/img").rglob("*"):
    if f.is_file():
        sz = f.stat().st_size
        tot += sz
        if sz > 150_000:
            big.append((f.relative_to(ROOT).as_posix(), sz))
res["_big_images"] = sorted(big, key=lambda x: -x[1])[:40]
stats["img_total_mb"] = round(tot / 1e6, 1)
stats["css_kb"] = {f.name: round(f.stat().st_size / 1024, 1) for f in (ROOT / "assets/css").glob("*.css")}
stats["js_kb"] = {f.name: round(f.stat().st_size / 1024, 1) for f in (ROOT / "assets/js").glob("*.js")}
stats["fonts"] = {f.name: round(f.stat().st_size / 1024, 1) for f in (ROOT / "assets/fonts").glob("*")}
res["_stats"] = stats
json.dump(res, open(OUT / "static.json", "w", encoding="utf8"), ensure_ascii=False, indent=1)
for k, v in res.items():
    if not k.startswith("_"):
        print(f"{k}: {len(v)}", [str(x)[:150] for x in v[:3]])
print(json.dumps(stats)[:900])
