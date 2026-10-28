#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Self-check for the generated static site: broken relative links, external links, <img> attributes,
heading hierarchy, SEO tags, 'pressafrica.media' leaks. Usage: python tools/site_check.py"""
import re
import sys
from pathlib import Path
from urllib.parse import unquote, urlparse

from bs4 import BeautifulSoup

ROOT = Path(__file__).resolve().parent.parent / "prototype_html"
SOCIAL_HOSTS = ("www.facebook.com", "x.com", "twitter.com", "www.linkedin.com", "www.pinterest.com", "www.instagram.com")   # share intents + profiles; every link must carry rel=noopener
LEAK = re.compile(r"pressafrica\.media", re.I)

import os
EXISTS = {os.path.normpath(str(x)) for x in ROOT.rglob("*") if x.is_file()}


def exists(path):
    return os.path.normpath(str(path)) in EXISTS


pages = sorted(list(ROOT.glob("fr/**/*.html")) + list(ROOT.glob("en/**/*.html")) + [ROOT / "index.html"])
import os
if os.environ.get("LIMIT"): pages = pages[:int(os.environ["LIMIT"])]
problems = {}
stats = dict(pages=len(pages), links=0, imgs=0)


def bad(kind, page, msg):
    problems.setdefault(kind, []).append(f"{page.relative_to(ROOT)}: {msg}")


titles, descs = {}, {}
for p in pages:
    src = p.read_text(encoding="utf8")
    soup = BeautifulSoup(src, "html.parser")
    rel = p.relative_to(ROOT).as_posix()
    # leaks (text email allowed only in mailto/email text, never in href/src except mailto)
    for t in soup.find_all(True):
        for a in ("href", "src", "srcset", "content", "action"):
            v = t.get(a)
            if v and LEAK.search(v) and not v.startswith("mailto:"):
                bad("leak", p, f"{t.name}[{a}]={v[:80]}")
    # links
    for t in soup.find_all(["a", "link", "script", "img", "source", "form"]):
        for a in ("href", "src", "action"):
            v = t.get(a)
            if not v or v.startswith(("#", "mailto:", "tel:", "data:", "javascript:")):
                continue
            stats["links"] += 1
            u = urlparse(v)
            if u.scheme in ("http", "https"):
                if t.name == "link" and t.get("rel") and ("canonical" in t["rel"] or "alternate" in t["rel"]):
                    continue
                if t.name == "a" and u.netloc in SOCIAL_HOSTS and "noopener" in (t.get("rel") or []):
                    continue
                if t.name == "a" and "dir-link" in (t.get("class") or []) and "noopener" in (t.get("rel") or []) and t.get("target") == "_blank":
                    continue   # directory entries (radios, television): official site of the broadcaster, never followed by search engines
                bad("external", p, f"{t.name} {a}={v}")
                continue
            target = p.parent / unquote(u.path)
            if not exists(target):
                bad("broken", p, f"{t.name} {a}={v}")
        ss = t.get("srcset")
        if ss:
            for part in ss.split(","):
                f = part.strip().split(" ")[0]
                if f and not f.startswith("data:") and not exists(p.parent / f):
                    bad("broken", p, f"srcset {f}")
    # images
    for im in soup.find_all("img"):
        stats["imgs"] += 1
        if not (im.get("alt") or "").strip():
            bad("img-alt", p, im.get("src", "")[:60])
        if not (im.get("title") or "").strip():
            bad("img-title", p, im.get("src", "")[:60])
        if not (im.get("width") and im.get("height")):
            bad("img-size", p, im.get("src", "")[:60])
    # headings
    hs = [(int(h.name[1]), h.get_text(" ", strip=True)) for h in soup.find_all(re.compile(r"^h[1-6]$"))]
    h1s = [h for h in hs if h[0] == 1]
    if len(h1s) != 1:
        bad("h1", p, f"{len(h1s)} h1")
    last = 0
    for lvl, tx in hs:
        if last and lvl > last + 1:
            bad("heading-skip", p, f"h{last}->h{lvl} '{tx[:40]}'")
        last = lvl
    # seo
    if p.name != "index.html" or p.parent != ROOT:
        for sel, name in (('link[rel=canonical]', "canonical"), ('meta[name=description]', "description"), ('meta[property="og:title"]', "og:title"),
                          ('meta[property="og:image"]', "og:image"), ('meta[name="twitter:card"]', "twitter"), ('script[type="application/ld+json"]', "jsonld"),
                          ('link[hreflang]', "hreflang")):
            if not soup.select_one(sel):
                if name == "og:image" and False:
                    continue
                bad("seo", p, f"missing {name}")
        if "BreadcrumbList" not in src:
            bad("seo", p, "no BreadcrumbList")
        if "/articles/" in rel and "NewsArticle" not in src:
            bad("seo", p, "no NewsArticle")
        t = soup.title.get_text() if soup.title else ""
        d = (soup.select_one("meta[name=description]") or {}).get("content", "")
        titles.setdefault(t, []).append(rel)
        descs.setdefault(d, []).append(rel)
        if not soup.select_one("main#main") or not soup.select_one("header") or not soup.select_one("footer") or not soup.select_one("a.skip-link"):
            bad("landmarks", p, "missing main/header/footer/skip")
        if not soup.html.get("lang"):
            bad("lang", p, "no lang")
for t, ps in titles.items():
    if len(ps) > 1:
        problems.setdefault("dup-title", []).append(f"{t[:60]} -> {ps[:4]}")
for d, ps in descs.items():
    if len(ps) > 1 and d:
        problems.setdefault("dup-desc", []).append(f"{d[:60]} -> {ps[:4]}")

print(stats)
if not problems:
    print("OK: no problems")
for k, v in problems.items():
    print(f"== {k}: {len(v)}")
    for x in v[:12]:
        print("   ", x)
sys.exit(1 if problems else 0)
