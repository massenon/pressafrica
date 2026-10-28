#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Africa Global Press - static site generator (FR + EN).

Reads prototype_html/data/{articles,categories,image-manifest}.json and writes
prototype_html/{index.html, fr/**, en/**, sitemap.xml, robots.txt, manifest.webmanifest, sw.js}
plus docs/mapping/URL-MAP.md. Everything is relative so the site opens via file://.

Usage:  python tools/build_site.py   (then run the Tailwind build, see build:css in package.json)
"""
import datetime
import html
import json
import posixpath
import re
import sys
import unicodedata
from pathlib import Path
from urllib.parse import urlparse

from bs4 import BeautifulSoup

sys.path.insert(0, str(Path(__file__).resolve().parent))
from critical_css import critical as _critical          # noqa: E402
from site_en_translations import PAIRS, T as EN_TR          # noqa: E402
from site_strings import (CATS, DESCRIPTIONS, MONTHS, SEO_TITLES, STATIC_PATHS, UI, WEEKDAYS)  # noqa: E402

# ----------------------------------------------------------------------------------------------
# CONFIG - the ONLY place a public host is declared (placeholder, replace before going live).
# ----------------------------------------------------------------------------------------------
SITE_URL = "https://www.africaglobalpress.example"      # PLACEHOLDER host (canonical, hreflang, JSON-LD, sitemap)
CONTACT_EMAIL = "contact@pressafrica.media"             # editorial e-mail (mailto only, never a web link)
CONTACT_EMAIL2 = "africaglobalpress@gmail.com"
PHONE_HREF = "tel:+33758802527"
SOCIAL = {  # placeholders for social profiles (external allowed, rel=noopener)
    "facebook": "https://www.facebook.com/africaglobalpress",
    "x": "https://x.com/africaglobalpress",
    "pinterest": "https://www.pinterest.com/africaglobalpress/",
    "instagram": "https://www.instagram.com/africaglobalpress/",
}
FONT_PRELOAD_CROSSORIGIN = False   # False: no console CORS error when opened via file://. Set True when deployed over http(s).
LANGS = ("fr", "en")
NOW = datetime.date.today()

ROOT = Path(__file__).resolve().parent.parent
PROTO = ROOT / "prototype_html"
DATA = PROTO / "data"

ARTICLES_RAW = json.loads((DATA / "articles.json").read_text(encoding="utf8"))
MAN_FILE = DATA / "image-manifest.json"
MAN = json.loads(MAN_FILE.read_text(encoding="utf8")) if MAN_FILE.exists() else {}

esc = html.escape
_CSS_FILE = PROTO / "assets" / "css" / "app.min.css"
INLINE_CRITICAL = False   # measured: inlining ~32KB of critical CSS made Lighthouse mobile LCP worse (HTML 128KB) than one blocking 55KB stylesheet
CRITICAL_CSS = _critical(_CSS_FILE.read_text(encoding="utf8")) if (INLINE_CRITICAL and _CSS_FILE.exists()) else ""


def attr(s):
    return html.escape(str(s), quote=True)


def slugify(s):
    s = unicodedata.normalize("NFKD", s).encode("ascii", "ignore").decode()
    return re.sub(r"[^a-z0-9]+", "-", s.lower()).strip("-")[:90].strip("-") or "article"


def clean_title(t):
    return re.sub(r"\s+", " ", html.unescape(t).replace("\xa0", " ")).strip()


# ----------------------------------------------------------------------------------------------
# Context (current page / language) and relative-URL helpers
# ----------------------------------------------------------------------------------------------
CTX = {"cur": "", "lang": "fr"}


def rel(target, cur=None):
    cur = cur or CTX["cur"]
    return posixpath.relpath(target, posixpath.dirname(cur) or ".")


def A(path):
    """relative URL to an asset (path relative to prototype_html)"""
    return rel(path)


def U():
    return UI[CTX["lang"]]


DBMAP = {}
for _k, _c in CATS.items():
    for _s in _c["db"]:
        DBMAP[_s] = _k


def cat_label(key, lang=None):
    return CATS[key][lang or CTX["lang"]]


def cat_path(key, lang):
    return f"{lang}/{CATS[key]['p_' + lang]}"


def static_path(key, lang):
    return f"{lang}/{STATIC_PATHS[key][0 if lang == 'fr' else 1]}"


def page_path(key, lang):
    if key in CATS:
        return cat_path(key, lang)
    if key in STATIC_PATHS:
        return static_path(key, lang)
    if key.startswith("art:"):
        a = ART_BY_KEY[(lang, key[4:])] if (lang, key[4:]) in ART_BY_KEY else None
        return a["path"] if a else None
    raise KeyError(key)


def L(key, lang=None):
    """relative link from the current page to the page `key` in the current (or given) language"""
    p = page_path(key, lang or CTX["lang"])
    return rel(p) if p else "#"


def fmt_date(d, lang=None, weekday=False):
    lang = lang or CTX["lang"]
    m = MONTHS[lang][d.month - 1]
    if lang == "fr":
        s = f"{d.day if d.day > 1 else '1er'} {m} {d.year}"
        if d.day == 1:
            s = f"1er {m} {d.year}"
    else:
        s = f"{m} {d.day}, {d.year}"
    if weekday:
        s = f"{WEEKDAYS[lang][d.weekday()]} " + s if lang == "fr" else f"{WEEKDAYS[lang][d.weekday()]}, " + s
    return s


# ----------------------------------------------------------------------------------------------
# Article model
# ----------------------------------------------------------------------------------------------
def text_of(raw):
    return BeautifulSoup(re.sub(r"<!--.*?-->", "", raw, flags=re.S), "html.parser").get_text(" ", strip=True)


def truncate(t, n=160):
    t = re.sub(r"\s+", " ", t).strip()
    if len(t) <= n:
        return t
    cut = t[:n].rsplit(" ", 1)[0].rstrip(" ,;:-—")
    return cut + "…"


def first_excerpt(raw):
    soup = BeautifulSoup(re.sub(r"<!--.*?-->", "", raw, flags=re.S), "html.parser")
    for e in soup.find_all(["p", "h2", "h3"]):
        t = e.get_text(" ", strip=True).replace("\xa0", " ")
        if len(t) > 70 and not t.startswith(("Rédacteur", "Redacteur")):
            return t
    return soup.get_text(" ", strip=True)


ARTICLES = {"fr": [], "en": []}
ART_BY_KEY = {}      # (lang, id) -> article
ART_BY_DBSLUG = {"fr": {}, "en": {}}


TR_DIR = DATA / "translations" / "en"
TR_ALLOWED = {"p", "h2", "h3", "h4", "ul", "ol", "li", "strong", "em", "blockquote", "a", "br", "b", "i"}


def load_translation(rid):
    """Full EN translation for a FR-only article, or None (missing / invalid -> old stub behaviour)."""
    f = TR_DIR / f"{rid}.json"
    if not f.exists():
        return None
    try:
        d = json.loads(f.read_text(encoding="utf8"))
        body = d["body_html"]
        if not isinstance(body, str):
            return None
        for k in ("title", "excerpt", "seo_title", "seo_desc", "image_alt"):
            if not isinstance(d.get(k), str) or not d[k].strip():
                return None
        d["body_html"] = sanitize_translation(body)
        if len(text_of(d["body_html"])) < 200:
            return None
        return d
    except Exception as e:   # noqa
        print("translation", rid, "ignored:", e)
        return None


SOCIAL_HOSTS = ("www.facebook.com", "x.com", "www.pinterest.com", "www.instagram.com", "twitter.com", "www.linkedin.com")


def sanitize_translation(raw):
    soup = BeautifulSoup(re.sub(r"<!--.*?-->", "", raw, flags=re.S), "html.parser")
    for t in soup(["script", "style", "svg", "iframe", "form", "noscript", "button", "img", "figure", "picture", "video", "audio", "source"]):
        t.decompose()
    for h in soup.find_all(["h1", "h2", "h3", "h4", "h5", "h6"]):
        txt = h.get_text(" ", strip=True)
        if not txt:
            h.decompose()
        elif len(txt) > 100 or txt.endswith((".", ";", "!", "…")):
            h.name = "p"
    seen_h2 = False
    for h in soup.find_all(["h1", "h2", "h3", "h4", "h5", "h6"]):
        h.name = "h2" if (h.name in ("h1", "h2") or not seen_h2) else "h3"
        seen_h2 = seen_h2 or h.name == "h2"
    for aa in soup.find_all("a"):
        href = (aa.get("href") or "").strip()
        u = urlparse(href)
        if "pressafrica.media" in href or "wp-content" in href:
            aa.unwrap()
        elif u.scheme == "https" and u.netloc in SOCIAL_HOSTS:
            aa.attrs = {"href": href, "rel": "noopener noreferrer", "target": "_blank"}
        elif href.startswith("mailto:"):
            aa.attrs = {"href": href}
        else:
            aa.unwrap()
    for t in soup.find_all(True):
        if t.name not in TR_ALLOWED:
            t.unwrap()
        elif t.name != "a":
            t.attrs = {}
    for t in soup.find_all(["p", "li", "h2", "h3", "h4", "strong", "em", "b", "i", "blockquote"]):
        if not t.get_text(strip=True):
            t.decompose()
    out = sanitize_text(str(soup))
    out = re.sub(r"[\u00a0\u202f\u2009\u2007]+", " ", out)
    return re.sub(r"[ \t]+", " ", out).strip()


def build_articles():
    posts = [a for a in ARTICLES_RAW if a["type"] == "post"]
    by_id = {a["id"]: a for a in posts}
    en_native = set(PAIRS.values())
    inv = {v: k for k, v in PAIRS.items()}
    used = {"fr": set(), "en": set()}

    def mk(raw, lang, translated=False, tr=None):
        title = clean_title(raw["title"])
        slug = raw["slug"]
        if slug.isdigit():
            slug = slugify(title)
        slug = slugify(slug) if len(slug) > 90 else slug
        if tr:
            title, slug = clean_title(tr["title"]), slugify(tr["title"])
        elif translated:
            title_en, exc_en = EN_TR[raw["id"]]
            title, slug = title_en, slugify(title_en)
        if slug in used[lang]:
            slug = f"{slug}-{raw['id']}"
        used[lang].add(slug)
        cats = sorted({DBMAP[c["slug"]] for c in raw["categories"] if c["slug"] in DBMAP},
                      key=lambda k: unicodedata.normalize("NFKD", CATS[k][lang]).lower())
        body = tr["body_html"] if tr else raw["content"]
        txt = text_of(body)
        exc = tr["excerpt"].strip() if tr else (EN_TR[raw["id"]][1] if translated else truncate(first_excerpt(body), 165))
        img = (raw.get("image") or {}).get("path")
        d = datetime.datetime.strptime(raw["date"], "%Y-%m-%d %H:%M:%S")
        mod = datetime.datetime.strptime(raw["modified"], "%Y-%m-%d %H:%M:%S")
        a = dict(id=raw["id"], lang=lang, slug=slug, title=title, date=d, modified=mod,
                 author=(raw["author"] or "Africa Global Press").replace("\xa0", " "),
                 cats=cats, img=img, img_alt=clean_title((raw.get("image") or {}).get("alt") or title),
                 body=body, excerpt=exc, translated=translated, minutes=max(3, round(len(txt) / 950)),
                 path=f"{lang}/articles/{slug}.html", dbslug=raw["slug"], tr=bool(tr))
        if tr:
            a.update(seo_title=tr["seo_title"].strip(), seo_desc=tr["seo_desc"].strip(), img_alt=tr["image_alt"].strip())
        return a

    for raw in sorted(posts, key=lambda r: (r["date"], int(r["id"])), reverse=True):
        rid = raw["id"]
        if rid in en_native:
            a = mk(raw, "en")
            ARTICLES["en"].append(a)
            ART_BY_KEY[("en", rid)] = a
            ART_BY_DBSLUG["en"][raw["slug"]] = a
        else:
            a = mk(raw, "fr")
            ARTICLES["fr"].append(a)
            ART_BY_KEY[("fr", rid)] = a
            ART_BY_DBSLUG["fr"][raw["slug"]] = a
            if rid not in PAIRS:   # FR-only -> EN stub with translated title/excerpt
                tr = load_translation(rid)
                t = mk(raw, "en", translated=not tr, tr=tr)
                ARTICLES["en"].append(t)
                ART_BY_KEY[("en", rid)] = t
    # pair alternates
    for lang in LANGS:
        ARTICLES[lang].sort(key=lambda a: (a["date"], int(a["id"])), reverse=True)


def alt_article(a):
    """equivalent article in the other language"""
    other = "en" if a["lang"] == "fr" else "fr"
    if a["lang"] == "fr":
        eid = PAIRS.get(a["id"], a["id"])
        return ART_BY_KEY.get(("en", eid))
    fid = {v: k for k, v in PAIRS.items()}.get(a["id"], a["id"])
    return ART_BY_KEY.get(("fr", fid)) if (("fr", fid) in ART_BY_KEY and ART_BY_KEY[("fr", fid)]["lang"] == "fr") else None


def resolve(lang, fr_id):
    """curated FR id -> article object in the given language"""
    if lang == "fr":
        return ART_BY_KEY[("fr", fr_id)]
    return ART_BY_KEY[("en", PAIRS.get(fr_id, fr_id))]


def in_cat(lang, key):
    return [a for a in ARTICLES[lang] if key in a["cats"]]


def art_url(a):
    return rel(a["path"])


# ----------------------------------------------------------------------------------------------
# Images
# ----------------------------------------------------------------------------------------------
def man_entry(path):
    return MAN.get(path)


def img_files(m):
    """unique (true_width, file) pairs for a manifest entry, ascending"""
    seen = {}
    for w in sorted(int(x) for x in m["widths"]):
        f = m["widths"][str(w)]
        if f not in seen:
            seen[f] = min(w, m.get("width") or w)
    return sorted(((v, k) for k, v in seen.items()))


def img_html(path, alt, title=None, sizes="100vw", cls="", eager=False, lqip=True, prio=None, low=False):
    """responsive <img> from the manifest; degrades to a styled placeholder if the manifest lacks it"""
    m = man_entry(path) if path else None
    title = title or alt
    if not m:
        return f'<span class="img-ph {cls}" role="img" aria-label="{attr(alt)}" title="{attr(title)}"></span>'
    files = img_files(m)
    if not re.search(r"100vw|[7-9]\d\dpx|1\d{3}px", re.sub(r"\([^)]*\)", "", sizes)):
        small = [f for f in files if f[0] <= 860]
        files = small or files[:1]
    srcset = ", ".join(f"{A(f)} {w}w" for w, f in files)
    mid = min(files, key=lambda x: abs(x[0] - 860))
    w0 = mid[0]
    h0 = round(w0 * (m.get("height") or 1) / (m.get("width") or 1))
    prio = eager if prio is None else prio
    load = ('loading="eager" fetchpriority="high" decoding="async"' if prio else 'loading="eager" decoding="async"') if eager else ('loading="lazy" fetchpriority="low" decoding="async"' if low else 'loading="lazy" decoding="async"')
    lq = f' style="background-image:url(&quot;{m["lqip"]}&quot;)"' if (lqip and eager and m.get("lqip")) else ""
    return (f'<img class="{cls}" src="{A(mid[1])}" srcset="{srcset}" sizes="{sizes}" width="{w0}" height="{h0}" '
            f'alt="{attr(alt)}" title="{attr(title)}" {load}{lq}>')


def site_img(name, alt, title=None, sizes="100vw", cls="", eager=False):
    return img_html("site/" + name, alt, title, sizes, cls, eager, lqip=(name != "logo"), prio=(False if name == "logo" else None))


def og_image(path):
    m = man_entry(path) if path else None
    if not m:
        m = MAN.get("site/hero-africa-map") or MAN.get("site/logo")
    if m and m.get("og"):
        return f"{SITE_URL}/{m['og']}"
    return None


def alt_for(a, kind="card"):
    return (f"Illustration de l'article : {a['title']}" if CTX["lang"] == "fr"
            else f"Illustration for the article: {a['title']}")


# ----------------------------------------------------------------------------------------------
# Icons (inline SVG sprite)
# ----------------------------------------------------------------------------------------------
SPRITE = """<svg xmlns="http://www.w3.org/2000/svg" class="hidden" aria-hidden="true" focusable="false"><defs>
<symbol id="i-search" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" d="M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Zm6-1.5L21 21"/></symbol>
<symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="9.5" r="3.2" fill="currentColor"/><path d="M5.8 18.2c1.4-2.6 3.6-3.8 6.2-3.8s4.8 1.2 6.2 3.8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></symbol>
<symbol id="i-chev" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></symbol>
<symbol id="i-arrow-r" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M4 12h15m-6-6 6 6-6 6"/></symbol>
<symbol id="i-arrow-l" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M20 12H5m6-6-6 6 6 6"/></symbol>
<symbol id="i-angle-r" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7"/></symbol>
<symbol id="i-angle-l" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" d="m15 5-7 7 7 7"/></symbol>
<symbol id="i-fire" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" d="M12.5 2.5c.4 3-1.6 4.4-3 6.2C8 10.6 7 12.3 7 14.5A5.5 5.5 0 0 0 12.5 20 5.5 5.5 0 0 0 18 14.5c0-2.6-1.2-4-2.2-5.4-.3 1.2-1 2-1.9 2.3.6-3-.3-6.4-1.4-8.9Z"/></symbol>
<symbol id="i-burger" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></symbol>
<symbol id="i-close" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></symbol>
<symbol id="i-mail" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" d="M3.5 6h17v12h-17zM3.8 6.5 12 13l8.2-6.5"/></symbol>
<symbol id="i-mic" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 3a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V6a3 3 0 0 0-3-3zM5 11a7 7 0 0 0 14 0M12 18v3"/></symbol>
<symbol id="i-envelope" viewBox="0 0 64 64"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round" d="M8 22 32 8l24 14v28H8zM8 22l24 17 24-17M32 2v-0M14 4l3 4M50 4l-3 4M32 1v5"/></symbol>
<symbol id="i-plus" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 8v8M8 12h8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></symbol>
<symbol id="i-dot" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5" fill="currentColor"/></symbol>
<symbol id="i-up" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="m6 11 6-6 6 6M6 19l6-6 6 6"/></symbol>
<symbol id="i-pause" viewBox="0 0 24 24"><path fill="currentColor" d="M7 5h3.5v14H7zM13.5 5H17v14h-3.5z"/></symbol>
<symbol id="i-play" viewBox="0 0 24 24"><path fill="currentColor" d="M8 5v14l11-7z"/></symbol>
<symbol id="i-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 11v6M12 7.5v.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
<symbol id="i-check" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4.5 4.5L19 7.5"/></symbol>
<symbol id="i-link" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/></symbol>
<symbol id="i-eye" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/></symbol>
<symbol id="i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M12 2.5v2.2M12 19.3v2.2M2.5 12h2.2M19.3 12h2.2M5.3 5.3l1.6 1.6M17.1 17.1l1.6 1.6M18.7 5.3l-1.6 1.6M6.9 17.1l-1.6 1.6"/></symbol>
<symbol id="i-moon" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5Z"/></symbol>
<symbol id="i-facebook" viewBox="0 0 24 24"><path fill="#1877f2" d="M24 12a12 12 0 1 0-13.9 11.9v-8.4H7.1V12h3V9.4c0-3 1.8-4.7 4.5-4.7 1.3 0 2.7.2 2.7.2v3h-1.5c-1.5 0-2 .9-2 1.9V12h3.4l-.5 3.5h-2.9v8.4A12 12 0 0 0 24 12Z"/></symbol>
<symbol id="i-x" viewBox="0 0 24 24"><path fill="currentColor" d="M18.2 2.3h3.3l-7.2 8.3 8.5 11.2h-6.7l-5.2-6.8-6 6.8H1.6l7.7-8.8L1.2 2.3H8l4.7 6.2 5.5-6.2Zm-1.2 17.5h1.8L7 4.1H5l12 15.7Z"/></symbol>
<symbol id="i-pinterest" viewBox="0 0 24 24"><path fill="#e60023" d="M12 0a12 12 0 0 0-4.4 23.2c-.1-.9-.2-2.4 0-3.4l1.4-6s-.4-.7-.4-1.8c0-1.7 1-2.9 2.2-2.9 1 0 1.5.8 1.5 1.7 0 1-.7 2.6-1 4-.3 1.2.6 2.2 1.800 2.200 2.100 0 3.800-2.300 3.800-5.500 0-2.900-2.100-4.900-5-4.900-3.400 0-5.400 2.600-5.400 5.200 0 1 .4 2.100.9 2.700.1.100.1.200.1.400l-.3 1.400c0 .2-.2.300-.4.200-1.500-.7-2.400-2.900-2.400-4.600 0-3.800 2.700-7.200 7.900-7.200 4.100 0 7.400 2.900 7.400 6.900 0 4.100-2.600 7.500-6.200 7.500-1.200 0-2.400-.6-2.800-1.400l-.8 2.900c-.3 1-1 2.300-1.500 3.100A12 12 0 1 0 12 0Z"/></symbol>
<symbol id="i-instagram" viewBox="0 0 24 24"><rect x="2.5" y="2.5" width="19" height="19" rx="5.5" fill="none" stroke="#d6249f" stroke-width="2"/><circle cx="12" cy="12" r="4.4" fill="none" stroke="#d6249f" stroke-width="2"/><circle cx="17.4" cy="6.6" r="1.3" fill="#d6249f"/></symbol>
<symbol id="i-linkedin" viewBox="0 0 24 24"><path fill="#0a66c2" d="M20.45 20.45h-3.56v-5.57c0-1.33-.03-3.04-1.85-3.04-1.86 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.41v1.56h.05a3.74 3.74 0 0 1 3.37-1.85c3.6 0 4.27 2.37 4.27 5.46v6.28ZM5.34 7.43a2.06 2.06 0 1 1 0-4.13 2.06 2.06 0 0 1 0 4.13ZM7.12 20.45H3.56V9h3.56v11.45ZM22.22 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.73V1.73C24 .77 23.2 0 22.22 0Z"/></symbol>
<symbol id="i-share" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M18 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM6 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM18 22a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></symbol>
<symbol id="i-comment" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" d="M4 4h16v12H9l-5 4V4Z"/></symbol>
<symbol id="i-more" viewBox="0 0 24 24"><circle cx="5" cy="12" r="1.8" fill="currentColor"/><circle cx="12" cy="12" r="1.8" fill="currentColor"/><circle cx="19" cy="12" r="1.8" fill="currentColor"/></symbol>
</defs></svg>"""


def icon(name, cls="i"):
    return f'<svg class="{cls}" aria-hidden="true" focusable="false"><use href="#i-{name}"/></svg>'


# ----------------------------------------------------------------------------------------------
# Small HTML building blocks
# ----------------------------------------------------------------------------------------------
def chips(a, link=True):
    out = []
    for k in a["cats"]:
        label = cat_label(k)
        if k in CATS:
            out.append(f'<a class="chip" href="{L(k)}">{esc(label)}</a>' if link else f'<span class="chip">{esc(label)}</span>')
    return f'<div class="chips">{"".join(out)}</div>' if out else ""


def minutes_label(a):
    return U()["read_min"].format(n=a["minutes"])


def meta_date(a, avatar=False):
    u = U()
    av = f'<span class="avatar" aria-hidden="true">{esc(a["author"][:1].upper())}</span>' if avatar else ""
    return (f'<p class="meta">{av}<span class="meta-by">{u["by"]} <b class="author">{esc(a["author"])}</b></span>'
            f'<span class="meta-sep" aria-hidden="true"></span><time datetime="{a["date"].date().isoformat()}">'
            f'{fmt_date(a["date"])}</time></p>')


def meta_read(a):
    u = U()
    return (f'<p class="meta"><span class="meta-by">{u["by"]} <b class="author">{esc(a["author"])}</b></span>'
            f'<span class="meta-sep" aria-hidden="true"></span><span>{minutes_label(a)}</span></p>')


def title_link(a, tag="h3", cls=""):
    return f'<{tag} class="card-title {cls}"><a href="{art_url(a)}">{esc(a["title"])}</a></{tag}>'


def thumb(a, sizes, cls="", eager=False):
    return (f'<a class="thumb {cls}" href="{art_url(a)}" tabindex="-1" aria-hidden="true">'
            f'{img_html(a["img"], alt_for(a), a["title"], sizes, eager=eager)}</a>')


def card_lg(a, tag="h3", eager=False, sizes="(min-width:1024px) 600px, 100vw"):
    return (f'<article class="card card-lg reveal">{thumb(a, sizes, "thumb-fill", eager)}'
            f'<div class="card-ov">{chips(a)}{title_link(a, tag)}'
            f'<p class="excerpt">{esc(a["excerpt"])}</p>{meta_date(a, True)}</div></article>')


def card_md(a, tag="h3", sizes="(min-width:1024px) 300px, 50vw"):
    return (f'<article class="card card-md reveal"><div class="thumb-wrap">{thumb(a, sizes, "thumb-portrait")}{chips(a)}</div>'
            f'{title_link(a, tag)}{meta_read(a)}</article>')


def card_text(a, tag="h3"):
    return (f'<article class="card card-text reveal">{chips(a)}{title_link(a, tag)}'
            f'<p class="excerpt">{esc(truncate(a["excerpt"], 110))}</p>{meta_date(a)}</article>')


def card_econ_lg(a, tag="h3", sizes="(min-width:1024px) 600px, 100vw"):
    return card_lg(a, tag, False, sizes)


def card_econ(a, tag="h3", sizes="(min-width:1024px) 300px, 50vw"):
    return (f'<article class="card card-sm reveal"><div class="thumb-wrap">{thumb(a, sizes, "thumb-land")}{chips(a)}</div>'
            f'{title_link(a, tag)}{meta_read(a)}</article>')


def card_grid(a, tag="h3", sizes="(min-width:1024px) 400px, 100vw"):
    return (f'<article class="card card-grid reveal"><div class="thumb-wrap">{thumb(a, sizes, "thumb-land")}{chips(a)}</div>'
            f'{title_link(a, tag)}<p class="excerpt">{esc(truncate(a["excerpt"], 120))}</p>{meta_read(a)}</article>')


def card_mini(a, sizes="(min-width:1024px) 200px, 50vw"):
    return (f'<article class="card card-mini reveal"><div class="thumb-wrap">{thumb(a, sizes, "thumb-land")}{chips(a)}</div>'
            f'<h2 class="card-title"><a href="{art_url(a)}">{esc(a["title"])}</a></h2><p class="meta"><span>{minutes_label(a)}</span></p></article>')


def sec_title(text, color="brand", tag="h2", id_=None):
    i = f' id="{id_}"' if id_ else ""
    return f'<{tag} class="sec-title sec-{color}"{i}><span class="sq" aria-hidden="true"></span>{esc(text)}</{tag}>'


def newsletter_btn(cls="btn btn-primary", label=None):
    return (f'<button type="button" class="{cls}" data-open="newsletter">{esc(label or U()["newsletter_btn"])}'
            f'{icon("arrow-r", "i i-sm")}</button>')


def search_form(cls="search-form", ph=None, idp="q"):
    u = U()
    return (f'<form class="{cls}" role="search" method="get" action="{L("search")}">'
            f'<label class="sr-only" for="{idp}">{esc(u["search_label"])}</label>'
            f'<span class="sf-ico">{icon("search")}</span>'
            f'<input id="{idp}" class="sf-input" type="search" name="q" placeholder="{attr(ph or u["search_ph"])}" autocomplete="off">'
            f'<button class="sf-btn" type="submit" aria-label="{attr(u["search_btn"])}">{icon("arrow-r")}</button></form>')


def search_block(idp="q-block"):
    u = U()
    tags = "".join(f'<li><a href="{L("search")}?q={urlq(q)}">{esc(lab)}</a></li>' for lab, q in u["tags"])
    return (f'<section class="search-frame reveal" aria-labelledby="{idp}-t"><h2 id="{idp}-t" class="search-frame-title">{esc(u["search_title"])}</h2>'
            f'{search_form("search-form search-form-lg", idp=idp)}'
            f'<ul class="tags"><li class="tags-lead">{esc(u["in_news"])}</li>{tags}</ul></section>')


def urlq(s):
    from urllib.parse import quote
    return quote(s)


WAVE = ('<svg class="wave" aria-hidden="true" focusable="false" preserveAspectRatio="none" viewBox="0 0 1200 12"><path fill="none" '
        'stroke="currentColor" stroke-width="1.2" d="M0 6q15-9 30 0' + 't30 0' * 39 + '"/></svg>')


def editorial_block(show_title_tag="h2"):
    u = U()
    return (f'<section class="editorial" aria-labelledby="ed-t"><div class="editorial-text reveal">'
            f'<p class="editorial-kicker" id="ed-t">{esc(u["ed_kicker"])}</p>'
            f'<h2 class="editorial-title">{esc(u["ed_title"])}</h2><p>{esc(u["ed_text"])}</p>'
            f'{newsletter_btn("btn btn-primary btn-lg")}</div>'
            f'<div class="editorial-map reveal">{site_img("hero-africa-map", u["map_alt"], u["map_title"], "(min-width:1024px) 560px, 100vw", "map-img")}</div></section>')


def tiles_block(heading=None):
    u = U()
    items = []
    for key, name, text, img in u["tiles"]:
        items.append(
            f'<figure class="tile reveal"><a class="tile-img" href="{L(key)}">{site_img(img, u["tiles_alt"][img], name, "(min-width:1024px) 400px, 100vw")}</a>'
            f'<figcaption><span class="tile-dash" aria-hidden="true"></span><a class="tile-name" href="{L(key)}"><strong>{esc(name)}</strong></a>: {esc(text)}</figcaption></figure>')
    return f'<div class="tiles">{"".join(items)}</div>'


def faq_block(qas, idp="faq"):
    u = U()
    items = []
    for i, (q, a) in enumerate(qas):
        items.append(
            f'<div class="faq-item"><h3 class="faq-q"><button type="button" class="faq-btn" aria-expanded="false" aria-controls="{idp}-{i}" id="{idp}-b{i}">'
            f'<span>{esc(q)}</span>{icon("chev", "i faq-chev")}</button></h3>'
            f'<div class="faq-a" id="{idp}-{i}" role="region" aria-labelledby="{idp}-b{i}" hidden><p>{esc(a)}</p></div></div>')
    return (f'<section class="faq reveal" aria-labelledby="{idp}-t"><h2 class="sec-title sec-brand" id="{idp}-t"><span class="sq" aria-hidden="true"></span>{esc(u["faq_title"])}</h2>'
            f'<div class="faq-list">{"".join(items)}</div></section>')


def faq_jsonld(qas):
    return {"@context": "https://schema.org", "@type": "FAQPage",
            "mainEntity": [{"@type": "Question", "name": q, "acceptedAnswer": {"@type": "Answer", "text": a}} for q, a in qas]}


def breadcrumb(items):
    """items: list of (label, href or None)"""
    u = U()
    lis = []
    for i, (lab, href) in enumerate(items):
        if href and i < len(items) - 1:
            lis.append(f'<li><a href="{href}">{esc(lab)}</a></li>')
        else:
            lis.append(f'<li><span aria-current="page">{esc(lab)}</span></li>')
    return f'<nav class="breadcrumb" aria-label="{attr(u["breadcrumb"])}"><ol>{"".join(lis)}</ol></nav>'


def breadcrumb_ld(items):
    out = []
    for i, (lab, path) in enumerate(items, 1):
        e = {"@type": "ListItem", "position": i, "name": lab}
        if path:
            e["item"] = f"{SITE_URL}/{path}"
        out.append(e)
    return {"@context": "https://schema.org", "@type": "BreadcrumbList", "itemListElement": out}


def newsletter_card():
    u = U()
    return (f'<aside class="nl-card reveal" aria-labelledby="nlc-t">{icon("envelope", "nl-ico")}'
            f'<h2 id="nlc-t">{esc(u["nl_card_title"])}</h2><p>{esc(u["nl_card_text"])}</p>'
            f'<button type="button" class="btn btn-ink" data-open="newsletter">{esc(u["subscribe"])}</button></aside>')


# ----------------------------------------------------------------------------------------------
# Page shell: head, header, nav, footer, overlays
# ----------------------------------------------------------------------------------------------
def head(meta):
    u = U()
    lang = CTX["lang"]
    cur = CTX["cur"]
    other = "en" if lang == "fr" else "fr"
    canon = f"{SITE_URL}/{cur}"
    alts = meta.get("alternates", {})
    tags = []
    tags.append('<meta charset="utf-8">')
    tags.append('<meta name="viewport" content="width=device-width, initial-scale=1">')
    tags.append(f'<title>{esc(meta["title"])}</title>')
    tags.append(f'<meta name="description" content="{attr(meta["desc"])}">')
    tags.append(f'<meta name="robots" content="{meta.get("robots", "index,follow,max-image-preview:large")}">')
    tags.append(f'<link rel="canonical" href="{canon}">')
    for lg in LANGS:
        if lg in alts:
            tags.append(f'<link rel="alternate" hreflang="{lg}" href="{SITE_URL}/{alts[lg]}">')
    if "fr" in alts:
        tags.append(f'<link rel="alternate" hreflang="x-default" href="{SITE_URL}/{alts["fr"]}">')
    tags.append(f'<meta property="og:site_name" content="Africa Global Press">')
    tags.append(f'<meta property="og:type" content="{meta.get("og_type", "website")}">')
    tags.append(f'<meta property="og:title" content="{attr(meta["title"])}">')
    tags.append(f'<meta property="og:description" content="{attr(meta["desc"])}">')
    tags.append(f'<meta property="og:url" content="{canon}">')
    tags.append(f'<meta property="og:locale" content="{u["locale"]}">')
    tags.append(f'<meta property="og:locale:alternate" content="{u["og_locale_alt"]}">')
    ogi = meta.get("og_image")
    if ogi:
        tags.append(f'<meta property="og:image" content="{ogi}">')
        tags.append('<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">')
        tags.append(f'<meta property="og:image:alt" content="{attr(meta.get("og_alt", meta["title"]))}">')
    if meta.get("og_type") == "article":
        a = meta["article"]
        tags.append(f'<meta property="article:published_time" content="{a["date"].isoformat()}+01:00">')
        tags.append(f'<meta property="article:modified_time" content="{a["modified"].isoformat()}+01:00">')
        tags.append(f'<meta property="article:author" content="{attr(a["author"])}">')
        for k in a["cats"]:
            tags.append(f'<meta property="article:section" content="{attr(cat_label(k))}">')
    tags.append('<meta name="twitter:card" content="summary_large_image">')
    tags.append(f'<meta name="twitter:title" content="{attr(meta["title"])}">')
    tags.append(f'<meta name="twitter:description" content="{attr(meta["desc"])}">')
    if ogi:
        tags.append(f'<meta name="twitter:image" content="{ogi}">')
    tags.append('<meta name="theme-color" content="#013079">')
    tags.append('<meta name="color-scheme" content="light dark">')
    tags.append(f'<link rel="manifest" href="{A("manifest.webmanifest")}">')
    tags.append(f'<link rel="icon" href="{A("assets/icons/favicon.ico")}" sizes="48x48">')
    tags.append(f'<link rel="icon" href="{A("assets/icons/favicon.svg")}" type="image/svg+xml" sizes="any">')
    tags.append(f'<link rel="icon" href="{A("assets/icons/favicon-32.png")}" type="image/png" sizes="32x32">')
    tags.append(f'<link rel="icon" href="{A("assets/icons/favicon-16.png")}" type="image/png" sizes="16x16">')
    tags.append(f'<link rel="apple-touch-icon" href="{A("assets/icons/apple-touch-icon.png")}">')
    co = " crossorigin" if FONT_PRELOAD_CROSSORIGIN else ""
    tags.append(f'<link rel="preload" href="{A("assets/fonts/Alegreya-normal-400-latin.woff2")}" as="font" type="font/woff2"{co}>')
    tags.append(f'<link rel="preload" href="{A("assets/fonts/EncodeSans-normal-400-latin.woff2")}" as="font" type="font/woff2"{co}>')
    lcp = meta.get("lcp")
    if lcp:
        mm = man_entry(lcp)
        if mm:
            files = img_files(mm)
            ss = ", ".join(f"{A(f)} {w}w" for w, f in files)
            tags.append(f'<link rel="preload" as="image" imagesrcset="{ss}" imagesizes="{meta.get("lcp_sizes", "100vw")}" fetchpriority="high">')
    css_href = A("assets/css/app.min.css")
    if CRITICAL_CSS:   # inline critical CSS, load the full stylesheet without blocking first paint
        tags.append(f'<style id="critical-css">{CRITICAL_CSS}</style>')
        tags.append(f'<link rel="stylesheet" href="{css_href}" media="print" onload="this.media=&quot;all&quot;">')
        tags.append(f'<noscript><link rel="stylesheet" href="{css_href}"></noscript>')
    else:
        tags.append(f'<link rel="stylesheet" href="{css_href}">')
    tags.append('<script>(function(d){try{var t=localStorage.getItem("agp-theme");d.setAttribute("data-theme",t==="dark"?"dark":"light")}catch(e){d.setAttribute("data-theme","light")}d.classList.add("js")})(document.documentElement)</script>')
    # Turbo Drive: page swaps without a full reload, prefetch on hover. Pages stay plain HTML, so crawlers and no-JS visitors are unaffected.
    tags.append(f'<script src="{A("assets/js/vendor/turbo.min.js")}" defer></script>')
    tags.append('<meta name="turbo-prefetch" content="true">')
    tags.append('<meta name="view-transition" content="same-origin">')
    for ld in meta.get("jsonld", []):
        tags.append('<script type="application/ld+json">' + json.dumps(ld, ensure_ascii=False, separators=(",", ":")) + "</script>")
    return "\n".join(tags)


def org_ld():
    return {"@context": "https://schema.org", "@type": "Organization", "@id": f"{SITE_URL}/#org", "name": "Africa Global Press",
            "url": f"{SITE_URL}/", "logo": f"{SITE_URL}/assets/img/site/logo-420.webp",
            "email": CONTACT_EMAIL2, "telephone": "+33758802527", "sameAs": list(SOCIAL.values()),
            "contactPoint": {"@type": "ContactPoint", "contactType": "editorial", "email": CONTACT_EMAIL2, "availableLanguage": ["fr", "en"]}}


def website_ld():
    lang = CTX["lang"]
    sp = static_path("search", lang)
    return {"@context": "https://schema.org", "@type": "WebSite", "@id": f"{SITE_URL}/#website", "url": f"{SITE_URL}/{lang}/index.html",
            "name": "Africa Global Press", "inLanguage": lang, "publisher": {"@id": f"{SITE_URL}/#org"},
            "potentialAction": {"@type": "SearchAction", "target": f"{SITE_URL}/{sp}?q={{search_term_string}}", "query-input": "required name=search_term_string"}}


def topbar(meta_alts):
    u = U()
    lang = CTX["lang"]
    other = "en" if lang == "fr" else "fr"
    d = datetime.date.today()
    cats = "".join(f'<li><a href="{L(k)}">{esc(lab)}</a></li>' for k, lab in
                   [("society", cat_label("society")), ("politics", cat_label("politics")), ("finance", cat_label("finance")),
                    ("culture", cat_label("culture")), ("science", cat_label("science"))])
    sw_fr = f'<a href="{rel(meta_alts["fr"])}" hreflang="fr" lang="fr"' + (' aria-current="true"' if lang == "fr" else "") + ' data-lang="fr">' + ("Français" if lang == "fr" else "French") + "</a>"
    sw_en = f'<a href="{rel(meta_alts["en"])}" hreflang="en" lang="en"' + (' aria-current="true"' if lang == "en" else "") + ' data-lang="en">' + ("Anglais" if lang == "fr" else "English") + "</a>"
    return (f'<div class="topbar"><div class="container-page topbar-in">'
            f'<p class="topbar-date" data-date data-lang="{lang}">{esc(fmt_date(d, weekday=True))}</p>'
            f'<nav aria-label="{attr(u["topbar_nav"])}"><ul class="topbar-cats">{cats}</ul></nav>'
            f'<div class="topbar-right"><ul class="lang-switch" aria-label="Language"><li>{sw_fr}</li><li>{sw_en}</li></ul>'
            f'<button type="button" class="topbar-search" data-open="search" aria-haspopup="dialog" aria-label="{attr(u["overlay_search"])}">{icon("search")}<span>{esc(u["search_open"])}</span></button>'
            f'<button type="button" class="topbar-login" data-open="login" aria-haspopup="dialog" aria-label="{attr(u["login"])}">{icon("user")}<span>{esc(u["login"])}</span></button></div>'
            f'</div></div>')


def brandband():
    u = U()
    return (f'<div class="brandband"><div class="container-page brandband-in"><div class="brandband-text">'
            f'<p class="brandband-tagline">{esc(u["tagline"])}</p><p class="brandband-sub">{esc(u["tagline_sub"])}</p>'
            f'<a class="btn btn-flash btn-sm" href="{L("online-press")}">{esc(u["read_paper"])}{icon("arrow-r", "i i-sm")}</a></div>'
            f'<a class="brandband-logo" href="{L("home")}" aria-label="Africa Global Press — {attr(u["home_crumb"])}">'
            f'{site_img("logo", "Africa Global Press", "Africa Global Press", "(min-width:768px) 150px, 112px", "logo-img", eager=True)}</a></div></div>')


def mega_card(a):
    return (f'<article class="mega-card"><a class="thumb thumb-land" href="{art_url(a)}" tabindex="-1" aria-hidden="true">'
            f'{img_html(a["img"], alt_for(a), a["title"], "220px")}</a><p class="mega-card-title"><a href="{art_url(a)}">{esc(a["title"])}</a></p>'
            f'<p class="meta">{minutes_label(a)}</p></article>')


def mainnav():
    u = U()
    lang = CTX["lang"]
    arts = ARTICLES[lang]
    latest5 = arts[:5]
    best3 = [resolve(lang, i) for i in ("4555", "4556", "4557")]
    latest4 = arts[:4]

    def item(idn, label, key, panel):
        return (f'<li class="nav-item" data-mega><a class="nav-link" href="{L(key)}">{esc(label)}</a>'
                f'<button type="button" class="nav-chev" aria-expanded="false" aria-controls="{idn}" aria-label="{attr(label)} — {attr(u["offcanvas_more"])}">{icon("chev")}</button>'
                f'<div class="mega" id="{idn}" hidden><div class="container-page mega-in">{panel}</div></div></li>')

    top_panel = (f'<div class="mega-head"><p class="mega-title">{esc(u["nav_top"])}</p><a class="mega-more" href="{L("online-press")}">{esc(u["show_more"])}{icon("angle-r", "i i-sm")}</a></div>'
                 f'<div class="mega-grid">{"".join(mega_card(a) for a in latest5)}</div>')
    pod_items = "".join(f'<li><a href="{L("podcasts")}#{e["id"]}">{icon("mic", "i i-sm")}<span><b>{esc(e["title"])}</b><small>{esc(e["show_name"])} · {e["minutes"]} {esc(u["pod_min"])}</small></span></a></li>' for e in pod_episodes(lang)[:4])
    pod_panel = (f'<div class="mega-head"><p class="mega-title">{esc(u["nav_podcasts"])}</p><a class="mega-more" href="{L("podcasts")}">{esc(u["show_more"])}{icon("angle-r", "i i-sm")}</a></div>'
                 f'<ul class="mega-pod-list">{pod_items}</ul>')
    vid_items = "".join(f'<li><a href="{L("videos")}#{v["id"]}"><span class="mega-vthumb">{img_html(v["art"]["img"], alt_for(v["art"]), v["title"], "96px")}</span>'
                        f'<span><b>{esc(v["title"])}</b><small>{esc(v["topic_name"])} · {v["clock"]}</small></span></a></li>' for v in vid_list(lang)[:4] if v["art"])
    vid_panel = (f'<div class="mega-head"><p class="mega-title">{esc(u["nav_videos"])}</p><a class="mega-more" href="{L("videos")}">{esc(u["show_more"])}{icon("angle-r", "i i-sm")}</a></div>'
                 + (f'<ul class="mega-vid-list">{vid_items}</ul>' if vid_items else ""))
    best = "".join(f'<li><a class="thumb thumb-sq" href="{art_url(a)}" tabindex="-1" aria-hidden="true">{img_html(a["img"], alt_for(a), a["title"], "96px")}</a>'
                   f'<div><a href="{art_url(a)}">{esc(a["title"])}</a><time datetime="{a["date"].date().isoformat()}">{fmt_date(a["date"])}</time></div></li>' for a in best3)
    last = "".join(f'<li><a href="{art_url(a)}">{esc(a["title"])}</a><time datetime="{a["date"].date().isoformat()}">{fmt_date(a["date"])}</time></li>' for a in latest4)
    press_panel = (f'<div class="mega-press"><div class="mega-press-intro"><p class="mega-title"><a href="{L("online-press")}">{esc(u["nav_press"])}</a></p>'
                   f'<p>{esc(u["press_blurb"])}</p><a class="mega-more" href="{L("online-press")}">{esc(u["show_more"])}{icon("angle-r", "i i-sm")}</a></div>'
                   f'<div><p class="mega-sub">{icon("fire", "i i-sm")}{esc(u["best_news"])}</p><ul class="mega-list mega-list-thumb">{best}</ul></div>'
                   f'<div><p class="mega-sub">{icon("check", "i i-sm")}{esc(u["latest_news_short"])}</p><ul class="mega-list">{last}</ul></div></div>')
    items = (item("mega-top", u["nav_top"], "online-press", top_panel) + item("mega-pod", u["nav_podcasts"], "podcasts", pod_panel) +
             item("mega-vid", u["nav_videos"], "videos", vid_panel) + item("mega-press", u["nav_press"], "online-press", press_panel))
    return (f'<div class="mainnav-wrap"><nav class="mainnav" id="site-navigation" aria-label="{attr(u["nav_label"])}"><div class="container-page mainnav-in">'
            f'<ul class="nav-list">{items}</ul>'
            f'<button type="button" class="btn btn-flash btn-explore" data-open="topics" aria-haspopup="dialog" aria-controls="topics-popup" aria-expanded="false">{icon("burger", "i i-sm")}<span>{esc(u["explore"])}</span></button>'
            f'<div class="nav-right"><button type="button" class="btn btn-ghost" data-open="newsletter" aria-haspopup="dialog">{esc(u["subscribe"])}</button>'
            f'{theme_switch()}</div>'
            f'<div class="nav-mobile"><a class="nav-mobile-logo" href="{L("home")}" aria-label="Africa Global Press">{site_img("logo", "Africa Global Press", "Africa Global Press", "96px", "logo-img")}</a>'
            f'<div class="nav-mobile-actions"><button type="button" class="icon-btn" data-open="search" aria-label="{attr(u["overlay_search"])}" aria-haspopup="dialog">{icon("search")}</button>'
            f'{theme_switch("m")}'
            f'<button type="button" class="icon-btn" data-open="offcanvas" aria-label="{attr(u["open_menu"])}" aria-haspopup="dialog" aria-controls="offcanvas">{icon("burger")}</button></div></div>'
            f'</div></nav></div>')


def theme_switch(suffix=""):
    u = U()
    return (f'<button type="button" class="theme-switch" role="switch" aria-checked="false" aria-label="{attr(u["dark_mode"])}" data-theme-toggle>'
            f'<span class="ts-track"><span class="ts-knob">{icon("sun", "i ts-sun")}{icon("moon", "i ts-moon")}</span></span></button>')


def ticker():
    u = U()
    lang = CTX["lang"]
    arts = ARTICLES[lang][:10]
    lis = "".join(f'<li class="ticker-item"><a href="{art_url(a)}">{esc(truncate(a["title"], 90))}</a></li>' for a in arts)
    return (f'<section class="ticker" aria-label="{attr(u["ticker"])}"><div class="container-page ticker-in">'
            f'<p class="ticker-label">{icon("fire", "i i-sm")}<span>{esc(u["ticker"])}</span></p>'
            f'<ul class="ticker-list" data-ticker>{lis}</ul>'
            f'<div class="ticker-ctl"><button type="button" class="icon-btn icon-btn-sm" data-ticker-prev aria-label="{attr(u["ticker_prev"])}">{icon("arrow-l")}</button>'
            f'<button type="button" class="icon-btn icon-btn-sm" data-ticker-pause aria-pressed="false" aria-label="{attr(u["ticker_pause"])}" data-l-pause="{attr(u["ticker_pause"])}" data-l-play="{attr(u["ticker_play"])}">{icon("pause")}</button>'
            f'<button type="button" class="icon-btn icon-btn-sm" data-ticker-next aria-label="{attr(u["ticker_next"])}">{icon("arrow-r")}</button></div></div></section>')


def footer():
    u = U()
    lang = CTX["lang"]
    cats = "".join(f'<li><a href="{L(k)}"{" aria-current=\"page\"" if page_path(k, lang) == CTX["cur"] else ""}>{esc(lab.upper())}</a></li>' for k, lab in
                   [("sports", cat_label("sports")), ("culture", cat_label("culture")), ("economy", cat_label("finance")),
                    ("politics", cat_label("politics")), ("science", cat_label("science"))])
    legal = "".join(f'<li><a href="{L(k)}"{" aria-current=\"page\"" if page_path(k, lang) == CTX["cur"] else ""}>{esc(lab)}</a></li>' for k, lab in u["legal_links"])
    alts = CTX["alts"]
    sw = (f'<ul class="lang-switch lang-switch-foot" aria-label="Language"><li><a href="{rel(alts["fr"])}" hreflang="fr" lang="fr" data-lang="fr"'
          + (' aria-current="true"' if lang == "fr" else "") + f'>{"Français" if lang == "fr" else "French"}</a></li><li><a href="{rel(alts["en"])}" hreflang="en" lang="en" data-lang="en"'
          + (' aria-current="true"' if lang == "en" else "") + f'>{"Anglais" if lang == "fr" else "English"}</a></li></ul>')
    return (f'<footer class="site-footer" id="footer"><div class="container-page footer-in">'
            f'<div class="footer-main"><h2 class="footer-title">{icon("angle-r", "i i-sm")}<span>{esc(u["footer_about"])}</span></h2>'
            f'<p class="footer-text">{u["footer_text"]}</p>'
            f'<a class="btn btn-flash btn-sm" href="{PHONE_HREF}">{esc(u["footer_call"])}{icon("arrow-r", "i i-sm")}</a>'
            f'<p class="footer-mail">{icon("mail", "i i-sm")}<span>{esc(u["footer_email"])} <a href="mailto:{CONTACT_EMAIL}">{CONTACT_EMAIL}</a> <span aria-hidden="true">/</span> <a href="mailto:{CONTACT_EMAIL2}">{CONTACT_EMAIL2}</a></span></p>'
            f'<nav aria-label="{attr(u["footer_cats"])}"><ul class="footer-cats">{cats}</ul></nav>'
            f'<p class="footer-copy">{esc(u["copyright"].format(y=NOW.year))}</p></div>'
            f'<div class="footer-side"><h2 class="footer-legal-title">{esc(u["footer_legal"])}</h2><nav aria-label="{attr(u["footer_legal"])}"><ul class="footer-legal">{legal}</ul></nav>'
            f'<div class="footer-bottom">{sw}<button type="button" class="link-btn" data-open="cookies">{esc(u["cookie_manage"])}</button></div></div>'
            f'</div>'
            f'<a class="back-top" href="#top" data-backtop aria-label="{attr(u["back_top"])}" hidden>{icon("up")}</a></footer>')


def offcanvas():
    u = U()
    lang = CTX["lang"]
    main = [("online-press", u["nav_press"]), ("magazine", cat_label("magazine")), ("radios", cat_label("radios")), ("television", cat_label("television")),
            ("podcasts", cat_label("podcasts")), ("videos", cat_label("videos"))]
    thematics = ["society", "politics", "finance", "economy", "culture", "sports", "science", "health"]
    m = "".join(f'<li><a href="{L(k)}">{esc(lab)}</a></li>' for k, lab in main)
    t = "".join(f'<li><a href="{L(k)}">{esc(cat_label(k))}</a></li>' for k in thematics)
    soc = "".join(f'<li><a href="{SOCIAL[k]}" rel="noopener noreferrer" target="_blank" aria-label="{attr(n)}">{icon(k, "i i-md")}</a></li>' for n, _, k in u["soc"])
    return (f'<div class="offcanvas" id="offcanvas" role="dialog" aria-modal="true" aria-label="{attr(u["menu"])}" hidden>'
            f'<div class="oc-backdrop" data-close></div><div class="oc-panel" tabindex="-1">'
            f'<div class="oc-head"><a href="{L("home")}" class="oc-logo" aria-label="Africa Global Press">{site_img("logo", "Africa Global Press", "Africa Global Press", "112px", "logo-img")}</a>'
            f'<button type="button" class="icon-btn" data-close aria-label="{attr(u["close"])}">{icon("close")}</button></div>'
            f'{search_form("search-form search-form-oc", idp="q-oc")}'
            f'<nav aria-label="{attr(u["menu"])}"><ul class="oc-menu">{m}</ul><p class="oc-sub">{esc(u["offcanvas_cats"])}</p><ul class="oc-menu oc-menu-cats">{t}</ul></nav>'
            f'<div class="oc-login"><p>{esc(u["offcanvas_login"])}</p><button type="button" class="btn btn-primary" data-open="login">{esc(u["login"])}</button></div>'
            f'<div class="oc-foot"><p class="oc-sub">{esc(u["follow"])}</p><ul class="oc-social">{soc}</ul><p class="oc-copy">{esc(u["copyright"].format(y=NOW.year))}</p></div>'
            f'</div></div>')


def topics_popup():
    """'Explore by topic' popup (Foxiz template popup, top position). Content is a <template> cloned by app.js on first open:
    one column per topic (heading + latest articles) and a strip of channels. Nothing here is in the DOM until the visitor asks."""
    u = U()
    lang = CTX["lang"]
    cols = []
    for key in ("politics", "economy", "society", "finance", "culture", "sports", "health", "science"):
        arts = in_cat(lang, key)[:4]
        lis = "".join(f'<li><a href="{art_url(a)}">{icon("link", "i i-sm")}<span>{esc(a["title"])}</span></a></li>' for a in arts)
        cols.append(f'<section class="tp-col" aria-labelledby="tp-{key}"><h3 class="tp-head" id="tp-{key}"><a href="{L(key)}" title="{attr(u["tp_all"])}">{icon("angle-r", "i i-sm")}{esc(cat_label(key))}</a></h3>'
                    + (f'<ul class="tp-list">{lis}</ul>' if lis else "") + '</section>')
    chans = "".join(f'<li><a class="chip chip-sm" href="{L(k)}">{esc(cat_label(k))}</a></li>' for k in ("podcasts", "videos", "magazine", "radios", "television"))
    return (f'<template id="tmpl-topics-popup" data-label="{attr(u["explore"])}" data-close="{attr(u["close"])}"><div class="popup-template-content"><div class="container-page tp-inner">'
            f'<div class="tp-grid">{"".join(cols)}</div>'
            f'<div class="tp-chan"><p class="tp-chan-label">{esc(u["tp_channels"])}</p><ul>{chans}</ul></div></div></div></template>')


def dialogs():
    u = U()
    lang = CTX["lang"]
    search = (f'<div class="modal modal-search" id="search-overlay" role="dialog" aria-modal="true" aria-label="{attr(u["overlay_search"])}" hidden>'
              f'<div class="modal-backdrop" data-close></div><div class="modal-card" tabindex="-1"><button type="button" class="icon-btn modal-x" data-close aria-label="{attr(u["close"])}">{icon("close")}</button>'
              f'<p class="modal-title">{esc(u["overlay_search"])}</p>{search_form("search-form search-form-lg", idp="q-overlay")}'
              f'<ul class="tags"><li class="tags-lead">{esc(u["in_news"])}</li>' + "".join(f'<li><a href="{L("search")}?q={urlq(q)}">{esc(lab)}</a></li>' for lab, q in u["tags"]) + '</ul></div></div>')
    opts = "".join(f'<label class="check"><input type="checkbox" name="topics" value="{v}"{" checked" if v == "daily" else ""}><span>{esc(l)}</span></label>' for v, l in u["nl_opts"])
    nl = (f'<div class="modal" id="newsletter-modal" role="dialog" aria-modal="true" aria-labelledby="nl-t" hidden>'
          f'<div class="modal-backdrop" data-close></div><div class="modal-card" tabindex="-1"><button type="button" class="icon-btn modal-x" data-close aria-label="{attr(u["close"])}">{icon("close")}</button>'
          f'<div class="modal-logo">{site_img("logo", "Africa Global Press", "Africa Global Press", "112px", "logo-img")}</div>'
          f'<h2 class="modal-title" id="nl-t">{esc(u["nl_title"])}</h2><p>{esc(u["nl_text"])}</p>'
          f'<form class="form" data-newsletter novalidate><div class="field"><label for="nl-email">{esc(u["nl_email"])} <span aria-hidden="true">*</span></label>'
          f'<input id="nl-email" name="email" type="email" required autocomplete="email" aria-describedby="nl-email-err"><p class="field-err" id="nl-email-err" role="alert" hidden>{esc(u["nl_err"])}</p></div>'
          f'<fieldset class="checks"><legend class="sr-only">Newsletter</legend>{opts}</fieldset>'
          f'<input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">'
          f'<button type="submit" class="btn btn-primary">{esc(u["nl_submit"])}</button>'
          f'<p class="form-ok" role="status" hidden>{esc(u["nl_ok"])}</p></form></div></div>')
    login = (f'<div class="modal" id="login-modal" role="dialog" aria-modal="true" aria-labelledby="lg-t" hidden>'
             f'<div class="modal-backdrop" data-close></div><div class="modal-card" tabindex="-1"><button type="button" class="icon-btn modal-x" data-close aria-label="{attr(u["close"])}">{icon("close")}</button>'
             f'<div class="modal-logo">{site_img("logo", "Africa Global Press", "Africa Global Press", "112px", "logo-img")}</div>'
             f'<h2 class="modal-title" id="lg-t">{esc(u["login_title"])}</h2><p>{esc(u["offcanvas_login"])}</p>'
             f'<form class="form" data-login novalidate><div class="field"><label for="lg-user">{esc(u["login_user"])}</label><input id="lg-user" name="log" type="text" required autocomplete="username"></div>'
             f'<div class="field"><label for="lg-pass">{esc(u["login_pass"])}</label><div class="pw"><input id="lg-pass" name="pwd" type="password" required autocomplete="current-password" spellcheck="false">'
             f'<button type="button" class="icon-btn icon-btn-sm" data-pw aria-label="{attr(u["login_show"])}" aria-pressed="false">{icon("eye")}</button></div></div>'
             f'<label class="check"><input type="checkbox" name="rememberme"><span>{esc(u["login_remember"])}</span></label>'
             f'<p class="form-err" role="alert" hidden>{esc(u["login_err"])}</p>'
             f'<button type="submit" class="btn btn-primary">{esc(u["login_submit"])}</button>'
             f'<p class="form-note" role="status" hidden>{esc(u["login_demo"])}</p>'
             f'<a class="lost" href="{L("contact")}">{esc(u["login_lost"])}</a></form></div></div>')
    cookie = (f'<aside class="cookie" id="cookie-banner" role="dialog" aria-modal="false" aria-labelledby="ck-t" hidden>'
              f'<div class="cookie-in"><div class="cookie-text"><h2 id="ck-t">{esc(u["cookie_title"])}</h2>'
              f'<p>{esc(u["cookie_text"])} <a href="{L("privacy")}">{esc(u["cookie_privacy"])}</a> {esc(u["cookie_and"])} <a href="{L("cookies")}">{esc(u["cookie_cookies"])}</a>.</p></div>'
              f'<div class="cookie-actions"><button type="button" class="btn btn-primary" data-consent="all">{esc(u["cookie_accept"])}</button>'
              f'<button type="button" class="btn btn-outline" data-consent="none">{esc(u["cookie_reject"])}</button>'
              f'<button type="button" class="btn btn-link" data-consent-custom aria-expanded="false" aria-controls="ck-prefs">{esc(u["cookie_custom"])}</button></div></div>'
              f'<form class="cookie-prefs" id="ck-prefs" hidden><fieldset><legend class="sr-only">{esc(u["cookie_custom"])}</legend>'
              f'<label class="check"><input type="checkbox" checked disabled><span><b>{esc(u["cookie_ess"])}</b><small>{esc(u["cookie_ess_d"])}</small></span></label>'
              f'<label class="check"><input type="checkbox" name="analytics"><span><b>{esc(u["cookie_ana"])}</b><small>{esc(u["cookie_ana_d"])}</small></span></label>'
              f'<label class="check"><input type="checkbox" name="social"><span><b>{esc(u["cookie_soc"])}</b><small>{esc(u["cookie_soc_d"])}</small></span></label></fieldset>'
              f'<button type="submit" class="btn btn-primary btn-sm">{esc(u["cookie_save"])}</button></form></aside>')
    # third-party scripts stay inert (type=text/plain) until the matching consent category is granted by app.js
    gated = ('<script type="text/plain" data-consent-cat="analytics" data-src=""></script>'
             '<script type="text/plain" data-consent-cat="social" data-src=""></script>')
    return search + nl + login + cookie + gated + topics_popup()


def layout(main, meta, body_class=""):
    lang = CTX["lang"]
    u = U()
    alts = CTX["alts"]
    meta = dict(meta)
    meta["alternates"] = alts
    lds = [website_ld(), org_ld()] + meta.get("jsonld", [])
    meta["jsonld"] = lds
    js = f'<script src="{A("assets/js/app.js")}" defer></script>'
    return (f'<!doctype html>\n<html lang="{lang}" data-theme="light">\n<head>\n{head(meta)}\n</head>\n'
            f'<body class="{body_class}" id="top" data-lang="{lang}" data-home="{L("home")}" data-sw="{A("sw.js")}">\n{SPRITE}\n'
            f'<a class="skip-link" href="#main">{esc(u["skip"])}</a>\n'
            f'<header class="site-header" id="site-header">{topbar(alts)}{brandband()}{mainnav()}</header>\n'
            f'{meta.get("after_header", "")}'
            f'<main id="main" tabindex="-1">\n{main}\n</main>\n{footer()}\n{offcanvas()}\n{dialogs()}\n{js}\n</body>\n</html>\n')


# ----------------------------------------------------------------------------------------------
# Article body cleaning
# ----------------------------------------------------------------------------------------------
LOCAL_PAGE_PATTERNS = [
    (r"politique-en-matiere-de-cookies|cookie-policy", "cookies"),
    (r"politique-de-confidentialite|privacy-policy", "privacy"),
    (r"mentions-legales|legal-notices|conditions-generales|terms-of-use", "legal"),
    (r"qui-sommes-nous|who-are-we", "about"),
    (r"/contact|contactus", "contact"),
]


def map_href(href, lang):
    if not href:
        return None
    if href.startswith("mailto:") or href.startswith("tel:"):
        return href
    if re.match(r"https?://", href) or href.startswith("/"):
        m = re.search(r"/(?:\d{4}/\d{2}/\d{2}/)?([a-z0-9-]+)/?$", href.split("?")[0].split("#")[0])
        if m:
            a = ART_BY_DBSLUG[lang].get(m.group(1))
            if a:
                return art_url(a)
        for pat, key in LOCAL_PAGE_PATTERNS:
            if re.search(pat, href):
                return L(key)
        return None
    return href


def upload_key(src):
    m = re.search(r"/wp-content/uploads/(\d{4}/\d{2}/[^?\"]+)", src or "")
    if not m:
        return None
    p = m.group(1)
    cands = [p, re.sub(r"-\d+x\d+(\.\w+)$", r"\1", p)]
    for c in cands:
        if c in MAN:
            return c
    base = re.sub(r"\.\w+$", "", cands[1])
    for ext in (".jpg", ".jpeg", ".png", ".webp"):
        if base + ext in MAN:
            return base + ext
    return None


def sanitize_text(s):
    s = re.sub(r"[\u00a0\u202f\u2009\u2007]+", " ", s)
    s = s.replace("&nbsp;", " ")
    s = re.sub(r"\(\s*https?://(?:www\.)?pressafrica\.media/?[^)\s]*\s*\)", "", s)
    s = re.sub(r"https?://(?:www\.)?pressafrica\.media/?[^\s\"<)]*", "", s)
    return s


def clean_body(raw, a):
    lang = a["lang"] if a else CTX["lang"]
    soup = BeautifulSoup(re.sub(r"<!--.*?-->", "", raw, flags=re.S), "html.parser")
    for t in soup(["script", "style", "svg", "iframe", "form", "noscript", "button"]):
        t.decompose()
    for h in soup.find_all(["h1", "h2", "h3", "h4", "h5", "h6"]):
        txt = h.get_text(" ", strip=True).replace("\xa0", " ")
        if not txt:
            h.decompose()
            continue
        if len(txt) > 100 or txt.endswith((".", ";", ":", "!", "…")) or re.match(r"^\d+[ᵉʳer.]", txt) or re.search(r"PRESS AF", txt):
            h.name = "p"
    for p in soup.find_all("p"):
        kids = [c for c in p.children if str(c).strip()]
        txt = p.get_text(" ", strip=True)
        if len(kids) == 1 and getattr(kids[0], "name", None) in ("strong", "b") and 2 < len(txt) < 70 and not txt.endswith((".", ";", ",", ":")) \
                and not re.match(r"^(Source|Par|By|Rédacteur)", txt):
            p.name = "h2"
            p.string = txt
    seen_h2 = False
    for h in soup.find_all(["h1", "h2", "h3", "h4", "h5", "h6"]):
        h.name = "h2" if (h.name in ("h1", "h2") or not seen_h2) else "h3"
        seen_h2 = seen_h2 or h.name == "h2"
        h.attrs = {}
        h.string = h.get_text(" ", strip=True)
    # images
    for fig in soup.find_all("figure"):
        im = fig.find("img")
        key = upload_key(im.get("src")) if im else None
        if not key or (a and key == a["img"]):
            fig.decompose()
            continue
        cap = fig.find("figcaption")
        caption = cap.get_text(" ", strip=True) if cap else ""
        alt = (im.get("alt") or "").strip() or (caption or (a["title"] if a else ""))
        new = soup.new_tag("figure", attrs={"class": "article-fig"})
        new.append(BeautifulSoup(img_html(key, alt, alt, "(min-width:1024px) 760px, 100vw"), "html.parser"))
        if caption:
            fc = soup.new_tag("figcaption")
            fc.string = caption
            new.append(fc)
        fig.replace_with(new)
    for im in soup.find_all("img"):
        if not im.find_parent("figure"):
            key = upload_key(im.get("src"))
            if key:
                new = soup.new_tag("figure", attrs={"class": "article-fig"})
                new.append(BeautifulSoup(img_html(key, im.get("alt") or (a["title"] if a else ""), None, "(min-width:1024px) 760px, 100vw"), "html.parser"))
                im.replace_with(new)
            else:
                im.decompose()
    # links
    for aa in soup.find_all("a"):
        href = map_href(aa.get("href", ""), lang)
        if href:
            aa.attrs = {"href": href}
        else:
            aa.unwrap()
    keep = {"a": ["href"], "img": ["src", "srcset", "sizes", "width", "height", "alt", "title", "loading", "decoding", "style", "class", "fetchpriority"],
            "figure": ["class"], "td": ["colspan"], "th": ["colspan"]}
    for t in soup.find_all(True):
        if t.name in ("img",):
            continue
        t.attrs = {k: v for k, v in t.attrs.items() if k in keep.get(t.name, [])}
    for p in soup.find_all(["p", "li", "h2", "h3", "strong", "em"]):
        if not p.get_text(strip=True) and not p.find("img"):
            p.decompose()
    out = str(soup)
    out = sanitize_text(out)
    out = re.sub(r"(<br\s*/?>\s*){2,}", "<br/>", out)
    return out.strip()


EN_FIX = {
    "3. Cookies et Technologies Similaires": "3. Cookies and Similar Technologies",
    "Africa Global Press s'engage à rendre son site web accessible à tous les utilisateurs, y compris aux personnes handicapées. Nous nous efforçons de respecter les normes d'accessibilité web.":
        "Africa Global Press is committed to making its website accessible to all users, including people with disabilities. We strive to comply with web accessibility standards.",
}


def clean_static(raw, lang):
    """Cleans an Elementor 'page' (legal pages): h2(title) -> H1 handled by caller; returns (title, intro_html, sections_html, toc)"""
    soup = BeautifulSoup(re.sub(r"<!--.*?-->", "", raw, flags=re.S), "html.parser")
    for t in soup(["script", "style", "svg", "iframe", "form", "noscript", "button", "img", "figure"]):
        t.decompose()
    title = None
    for h in soup.find_all(["h1", "h2"]):
        title = h.get_text(" ", strip=True)
        h.decompose()
        break
    for h in soup.find_all(["h3", "h4", "h5", "h6"]):
        h.name = "h2" if h.name == "h3" else "h3"
        h.attrs = {}
        h.string = h.get_text(" ", strip=True)
    for aa in soup.find_all("a"):
        href = map_href(aa.get("href", ""), lang)
        txt = aa.get_text(" ", strip=True)
        if href:
            aa.attrs = {"href": href}
        else:
            aa.unwrap()
    lab = {"cookies": ("Politique relative aux cookies", "Cookie policy"), "privacy": ("Politique de confidentialité", "Privacy policy"),
           "legal": ("Mentions légales", "Legal notices")}
    for aa in soup.find_all("a"):
        if not aa.get_text(strip=True) and aa.get("href"):
            for k, (fr_l, en_l) in lab.items():
                if aa["href"] == L(k):
                    aa.string = fr_l if lang == "fr" else en_l
                    break
            else:
                aa.unwrap()
    keep = {"a": ["href"]}
    for t in soup.find_all(True):
        t.attrs = {k: v for k, v in t.attrs.items() if k in keep.get(t.name, [])}
    # unwrap generic containers
    for t in soup.find_all(["div", "section", "span"]):
        t.unwrap()
    for p in soup.find_all(["p", "li", "h2", "h3", "strong", "em"]):
        if not p.get_text(strip=True):
            p.decompose()
    h = str(soup)
    h = sanitize_text(h)
    if lang == "en":
        for fr_s, en_s in EN_FIX.items():
            h = h.replace(fr_s, en_s)
    h = re.sub(r"\s*\n\s*", "\n", h)
    toc = []
    cnt = [0]

    def idrep(m):
        cnt[0] += 1
        t = re.sub(r"<[^>]+>", "", m.group(1))
        sid = f"s{cnt[0]}-" + slugify(t)[:30]
        toc.append((sid, re.sub(r"^\d+\.\s*", "", t)))
        return f'<h2 id="{sid}">{m.group(1)}</h2>'
    h = re.sub(r"<h2>(.*?)</h2>", idrep, h, flags=re.S)
    return title, h, toc


# ----------------------------------------------------------------------------------------------
# Page builders
# ----------------------------------------------------------------------------------------------
PAGES_OUT = []       # (path, html)
SITEMAP = []         # (key, {lang: path})


def alt_map(key, a=None):
    if a is not None:
        o = alt_article(a)
        m = {a["lang"]: a["path"]}
        if o:
            m[o["lang"]] = o["path"]
        else:
            m["en" if a["lang"] == "fr" else "fr"] = f"{'en' if a['lang'] == 'fr' else 'fr'}/index.html"
        return m
    return {lg: page_path(key, lg) for lg in LANGS}


def begin(lang, path, alts):
    CTX["lang"] = lang
    CTX["cur"] = path
    CTX["alts"] = alts


def emit(html_str):
    # the editorial e-mail keeps working but the domain is written as an HTML entity (keeps the legacy host out of the source)
    html_str = html_str.replace("@pressafrica.media", "@pressafrica&#46;media")
    PAGES_OUT.append((CTX["cur"], html_str))


def meta_for(key, **kw):
    lang = CTX["lang"]
    m = dict(title=SEO_TITLES[lang][key], desc=DESCRIPTIONS[lang][key], og_image=og_image(None))
    m.update(kw)
    return m


def crumbs(key, extra=None):
    """list of (label, path) for breadcrumb + JSON-LD (paths relative to site root, None for current)"""
    lang = CTX["lang"]
    c = [(U()["home_crumb"], f"{lang}/index.html")]
    if key == "home":
        return [(U()["home_crumb"], None)]
    if key in CATS:
        if key != "online-press" and CATS[key]["layout"] in ("archive",) and key != "online-press":
            c.append((cat_label("online-press"), cat_path("online-press", lang)))
        c.append((cat_label(key), None))
        return c
    if key in STATIC_PATHS:
        lab = {"about": U()["about_h1"], "contact": "Contact", "legal": SEO_TITLES[lang]["legal"].split(" | ")[0],
               "privacy": SEO_TITLES[lang]["privacy"].split(" | ")[0], "cookies": SEO_TITLES[lang]["cookies"].split(" | ")[0],
               "search": U()["sr_title"]}[key]
        c.append((lab, None))
    return c


def crumb_html_ld(items):
    html_items = [(lab, rel(p) if p else None) for lab, p in items]
    return breadcrumb(html_items), breadcrumb_ld(items)


def sr_h1(text):
    return f'<h1 class="sr-only">{esc(text)}</h1>'


# ---- home -----------------------------------------------------------------------------------
LATEST_IDS = ["4596", "4586", "4267", "4538", "4281", "4299"]
ECON_BIG = ["4585", "4557"]
ECON_SMALL = ["4637", "4632", "4631", "4630"]
HERO_IDS = ["3740", "3482", "3473"]


def hero_block(lang):
    u = U()
    slides = []
    intro_img = "2025/12/Malabo.jpg"
    arts = [resolve(lang, i) for i in HERO_IDS]
    total = 4

    def slide(n, imgpath, alt, title, text, by, href, cta, eager):
        im = img_html(imgpath, alt, title, "(min-width:1280px) 1240px, 100vw", "hero-img", eager=eager, low=not eager)
        sr_title = f'<span class="sr-only"> : {esc(title)}</span>' if cta == u["read_more"] else ""
        by_html = f'<p class="hero-by">{esc(u["by"])} <b>{esc(by)}</b></p>' if by else ""
        return (f'<div class="hero-slide{" is-active" if n == 1 else ""}" role="group" aria-roledescription="slide" aria-label="{esc(u["slide"])} {n} / {total}" data-slide>'
                f'{im}<span class="hero-ov" aria-hidden="true"></span><div class="hero-body"><h2 class="hero-title">{esc(title)}</h2>'
                f'<p class="hero-text">{esc(text)}</p>{by_html}<a class="btn btn-outline-w" href="{href}">{esc(cta)}{sr_title}</a></div></div>')

    lead = arts[1]
    slides.append(slide(1, lead["img"], alt_for(lead), lead["title"], lead["excerpt"], lead["author"], art_url(lead), u["read_more"], True))
    slides.append(slide(2, intro_img, "Panorama urbain d'une capitale africaine" if lang == "fr" else "Urban skyline of an African capital",
                        u["hero_intro_title"], u["hero_intro_text"], "", L("online-press"), u["hero_intro_cta"], False))
    for i, a in enumerate([arts[0], arts[2]], 3):
        exc = a["excerpt"] if (a["translated"] or a.get("tr")) else truncate(first_excerpt(a["body"]), 190)
        exc = re.sub(r"^[^—–]{3,60}[—–]\s*PRESS AFRICA\s*[—–]\s*", "", exc) if False else exc
        slides.append(slide(i, a["img"], alt_for(a), a["title"], a["excerpt"], a["author"], art_url(a), u["read_more"], False))
    dots = "".join(f'<button type="button" class="hero-dot" data-dot="{i}" aria-label="{esc(u["slide_go"])} {i + 1}"{" aria-current=\"true\"" if i == 0 else ""}></button>' for i in range(total))
    return (f'<section class="hero container-page" aria-roledescription="carousel" aria-label="{attr(u["hero_label"])}" data-hero>'
            f'<div class="hero-viewport" aria-live="off">{"".join(slides)}</div>'
            f'<button type="button" class="hero-nav hero-prev" data-prev aria-label="{attr(u["slide_prev"])}">{icon("angle-l")}</button>'
            f'<button type="button" class="hero-nav hero-next" data-next aria-label="{attr(u["slide_next"])}">{icon("angle-r")}</button>'
            f'<div class="hero-ctl"><div class="hero-dots">{dots}</div>'
            f'<button type="button" class="hero-pause" data-pause aria-pressed="false" aria-label="{attr(u["slide_pause"])}" data-l-pause="{attr(u["slide_pause"])}" data-l-play="{attr(u["slide_play"])}">{icon("pause")}</button></div></section>')


def build_home(lang):
    path = f"{lang}/index.html"
    begin(lang, path, alt_map("home"))
    u = U()
    latest = [resolve(lang, i) for i in LATEST_IDS]
    econ_big = [resolve(lang, i) for i in ECON_BIG]
    econ_small = [resolve(lang, i) for i in ECON_SMALL]
    used = {a["id"] for a in econ_big + econ_small}
    rest = [a for a in in_cat(lang, "economy") + in_cat(lang, "finance") if a["id"] not in used]
    seen = set()
    rest = [a for a in sorted(rest, key=lambda a: (a["date"], int(a["id"])), reverse=True) if not (a["id"] in seen or seen.add(a["id"]))]
    pages = [(econ_big, econ_small)]
    for i in range(0, len(rest), 6):
        chunk = rest[i:i + 6]
        pages.append((chunk[:2], chunk[2:]))
    econ_html = []
    for n, (big, small) in enumerate(pages):
        hid = "" if n == 0 else " hidden"
        econ_html.append(f'<div class="econ-page" data-econ-page="{n}"{hid}><div class="grid-econ-big">{"".join(card_econ_lg(a) for a in big)}</div>'
                         f'<div class="grid-econ-small">{"".join(card_econ(a) for a in small)}</div></div>')
    # --- sections inspired by the Foxiz demo: most read, per-topic blocks, newsletter band ---
    shown = {a["id"] for a in latest + econ_big + econ_small}
    pool = sorted((a for a in ARTICLES[lang] if a["id"] not in shown), key=lambda a: (a["date"], int(a["id"])), reverse=True)
    ranked = "".join(f'<li class="rank-item reveal"><span class="rank-n" aria-hidden="true">{n}</span>{card_text(a, "h3")}</li>'
                     for n, a in enumerate(pool[:4], 1))
    topic_html = []
    for key, col in (("society", "blue"), ("politics", "red"), ("culture", "blue")):
        items = [a for a in in_cat(lang, key) if a["id"] not in shown][:4]
        if len(items) < 2:
            continue
        shown |= {a["id"] for a in items}
        topic_html.append(f'<section class="block" aria-labelledby="sec-{key}"><div class="sec-row">{sec_title(cat_label(key), col, id_="sec-" + key)}'
                          f'<a class="sec-link" href="{L(key)}">{esc(u["see_all"])}{icon("arrow-r", "i i-sm")}</a></div>'
                          f'<div class="grid-econ-small">{"".join(card_econ(a) for a in items)}</div></section>')
    extra = (f'<section class="block" aria-labelledby="sec-read">{sec_title(u["most_read"], "red", id_="sec-read")}<ol class="rank-list">{ranked}</ol></section>'
             f'{"".join(topic_html)}<div class="home-nl">{newsletter_card()}</div>')
    main = (f'{sr_h1(SEO_TITLES[lang]["home"])}{hero_block(lang)}'
            f'<div class="container-page"><section class="block" aria-labelledby="sec-latest">{sec_title(u["latest"], "blue", id_="sec-latest")}'
            f'<div class="grid-latest"><div class="gl-big">{card_lg(latest[0], eager=False)}</div><div class="gl-md">{card_md(latest[1])}{card_md(latest[2])}</div></div>'
            f'<div class="grid-text">{"".join(card_text(a) for a in latest[3:])}</div></section>'
            f'<section class="block" aria-labelledby="sec-econ">{sec_title(u["economic"], "red", id_="sec-econ")}'
            f'<div class="econ" data-econ>{"".join(econ_html)}'
            f'<div class="pager" role="navigation" aria-label="{attr(u["pages"])}"><button type="button" class="pager-btn" data-econ-prev disabled>{icon("angle-l", "i i-sm")}{esc(u["prev"])}</button>'
            f'<button type="button" class="pager-btn pager-next" data-econ-next>{esc(u["next"])}{icon("angle-r", "i i-sm")}</button></div></div></section>'
            f'{extra}{search_block("q-home")}{WAVE}{editorial_block()}{tiles_block()}</div>')
    heroimg = resolve(lang, HERO_IDS[1])["img"]
    ld = [breadcrumb_ld([(u["home_crumb"], None)])]
    meta = meta_for("home", jsonld=ld, after_header=ticker(), lcp=heroimg, lcp_sizes="(min-width:1280px) 1240px, 100vw", og_image=og_image(heroimg))
    emit(layout(main, meta, "page-home"))
    SITEMAP.append(("home", alt_map("home")))


# ---- category pages -------------------------------------------------------------------------
def cat_faq(key):
    lang = CTX["lang"]
    lab = cat_label(key)
    lab_l = lab.lower() if lang == "fr" else lab
    return [(q.format(c=lab_l), a.format(c=lab_l)) for q, a in U()["cat_faq_q"]]


def build_category(lang, key):
    c = CATS[key]
    path = cat_path(key, lang)
    begin(lang, path, alt_map(key))
    u = U()
    arts = in_cat(lang, key)
    lay = c["layout"]
    items = crumbs(key)
    bc_html, bc_ld = crumb_html_ld(items)
    faq = cat_faq(key)
    jsonld = [bc_ld, faq_jsonld(faq)] + ([podcasts_jsonld(lang)] if key == 'podcasts' and pod_episodes(lang) else []) + (videos_jsonld(lang) if key == 'videos' else []) + ([magazine_jsonld(lang)] if key == 'magazine' and mag_issues(lang) else []) + ([directory_jsonld(lang, key)] if key in ('radios', 'television') and dir_list(lang, key) else [])
    label = cat_label(key)
    top_img = arts[0]["img"] if arts else None
    if lay == "archive":
        body = archive_body(lang, key, arts, bc_html, label)
    elif lay == "thematic":
        body = thematic_body(lang, key, arts, bc_html)
    else:
        body = landing_body(lang, key, arts, bc_html)
    main = body + f'<div class="container-page">{faq_block(faq)}</div>'
    meta = meta_for(key, jsonld=jsonld, og_image=og_image(top_img))
    emit(layout(main, meta, f"page-cat page-{lay}"))
    SITEMAP.append((key, alt_map(key)))


def archive_body(lang, key, arts, bc_html, label):
    u = U()
    if not arts:
        return (f'<div class="container-page">{bc_html}<h1 class="page-title">{esc(label)}</h1><p class="empty">{esc(u["empty"])}</p></div>')
    feat = arts[0]
    side = arts[1] if len(arts) > 1 else None
    rest = arts[2:] if side else []
    feat_html = (f'<article class="feat reveal"><a class="thumb thumb-feat" href="{art_url(feat)}" tabindex="-1" aria-hidden="true">{img_html(feat["img"], alt_for(feat), feat["title"], "(min-width:1024px) 560px, 100vw", eager=True)}</a>'
                 f'<div class="feat-body"><h2 class="feat-title"><a href="{art_url(feat)}">{esc(feat["title"])}</a></h2>'
                 f'<p class="excerpt">{esc(feat["excerpt"])}</p>{meta_date(feat)}</div></article>')
    side_html = ""
    if side:
        side_html = (f'<article class="feat-side reveal"><time datetime="{side["date"].date().isoformat()}">{fmt_date(side["date"])}</time>'
                     f'<h2 class="feat-side-title"><a href="{art_url(side)}">{esc(side["title"])}</a></h2>'
                     f'<a class="thumb thumb-land" href="{art_url(side)}" tabindex="-1" aria-hidden="true">{img_html(side["img"], alt_for(side), side["title"], "(min-width:1024px) 340px, 100vw")}</a></article>')
    per = 6
    pages = [rest[i:i + per] for i in range(0, len(rest), per)] or [[]]

    def li(a):
        return (f'<article class="row-item reveal"><a class="thumb thumb-row" href="{art_url(a)}" tabindex="-1" aria-hidden="true">{img_html(a["img"], alt_for(a), a["title"], "(min-width:768px) 220px, 40vw")}</a>'
                f'<div><time datetime="{a["date"].date().isoformat()}">{fmt_date(a["date"])}</time>'
                f'<h3 class="row-title"><a href="{art_url(a)}">{esc(a["title"])}</a></h3><p class="excerpt">{esc(a["excerpt"])}</p>{chips(a)}</div></article>')
    panels = "".join(f'<div class="list-page" data-page="{n}">{"".join(li(a) for a in pg)}</div>' for n, pg in enumerate(pages))
    pager = ""
    if len(pages) > 1:
        nums = "".join(f'<li><button type="button" class="pg" data-goto="{n}" aria-label="{attr(u["page_of"])} {n + 1}"{" aria-current=\"page\"" if n == 0 else ""}>{n + 1}</button></li>' for n in range(len(pages)))
        pager = (f'<nav class="pagination" aria-label="{attr(u["pages"])}"><ul>{nums}<li><button type="button" class="pg pg-next" data-next-page aria-label="{attr(u["next_page"])}">{icon("angle-r", "i i-sm")}</button></li></ul></nav>')
    return (f'<div class="container-page">{bc_html}<h1 class="page-title">{esc(label)}</h1>'
            f'<div class="feat-grid">{feat_html}{side_html}</div>{WAVE}'
            f'<div class="list-grid"><section aria-labelledby="lp-t"><h2 class="sr-only" id="lp-t">{esc(u["latest_pubs"])}</h2><div data-paged>{panels}</div>{pager}</section>{newsletter_card()}</div></div>')


def thematic_body(lang, key, arts, bc_html):
    u = U()
    c = CATS[key]
    heading = c["h_" + lang]
    if not arts:
        return (f'<div class="container-page">{bc_html}<h1 class="page-title">{esc(heading)}</h1><p class="empty">{esc(u["empty"])}</p></div>')
    strip = "".join(card_mini(a) for a in arts[:6])
    feat = arts[0]
    first = 9
    grid_items = []
    for i, a in enumerate(arts):
        hid = ' hidden data-more' if i >= first else ""
        grid_items.append(f'<div class="grid-cell"{hid}>{card_grid(a)}</div>')
    more = ""
    if len(arts) > first:
        more = f'<div class="more-wrap"><button type="button" class="btn btn-outline" data-loadmore>{esc(u["show_more"])}</button></div>'
    end = f'<p class="end-list"{" hidden" if len(arts) > first else ""} data-end>{icon("info", "i i-sm")}{esc(u["end_list"])}</p>'
    return (f'<div class="container-page thematic">{bc_html}<div class="strip">{strip}</div>'
            f'<p class="watermark" aria-hidden="true" data-t="Africa Global Press"></p><h1 class="thematic-title">{esc(heading)}</h1>'
            f'<article class="feat-center reveal"><a class="thumb thumb-fill" href="{art_url(feat)}" tabindex="-1" aria-hidden="true">{img_html(feat["img"], alt_for(feat), feat["title"], "(min-width:1024px) 520px, 100vw", eager=True)}</a>'
            f'<div class="card-ov">{chips(feat)}<h2 class="card-title"><a href="{art_url(feat)}">{esc(feat["title"])}</a></h2><p class="excerpt">{esc(feat["excerpt"])}</p>{meta_date(feat, True)}</div></article>'
            f'<p class="watermark" aria-hidden="true" data-t="Africa Global Press"></p><section aria-labelledby="lp-t"><h2 class="thematic-sub" id="lp-t">{esc(u["latest_pubs"])}</h2>'
            f'<div class="grid-3" data-loadmore-grid>{"".join(grid_items)}</div>{more}{end}</section></div>')


# ---- podcasts -------------------------------------------------------------------------------
_POD_FILE = Path(__file__).resolve().parents[1] / "prototype_html" / "data" / "podcasts.json"
PODCASTS = json.loads(_POD_FILE.read_text(encoding="utf-8")) if _POD_FILE.exists() else {"shows": [], "episodes": []}


def pod_episodes(lang):
    """Published episodes, newest first, with localized text, show name and the linked article (if any)."""
    shows = {s["id"]: s[lang] for s in PODCASTS["shows"]}
    out = []
    for e in sorted((x for x in PODCASTS["episodes"] if x.get("status") == "published"), key=lambda x: x["date"], reverse=True):
        try:
            art = resolve(lang, e["article"]) if e.get("article") else None
        except KeyError:
            art = None
        out.append(dict(e, audio=e["audio"].replace("{lang}", lang), title=e[lang]["title"], desc=e[lang]["desc"], show_id=e["show"], show_name=shows.get(e["show"], {}).get("title", ""),
                        art=art, minutes=max(1, round(e["duration"] / 60)), dt=datetime.datetime.fromisoformat(e["date"])))
    return out


def pod_player(e):
    """Native audio element: nothing is downloaded until play (preload=none); labelled for screen readers."""
    return (f'<audio class="pod-audio" controls preload="none" src="{A(e["audio"])}" aria-label="{attr(U()["pod_audio"] + e["title"])}">'
            f'<a href="{A(e["audio"])}">{esc(e["title"])}</a></audio>')


def pod_card(e):
    u = U()
    cover = (f'<a class="pod-cover" href="{art_url(e["art"])}" tabindex="-1" aria-hidden="true">{img_html(e["art"]["img"], alt_for(e["art"]), e["title"], "120px")}</a>'
             if e["art"] else "")
    read = f'<a class="pod-read" href="{art_url(e["art"])}">{esc(u["pod_read"])}{icon("arrow-r", "i i-sm")}</a>' if e["art"] else ""
    return (f'<article class="pod-ep reveal" id="{e["id"]}" data-show="{e["show_id"]}">{cover}<div class="pod-main">'
            f'<p class="meta"><span class="chip chip-sm">{esc(e["show_name"])}</span><time datetime="{e["date"]}">{fmt_date(e["dt"])}</time>'
            f'<span class="meta-sep" aria-hidden="true"></span><span>{e["minutes"]} {esc(u["pod_min"])}</span></p>'
            f'<h3 class="card-title">{esc(e["title"])}</h3><p class="excerpt">{esc(e["desc"])}</p>{pod_player(e)}{read}</div></article>')


def podcasts_body(lang, bc_html):
    """Podcasts page: latest episode with player, filterable episode list, shows, newsletter."""
    u = U()
    eps = pod_episodes(lang)
    top = eps[0]
    shows = PODCASTS["shows"]
    show_cards = "".join(f'<li class="pod-show"><h3>{esc(s[lang]["title"])}</h3><p>{esc(s[lang]["desc"])}</p></li>' for s in shows)
    flt = (f'<div class="pod-filter" role="group" aria-label="{attr(u["pod_shows"])}"><button type="button" class="chip-btn is-on" data-pod-filter="all" aria-pressed="true">{esc(u["pod_all"])}</button>'
           + "".join(f'<button type="button" class="chip-btn" data-pod-filter="{s["id"]}" aria-pressed="false">{esc(s[lang]["title"])}</button>' for s in shows) + '</div>')
    hero = (f'<section class="pod-hero reveal" id="{top["id"]}" aria-labelledby="pod-top"><p class="pod-kicker">{icon("mic", "i i-sm")}{esc(u["pod_latest"])}</p>'
            f'<h2 id="pod-top">{esc(top["title"])}</h2><p class="meta"><span>{esc(top["show_name"])}</span><span class="meta-sep" aria-hidden="true"></span>'
            f'<time datetime="{top["date"]}">{fmt_date(top["dt"])}</time><span class="meta-sep" aria-hidden="true"></span><span>{top["minutes"]} {esc(u["pod_min"])}</span></p>'
            f'<p>{esc(top["desc"])}</p>{pod_player(top)}</section>')
    return (f'<div class="container-page">{bc_html}<h1 class="page-title">{esc(cat_label("podcasts"))}</h1><p class="pod-lead">{esc(u["pod_about"])}</p>{hero}'
            f'<section class="block" aria-labelledby="pod-eps">{sec_title(u["pod_episodes"], "blue", id_="pod-eps")}{flt}'
            f'<div class="pod-list" data-pod-list>{"".join(pod_card(e) for e in eps[1:])}</div></section>'
            f'<section class="block" aria-labelledby="pod-sh">{sec_title(u["pod_shows"], "red", id_="pod-sh")}<ul class="pod-shows">{show_cards}</ul></section>'
            f'<div class="home-nl">{newsletter_card()}</div></div>')


def podcasts_jsonld(lang):
    base = f"{SITE_URL}/{cat_path('podcasts', lang)}"
    return {"@context": "https://schema.org", "@type": "PodcastSeries", "name": "Africa Global Press - " + cat_label("podcasts"),
            "inLanguage": lang, "url": base, "publisher": {"@id": f"{SITE_URL}/#org"},
            "episode": [{"@type": "PodcastEpisode", "name": e["title"], "description": e["desc"], "datePublished": e["date"],
                         "timeRequired": f"PT{e['minutes']}M", "url": f"{base}#{e['id']}",
                         "associatedMedia": {"@type": "AudioObject", "contentUrl": f"{SITE_URL}/{e['audio']}", "encodingFormat": "audio/mpeg"}}
                        for e in pod_episodes(lang)]}


# ---- videos ---------------------------------------------------------------------------------
_VID_FILE = Path(__file__).resolve().parents[1] / "prototype_html" / "data" / "videos.json"
VIDEOS = json.loads(_VID_FILE.read_text(encoding="utf-8")) if _VID_FILE.exists() else {"topics": [], "videos": []}


def vid_list(lang):
    """Published videos, newest first, with localized text, topic label and the linked article."""
    topics = {t["id"]: t[lang] for t in VIDEOS["topics"]}
    out = []
    for v in sorted((x for x in VIDEOS["videos"] if x.get("status") == "published" and x.get("file")), key=lambda x: x["date"], reverse=True):
        try:
            art = resolve(lang, v["article"]) if v.get("article") else None
        except KeyError:
            art = None
        d = int(v["duration"])
        out.append(dict(v, title=v[lang]["title"], desc=v[lang]["desc"], topic_name=topics.get(v["topic"], ""), art=art,
                        dt=datetime.datetime.fromisoformat(v["date"]), clock=f"{d // 60}:{d % 60:02d}"))
    return out


def vid_meta_text(v):
    return f'{v["topic_name"]} · {fmt_date(v["dt"])} · {v["clock"]}'


def vid_card(v):
    u = U()
    read = art_url(v["art"]) if v["art"] else "#"
    thumb = img_html(v["art"]["img"], alt_for(v["art"]), v["title"], "(min-width:1024px) 400px, 100vw") if v["art"] else ""
    return (f'<article class="vid-card reveal" id="{v["id"]}" data-topic="{v["topic"]}">'
            f'<a class="vid-thumb" href="{read}" data-vid-play data-src="{A(v["file"])}" data-poster="{A(v["poster"])}" data-title="{attr(v["title"])}" data-desc="{attr(v["desc"])}" '
            f'data-meta="{attr(vid_meta_text(v))}" data-read="{read}" aria-label="{attr(u["vid_play"] + v["title"])}">'
            f'{thumb}<span class="vid-play" aria-hidden="true">{icon("play", "i i-lg")}</span><span class="vid-dur">{v["clock"]}</span></a>'
            f'<p class="meta"><span class="chip chip-sm">{esc(v["topic_name"])}</span><time datetime="{v["date"]}">{fmt_date(v["dt"])}</time></p>'
            f'<h3 class="card-title">{esc(v["title"])}</h3><p class="excerpt">{esc(v["desc"])}</p></article>')


def videos_body(lang, bc_html):
    """Videos page: latest video in a player, filterable grid (a card loads its video into the player), newsletter."""
    u = U()
    vids = vid_list(lang)
    top = vids[0]
    read = (f'<a class="pod-read" id="vid-read" href="{art_url(top["art"])}">{esc(u["vid_read"])}{icon("arrow-r", "i i-sm")}</a>' if top["art"] else '<a class="pod-read" id="vid-read" href="#" hidden></a>')
    hero = (f'<section class="vid-hero reveal" id="vid-top" aria-labelledby="vid-title"><div class="vid-stage">'
            f'<video class="vid-player" id="vid-player" controls preload="none" playsinline width="1280" height="720" poster="{A(top["poster"])}" src="{A(top["file"])}" aria-label="{attr(u["vid_play"] + top["title"])}">'
            f'<a href="{A(top["file"])}">{esc(u["vid_fallback"])}</a></video></div>'
            f'<div class="vid-info"><p class="pod-kicker">{icon("play", "i i-sm")}{esc(u["vid_latest"])}</p><h2 id="vid-title">{esc(top["title"])}</h2>'
            f'<p class="meta" id="vid-meta">{esc(vid_meta_text(top))}</p><p id="vid-desc">{esc(top["desc"])}</p>{read}</div></section>')
    used = [t for t in VIDEOS["topics"] if any(v["topic"] == t["id"] for v in vids)]
    flt = (f'<div class="pod-filter" role="group" aria-label="{attr(u["vid_topics"])}"><button type="button" class="chip-btn is-on" data-vid-filter="all" aria-pressed="true">{esc(u["pod_all"])}</button>'
           + "".join(f'<button type="button" class="chip-btn" data-vid-filter="{t["id"]}" aria-pressed="false">{esc(t[lang])}</button>' for t in used) + '</div>')
    return (f'<div class="container-page">{bc_html}<h1 class="page-title">{esc(cat_label("videos"))}</h1><p class="pod-lead">{esc(u["vid_about"])}</p>{hero}'
            f'<section class="block" aria-labelledby="vid-all">{sec_title(u["vid_all"], "blue", id_="vid-all")}{flt}'
            f'<div class="vid-grid" data-vid-list>{"".join(vid_card(v) for v in vids)}</div></section>'
            f'<div class="home-nl">{newsletter_card()}</div></div>')


def videos_jsonld(lang):
    out = []
    for v in vid_list(lang):
        thumb = og_image(v["art"]["img"]) if v["art"] else None
        out.append({"@context": "https://schema.org", "@type": "VideoObject", "name": v["title"], "description": v["desc"], "inLanguage": lang,
                    "thumbnailUrl": [thumb or f"{SITE_URL}/{v['poster']}"], "uploadDate": v["date"], "duration": f"PT{int(v['duration'])}S",
                    "contentUrl": f"{SITE_URL}/{v['file']}", "publisher": {"@id": f"{SITE_URL}/#org"}})
    return out


# ---- magazine, radios, television ------------------------------------------------------------
def _data_json(name, default):
    f = Path(__file__).resolve().parents[1] / "prototype_html" / "data" / name
    return json.loads(f.read_text(encoding="utf-8")) if f.exists() else default


MAGAZINE = _data_json("magazine.json", {"issues": []})
RADIOS = _data_json("radios.json", {"stations": []})
TV = _data_json("tv.json", {"channels": []})
COUNTRIES = {
    "FR": {"fr": "France", "en": "France"}, "GB": {"fr": "Royaume-Uni", "en": "United Kingdom"}, "CD": {"fr": "RD Congo", "en": "DR Congo"},
    "US": {"fr": "États-Unis", "en": "United States"}, "DE": {"fr": "Allemagne", "en": "Germany"}, "NG": {"fr": "Nigeria", "en": "Nigeria"},
    "CG": {"fr": "Congo", "en": "Congo"}, "QA": {"fr": "Qatar", "en": "Qatar"}, "CN": {"fr": "Chine", "en": "China"},
    "ZA": {"fr": "Afrique du Sud", "en": "South Africa"}, "CI": {"fr": "Côte d'Ivoire", "en": "Côte d'Ivoire"},
}
DIR_LANGS = {"fr": {"fr": "Français", "en": "Anglais", "fr,en": "Français, anglais"}, "en": {"fr": "French", "en": "English", "fr,en": "French, English"}}
DIR_GENRES = {"fr": {"news": "Actualités", "public": "Service public", "culture": "Culture"}, "en": {"news": "News", "public": "Public service", "culture": "Culture"}}


def mag_issues(lang):
    """Published issues, newest first, with localized text, month label and the resolved cover/contents articles."""
    out = []
    for i in sorted((x for x in MAGAZINE["issues"] if x.get("status") == "published"), key=lambda x: x["date"], reverse=True):
        arts = []
        for fid in i.get("articles", []):
            try:
                arts.append(resolve(lang, fid))
            except KeyError:
                pass
        try:
            cover = resolve(lang, i["cover_article"]) if i.get("cover_article") else None
        except KeyError:
            cover = None
        cover = cover or (arts[0] if arts else None)
        dt = datetime.datetime.fromisoformat(i["date"])
        out.append(dict(i, title=i[lang]["title"], desc=i[lang]["desc"], arts=arts, cover=cover, dt=dt, month=f"{MONTHS[lang][dt.month - 1]} {dt.year}"))
    return out


def mag_cover(i, sizes):
    """Cover = the cover story's photo under a masthead, issue number and cover line (CSS/HTML, no extra image)."""
    u = U()
    photo = img_html(i["cover"]["img"], alt_for(i["cover"]), i["title"], sizes) if i["cover"] else ""
    return (f'<figure class="mag-cover" role="group" aria-label="{attr(u["mag_cover"].format(n=i["number"]))}">{photo}<span class="mag-shade" aria-hidden="true"></span>'
            f'<figcaption class="mag-over"><span class="mag-mast">{esc(u["mag_masthead"])}</span>'
            f'<span class="mag-foot"><span class="mag-no">{esc(u["mag_issue"])} {i["number"]} · {esc(i["month"])}</span><span class="mag-line">{esc(i["title"])}</span></span></figcaption></figure>')


def mag_contents(i, cls="mag-toc"):
    return f'<ol class="{cls}">' + "".join(f'<li><a href="{art_url(a)}">{esc(a["title"])}</a></li>' for a in i["arts"]) + '</ol>'


def magazine_body(lang, bc_html):
    """Magazine page: latest issue (cover + contents), archive grid with expandable contents, newsletter."""
    u = U()
    issues = mag_issues(lang)
    top = issues[0]
    hero = (f'<section class="mag-hero reveal" id="{top["id"]}" aria-labelledby="mag-top">{mag_cover(top, "(min-width:1024px) 400px, 70vw")}'
            f'<div class="mag-info"><p class="pod-kicker">{icon("fire", "i i-sm")}{esc(u["mag_latest"])}</p><h2 id="mag-top">{esc(top["title"])}</h2>'
            f'<p class="meta"><span>{esc(u["mag_issue"])} {top["number"]}</span><span class="meta-sep" aria-hidden="true"></span><time datetime="{top["date"]}">{esc(top["month"])}</time></p>'
            f'<p>{esc(top["desc"])}</p><h3 class="mag-sub">{esc(u["mag_contents"])}</h3>{mag_contents(top)}</div></section>')
    cards = "".join(
        f'<article class="mag-card reveal" id="{i["id"]}">{mag_cover(i, "(min-width:1024px) 300px, 50vw")}'
        f'<p class="meta"><span>{esc(u["mag_issue"])} {i["number"]}</span><span class="meta-sep" aria-hidden="true"></span><time datetime="{i["date"]}">{esc(i["month"])}</time></p>'
        f'<h3 class="card-title">{esc(i["title"])}</h3><p class="excerpt">{esc(i["desc"])}</p>'
        f'<details class="mag-more"><summary>{esc(u["mag_summary"])}</summary>{mag_contents(i)}</details></article>' for i in issues[1:])
    arch = (f'<section class="block" aria-labelledby="mag-all">{sec_title(u["mag_archive"], "blue", id_="mag-all")}<div class="mag-grid">{cards}</div></section>' if cards else "")
    return (f'<div class="container-page">{bc_html}<h1 class="page-title">{esc(cat_label("magazine"))}</h1><p class="pod-lead">{esc(u["mag_about"])}</p>{hero}{arch}'
            f'<div class="home-nl">{newsletter_card()}</div></div>')


def magazine_jsonld(lang):
    base = f"{SITE_URL}/{cat_path('magazine', lang)}"
    return {"@context": "https://schema.org", "@type": "Periodical", "name": "Africa Global Press Magazine", "inLanguage": lang, "url": base,
            "publisher": {"@id": f"{SITE_URL}/#org"},
            "hasPart": [{"@type": "PublicationIssue", "issueNumber": str(i["number"]), "name": i["title"], "description": i["desc"], "datePublished": i["date"], "url": f"{base}#{i['id']}"}
                        for i in mag_issues(lang)]}


def dir_list(lang, key):
    """Published directory entries (radio stations or TV channels) with localized text and labels."""
    rows = RADIOS["stations"] if key == "radios" else TV["channels"]
    out = []
    for x in rows:
        if x.get("status") != "published":
            continue
        out.append(dict(x, desc=x[lang]["desc"], country_name=COUNTRIES.get(x["country"], {}).get(lang, x["country"]),
                        lang_name=DIR_LANGS[lang].get(x["language"], x["language"]), genre_name=DIR_GENRES[lang].get(x["genre"], x["genre"])))
    return out


def dir_card(x):
    u = U()
    site = (f'<a class="btn btn-outline btn-sm dir-link" href="{attr(x["website"])}" target="_blank" rel="noopener noreferrer nofollow">{esc(u["dir_site"])}{icon("arrow-r", "i i-sm")}'
            f'<span class="sr-only"> · {esc(x["name"])}</span></a>' if x.get("website") else "")
    return (f'<article class="dir-card reveal" id="{x["id"]}" data-country="{x["country"]}"><p class="meta"><span class="chip chip-sm">{esc(x["genre_name"])}</span>'
            f'<span>{esc(x["country_name"])}</span><span class="meta-sep" aria-hidden="true"></span><span>{esc(x["lang_name"])}</span></p>'
            f'<h3 class="card-title">{esc(x["name"])}</h3><p class="excerpt">{esc(x["desc"])}</p>{site}</article>')


def dir_replay(v):
    u = U()
    thumb = img_html(v["art"]["img"], alt_for(v["art"]), v["title"], "(min-width:1024px) 280px, 50vw") if v["art"] else ""
    return (f'<article class="vid-card reveal"><a class="vid-thumb" href="{L("videos")}#{v["id"]}" aria-label="{attr(u["vid_play"] + v["title"])}">{thumb}'
            f'<span class="vid-play" aria-hidden="true">{icon("play", "i i-lg")}</span><span class="vid-dur">{v["clock"]}</span></a>'
            f'<h3 class="card-title">{esc(v["title"])}</h3></article>')


def directory_body(lang, bc_html, key):
    """Radios / television: lead, country filter, entry cards, then a cross-link (podcasts) or the latest video replays."""
    u = U()
    items = dir_list(lang, key)
    seen, chips = set(), []
    for x in items:
        if x["country"] not in seen:
            seen.add(x["country"])
            chips.append((x["country"], x["country_name"]))
    flt = (f'<div class="pod-filter" role="group" aria-label="{attr(u["dir_country"])}"><button type="button" class="chip-btn is-on" data-dir-filter="all" aria-pressed="true">{esc(u["pod_all"])}</button>'
           + "".join(f'<button type="button" class="chip-btn" data-dir-filter="{c}" aria-pressed="false">{esc(n)}</button>' for c, n in chips) + '</div>')
    radio = key == "radios"
    about, head = (u["rad_about"], u["rad_list"]) if radio else (u["tv_about"], u["tv_list"])
    if radio:
        extra = (f'<section class="dir-cross reveal"><p>{esc(u["rad_cross"])}</p><a class="btn btn-primary" href="{L("podcasts")}">{esc(u["rad_cross_cta"])}{icon("arrow-r", "i i-sm")}</a></section>')
    else:
        reps = [v for v in vid_list(lang)[:4]]
        extra = (f'<section class="block" aria-labelledby="tv-rep">{sec_title(u["tv_replays"], "red", id_="tv-rep")}<div class="rep-grid">{"".join(dir_replay(v) for v in reps)}</div>'
                 f'<p class="dir-cross-line">{esc(u["tv_cross"])} <a href="{L("videos")}">{esc(u["tv_replays_cta"])}</a></p></section>') if reps else ""
    return (f'<div class="container-page">{bc_html}<h1 class="page-title">{esc(cat_label(key))}</h1><p class="pod-lead">{esc(about)}</p>'
            f'<section class="block" aria-labelledby="dir-h">{sec_title(head, "blue", id_="dir-h")}{flt}'
            f'<div class="dir-grid" data-dir-list>{"".join(dir_card(x) for x in items)}</div><p class="dir-note">{esc(u["dir_note"])}</p></section>{extra}'
            f'<div class="home-nl">{newsletter_card()}</div></div>')


def directory_jsonld(lang, key):
    kind = "RadioChannel" if key == "radios" else "TelevisionChannel"
    els = []
    for n, x in enumerate(dir_list(lang, key), 1):
        item = {"@type": kind, "name": x["name"], "description": x["desc"], "inLanguage": x["language"].split(",")}
        if x.get("website"):
            item["url"] = x["website"]
        els.append({"@type": "ListItem", "position": n, "item": item})
    return {"@context": "https://schema.org", "@type": "ItemList", "name": cat_label(key), "inLanguage": lang, "itemListElement": els}


def landing_body(lang, key, arts, bc_html):
    if key == 'podcasts' and pod_episodes(lang):
        return podcasts_body(lang, bc_html)
    if key == 'videos' and vid_list(lang):
        return videos_body(lang, bc_html)
    if key == 'magazine' and mag_issues(lang):
        return magazine_body(lang, bc_html)
    if key in ('radios', 'television') and dir_list(lang, key):
        return directory_body(lang, bc_html, key)
    u = U()
    label = cat_label(key)
    latest = ARTICLES[lang][:3]
    return (f'<div class="container-page">{bc_html}<h1 class="page-title">{esc(label)}</h1>'
            f'<section class="soon reveal" aria-labelledby="soon-t">{icon("envelope", "soon-ico")}<div><h2 id="soon-t">{esc(u["landing_title"])}</h2><p>{esc(u["landing_text"])}</p>'
            f'<div class="soon-cta"><a class="btn btn-primary" href="{L("online-press")}">{esc(u["landing_cta"])}{icon("arrow-r", "i i-sm")}</a>{newsletter_btn("btn btn-flash")}</div></div></section>'
            f'<section class="block" aria-labelledby="soon-l">{sec_title(u["landing_latest"], "blue", id_="soon-l")}<div class="grid-3">{"".join(card_grid(a) for a in latest)}</div></section></div>')


def side_item(a):
    """Compact row for the article sidebar: small thumbnail + title (Foxiz 'Must Read' list)."""
    return (f'<article class="asd-item"><a class="asd-thumb" href="{art_url(a)}" tabindex="-1" aria-hidden="true">'
            f'{img_html(a["img"], alt_for(a), a["title"], "96px")}</a>'
            f'<h3 class="asd-title"><a href="{art_url(a)}">{esc(a["title"])}</a></h3></article>')


def article_sidebar(a, lang):
    """Right-hand panel: follow-us list, must-read stories, newsletter. Sticky on desktop."""
    u = U()
    pool = sorted((x for x in ARTICLES[lang] if x["id"] != a["id"]), key=lambda x: (x["date"], int(x["id"])), reverse=True)[:5]
    soc = "".join(f'<li><a href="{SOCIAL[k]}" rel="noopener noreferrer" target="_blank">{icon(k, "i i-md")}<b>{esc(n)}</b><span>{esc(v)}</span></a></li>'
                  for n, v, k in u["soc"])
    top = (f'<article class="asd-top">{thumb(pool[0], "300px", "thumb-land")}{title_link(pool[0], "h3")}{meta_read(pool[0])}</article>' if pool else "")
    rows = "".join(side_item(x) for x in pool[1:])
    return (f'<aside class="article-side sidebar-wrap single-sidebar has-border is-border-gray-dash" aria-label="{attr(u["must_read"])}"><div class="asd-inner sidebar-inner">'
            f'<section class="asd-block" aria-labelledby="sd-f">{sec_title(u["follow_us"], "blue", "h2", "sd-f")}<ul class="asd-soc">{soc}</ul></section>'
            f'<section class="asd-block" aria-labelledby="sd-m">{sec_title(u["must_read"], "red", "h2", "sd-m")}{top}{rows}</section>'
            f'<div class="asd-sticky">{newsletter_card()}{ad_slot()}</div></div></aside>')


# ---- article extras (Foxiz single-post blocks, restyled with the prototype tokens) ----------
def share_links(a, labels=False):
    """Share targets for the sticky/mobile bar and the 'Share this article' block (Foxiz .share-action)."""
    from urllib.parse import quote
    u = U()
    url = quote(f"{SITE_URL}/{a['path']}", safe="")
    ttl = quote(a["title"], safe="")
    nets = [("facebook", "Facebook", f"https://www.facebook.com/sharer.php?u={url}"),
            ("linkedin", "LinkedIn", f"https://www.linkedin.com/sharing/share-offsite/?url={url}"),
            ("x", "X", f"https://twitter.com/intent/tweet?text={ttl}&url={url}"),
            ("instagram", u["share_ig"], SOCIAL["instagram"]),   # Instagram has no web share intent: opens the profile
            ("mail", "E-mail", f"mailto:?subject={ttl}&body={url}")]
    out = []
    for k, name, href in nets:
        lab = u["share_mail"] if k == "mail" else (name if k == "instagram" else u["share_on"].format(name))
        ext = "" if k == "mail" else ' target="_blank" rel="nofollow noopener"'
        txt = f"<span>{esc(name)}</span>" if labels else ""
        out.append(f'<a class="share-action icon-{k}" href="{attr(href)}"{ext} aria-label="{attr(lab)}" title="{attr(lab)}">{icon(k, "i i-md")}{txt}</a>')
    cp = f"<span>{esc(u['share_copy'])}</span>" if labels else ""
    out.append(f'<button type="button" class="share-action icon-copy" data-copy-link data-icon-only data-ok="{attr(u["copied"])}" aria-label="{attr(u["share_copy"])}" title="{attr(u["share_copy"])}">{icon("link", "i i-md")}{cp}</button>')
    out.append(f'<button type="button" class="share-action native-share-trigger" data-native-share data-title="{attr(a["title"])}" hidden aria-label="{attr(u["share_more"])}" title="{attr(u["share_more"])}">{icon("more", "i i-md")}</button>')
    return "".join(out)


def share_bar(a):
    """Sticky column on desktop, fixed bottom bar on mobile (Foxiz l-shared-sec-outer.show-mobile)."""
    u = U()
    return (f'<div class="l-shared-sec-outer show-mobile"><div class="l-shared-sec" role="group" aria-label="{attr(u["share_rail"])}">'
            f'<div class="l-shared-header meta-text">{icon("share", "i i-md")}<span class="share-label">{esc(u["share"])}</span></div>'
            f'<div class="l-shared-items">{share_links(a)}</div></div></div>')


def share_box(a):
    u = U()
    return (f'<div class="e-shared-sec entry-sec"><div class="e-shared-header h4">{icon("share", "i i-md")}<span>{esc(u["share_article"])}</span></div>'
            f'<div class="rbbsl">{share_links(a, True)}</div></div>')


def newsletter_box():
    """In-article sign-up. Consent is explicit and required (GDPR); app.js stores the opt-in timestamp with the e-mail."""
    u = U()
    return (f'<aside class="newsletter-box entry-sec" aria-labelledby="nlb-t"><div class="newsletter-inner">'
            f'<div class="newsletter-featured" aria-hidden="true">{icon("envelope", "nlb-ico")}</div>'
            f'<div class="newsletter-content"><h2 class="newsletter-title" id="nlb-t">{esc(u["nlb_title"])}</h2><p class="newsletter-description">{esc(u["nlb_text"])}</p></div>'
            f'<div class="newsletter-form"><form class="form" data-newsletter novalidate>'
            f'<div class="field"><label class="sr-only" for="nlb-email">{esc(u["nlb_email"])}</label>'
            f'<input id="nlb-email" name="email" type="email" required autocomplete="email" placeholder="{attr(u["nlb_email"])}" aria-describedby="nlb-email-err">'
            f'<p class="field-err" id="nlb-email-err" role="alert" hidden>{esc(u["nl_err"])}</p></div>'
            f'<div class="agree-to-terms"><input id="nlb-consent" name="consent" type="checkbox" required aria-describedby="nlb-consent-err">'
            f'<label for="nlb-consent"><a href="{L("privacy")}">{esc(u["nlb_consent"])}</a></label></div>'
            f'<p class="field-err" id="nlb-consent-err" role="alert" hidden>{esc(u["nlb_consent_err"])}</p>'
            f'<input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">'
            f'<button type="submit" class="btn btn-primary">{esc(u["nlb_submit"])}</button>'
            f'<p class="form-ok" role="status" hidden>{esc(u["nl_ok"])}</p></form></div></div></aside>')


def author_box(a):
    u = U()
    name = a["author"] or "Africa Global Press"
    soc = "".join(f'<a href="{SOCIAL[k]}" rel="noopener nofollow" target="_blank" aria-label="{attr(n)}" title="{attr(n)}">{icon(k, "i i-md")}</a>'
                  for k, n in (("facebook", "Facebook"), ("x", "X"), ("instagram", "Instagram")))
    return (f'<section class="usr-holder entry-sec" aria-label="{attr(u["by"])} {attr(name)}"><div class="ubox"><div class="ubox-header">'
            f'<div class="author-info-wrap"><span class="avatar" aria-hidden="true">{esc(name[:1].upper())}</span>'
            f'<div class="is-meta"><div class="nname-info meta-author"><span class="meta-label">{u["by"]}</span><b class="nice-name">{esc(name)}</b></div>'
            f'<span class="author-job">{esc(u["au_role"])}</span></div></div>'
            f'<div class="usocials meta-text"><span class="ef-label">{esc(u["au_follow"])}</span>{soc}</div></div>'
            f'<p class="bio-description">{esc(u["au_bio"])}</p></div></section>')


def article_pagination(a, lang):
    """Previous (older) / next (newer) article of the same language."""
    u = U()
    seq = sorted(ARTICLES[lang], key=lambda x: (x["date"], int(x["id"])), reverse=True)
    i = next((n for n, x in enumerate(seq) if x["id"] == a["id"]), None)
    if i is None:
        return ""

    def side(x, cls, label, arrow_l):
        if not x:
            return f'<div class="nav-el {cls}" aria-hidden="true"></div>'
        arrow = icon("angle-l" if arrow_l else "angle-r", "i i-sm")
        lab = f'{arrow}<span>{esc(label)}</span>' if arrow_l else f'<span>{esc(label)}</span>{arrow}'
        return (f'<div class="nav-el {cls}"><a href="{art_url(x)}" rel="{"prev" if arrow_l else "next"}"><span class="nav-label is-meta">{lab}</span>'
                f'<span class="nav-inner">{img_html(x["img"], alt_for(x), x["title"], "72px", "e-pagi-img")}'
                f'<span class="e-pagi-title">{esc(x["title"])}</span></span></a></div>')
    older = seq[i + 1] if i + 1 < len(seq) else None
    newer = seq[i - 1] if i > 0 else None
    if not (older or newer):
        return ""
    return (f'<nav class="entry-pagination e-pagi entry-sec mobile-hide" aria-label="{attr(u["pg_prev"])} / {attr(u["pg_next"])}"><div class="inner">'
            f'{side(older, "nav-left", u["pg_prev"], True)}{side(newer, "nav-right", u["pg_next"], False)}</div></nav>')


def comment_box(a):
    """Reader comment form. Prototype only: app.js queues it in localStorage for moderation. Consent is required (GDPR)."""
    u = U()
    return (f'<section class="comment-box-wrap entry-sec" aria-labelledby="cm-t"><div class="comment-box-header">'
            f'<h2 class="h3" id="cm-t">{icon("comment", "i i-md")}{esc(u["cm_title"])}</h2></div>'
            f'<div class="comment-holder"><div id="respond" class="comment-respond">'
            f'<form class="comment-form form" data-comment data-post="{attr(a["id"])}" data-title="{attr(a["title"])}" novalidate>'
            f'<p class="comment-notes">{esc(u["cm_notes"])}</p>'
            f'<p class="comment-form-comment"><label for="cm-comment">{esc(u["cm_comment"])} <span class="required" aria-hidden="true">*</span></label>'
            f'<textarea id="cm-comment" name="comment" rows="6" maxlength="2000" required placeholder="{attr(u["cm_reply"])}"></textarea></p>'
            f'<p class="comment-form-author"><label for="cm-author">{esc(u["cm_name"])} <span class="required" aria-hidden="true">*</span></label>'
            f'<input id="cm-author" name="author" type="text" maxlength="80" required autocomplete="name"></p>'
            f'<p class="comment-form-email"><label for="cm-email">{esc(u["cm_email"])} <span class="required" aria-hidden="true">*</span></label>'
            f'<input id="cm-email" name="email" type="email" maxlength="100" required autocomplete="email"></p>'
            f'<p class="comment-form-consent"><input id="cm-consent" name="consent" type="checkbox" required> <label for="cm-consent">{esc(u["cm_consent"])}</label></p>'
            f'<input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">'
            f'<p class="form-err" role="alert" hidden>{esc(u["cm_err"])}</p>'
            f'<p class="form-submit"><button type="submit" class="btn btn-primary">{esc(u["cm_submit"])}</button></p>'
            f'<p class="form-ok" role="status" hidden>{esc(u["cm_ok"])}</p></form></div></div></section>')


def ad_slot(zone="sidebar_article", size="300x250"):
    """Placeholder for the admin 'ads' zone of the same key; no third-party code is ever executed in the prototype."""
    u = U()
    w, h_ = size.split("x")
    return (f'<div class="asd-ad" data-ad-zone="{zone}"><span class="ad-label">- {esc(u["ad_label"])} -</span>'
            f'<div class="ad-box" role="img" aria-label="{attr(u["ad_zone"])} {size}" style="aspect-ratio:{w}/{h_}"><span>{esc(u["ad_zone"])} · {size}</span></div></div>')


# ---- article --------------------------------------------------------------------------------
def build_article(a):
    lang = a["lang"]
    u = UI[lang]
    begin(lang, a["path"], alt_map(None, a))
    u = U()
    body = a["body"] if a.get("tr") else clean_body(a["body"], a)
    other = alt_article(a)
    primary = a["cats"][0] if a["cats"] else "online-press"
    cat_for_bc = next((k for k in a["cats"] if k != "online-press"), "online-press")
    items = [(u["home_crumb"], f"{lang}/index.html"), (cat_label(cat_for_bc), cat_path(cat_for_bc, lang)), (a["title"], None)]
    bc_html, bc_ld = crumb_html_ld(items)
    notice = ""
    body_lang = ""
    if a["translated"]:
        body_lang = ' lang="fr"'
        notice = (f'<p class="notice" role="note">{icon("info", "i i-sm")}<span>{esc(u["tr_notice"])} '
                  f'<a href="{rel(other["path"])}" hreflang="fr" lang="fr">{esc(u["tr_read_other"])}</a></span></p>' if other else "")
    elif other:
        notice = ""
    alt_link = ""
    if other and not a["translated"] and not other["translated"]:
        txt = U()["read_in_other"]
        alt_link = f'<p class="alt-version"><a href="{rel(other["path"])}" hreflang="{other["lang"]}" lang="{other["lang"]}">{esc(txt)}</a></p>'
    pub = a["date"].date().isoformat()
    heroimg = img_html(a["img"], (a["img_alt"] if a.get("tr") else (a["title"].replace("Économie", "Economy") if lang == "en" else (a["img_alt"] or alt_for(a)))), a["title"], "(min-width:1024px) 860px, 100vw", "article-hero-img", eager=True)
    related = [x for x in ARTICLES[lang] if x["id"] != a["id"] and set(x["cats"]) & set(a["cats"] if len(a["cats"]) > 1 else a["cats"])]
    related = [x for x in related if any(k != "online-press" and k in x["cats"] for k in a["cats"])] or related
    related = related[:3]
    rel_html = ""
    if related:
        rel_html = (f'<section class="block related" aria-labelledby="rel-t">{sec_title(u["related"], "blue", id_="rel-t")}'
                    f'<div class="grid-3">{"".join(card_grid(x) for x in related)}</div></section>')
    upd = f'<p class="byline-upd">{esc(u["updated"])} <time datetime="{a["modified"].date().isoformat()}">{fmt_date(a["modified"])}</time></p>'
    main = (f'<div class="read-progress" aria-hidden="true"><i data-read-progress></i></div>'
            f'<div class="container-page"><div class="article-layout"><div class="article-main">{bc_html}<article class="article">'
            f'<header class="article-head">{chips(a)}<h1 class="article-title">{esc(a["title"])}</h1>'
            f'<div class="byline"><span class="avatar" aria-hidden="true">{esc((a["author"] or "A")[:1].upper())}</span><div>'
            f'<p class="meta meta-lg"><span class="meta-by">{u["by"]} <b class="author">{esc(a["author"])}</b></span><span class="meta-sep" aria-hidden="true"></span>'
            f'<time datetime="{pub}">{fmt_date(a["date"])}</time><span class="meta-sep" aria-hidden="true"></span><span>{minutes_label(a)}</span></p>{upd}</div></div>{alt_link}{notice}</header>'
            f'<figure class="article-hero">{heroimg}</figure>'
            f'<div class="article-body">{share_bar(a)}<div class="prose"{body_lang}>{body}</div></div>{share_box(a)}{author_box(a)}{article_pagination(a, lang)}{newsletter_box()}</article></div>'
            f'{article_sidebar(a, lang)}</div>{rel_html}{comment_box(a)}</div>')
    og = og_image(a["img"])
    desc = truncate(a.get("seo_desc") or a["excerpt"], 158)
    ld = [bc_ld, {
        "@context": "https://schema.org", "@type": "NewsArticle", "headline": a["title"][:110], "description": desc,
        "image": [og] if og else [], "datePublished": a["date"].isoformat() + "+01:00", "dateModified": a["modified"].isoformat() + "+01:00",
        "inLanguage": lang, "author": {"@type": "Person", "name": a["author"]}, "publisher": {"@id": f"{SITE_URL}/#org"},
        "mainEntityOfPage": {"@type": "WebPage", "@id": f"{SITE_URL}/{a['path']}"}, "articleSection": [cat_label(k) for k in a["cats"]],
        "url": f"{SITE_URL}/{a['path']}"}]
    tt = a.get("seo_title") or a["title"]
    full_title = f"{tt} | Africa Global Press"
    if len(full_title) > 70:
        full_title = tt if (a.get("seo_title") and len(tt) <= 70) else f"{truncate(tt, 40)} | Africa Global Press"
    meta = dict(title=full_title, desc=desc, og_image=og, og_alt=((a["img_alt"] if a.get("tr") else a["title"].replace("Économie", "Economy")) if lang == "en" else (a["img_alt"] or a["title"])), og_type="article", article=a,
                jsonld=ld, lcp=a["img"], lcp_sizes="(min-width:1024px) 860px, 100vw")
    emit(layout(main, meta, "page-article"))
    SITEMAP.append(("art:" + a["id"] + lang, alt_map(None, a)))


# ---- static pages ---------------------------------------------------------------------------
def page_raw(slug_pred):
    for x in ARTICLES_RAW:
        if x["type"] == "page" and slug_pred(x):
            return x
    return None


def legal_source(key, lang):
    if lang == "fr":
        s = {"legal": "mentions-legales", "privacy": "politique-de-confidentialite", "cookies": "politique-en-matiere-de-cookies"}[key]
        return page_raw(lambda x: x["slug"] == s)
    # EN: DB slugs for privacy/cookie are swapped -> identify by first heading
    want = {"legal": "Legal Notices", "privacy": "Privacy Policy", "cookies": "Cookie Policy"}[key]
    for x in ARTICLES_RAW:
        if x["type"] == "page" and x["slug"] in ("legal-notices", "privacy-policy", "cookie-policy"):
            m = re.search(r"<h2[^>]*>(.*?)</h2>", x["content"], flags=re.S)
            if m and BeautifulSoup(m.group(1), "html.parser").get_text(strip=True).lower() == want.lower():
                return x
    return None


def build_legal(lang, key):
    path = static_path(key, lang)
    begin(lang, path, alt_map(key))
    u = U()
    src = legal_source(key, lang)
    title, h, toc = clean_static(src["content"], lang)
    names = {L("cookies"): ("Politique relative aux cookies", "Cookie policy"), L("privacy"): ("Politique de confidentialité", "Privacy policy"),
             L("legal"): ("Mentions légales", "Legal notices")}
    h = re.sub(r'<a href="([^"]+)"></a>', lambda m: f'<a href="{m.group(1)}">{names[m.group(1)][0 if lang == "fr" else 1]}</a>' if m.group(1) in names else "", h)
    title = {"fr": {"legal": "Mentions légales", "privacy": "Politique de confidentialité", "cookies": "Politique relative aux cookies"},
             "en": {"legal": "Legal Notices", "privacy": "Privacy Policy", "cookies": "Cookie Policy"}}[lang][key]
    items = crumbs(key)
    bc_html, bc_ld = crumb_html_ld(items)
    toc_html = ""
    if len(toc) > 3:
        toc_html = (f'<nav class="toc" aria-label="{attr(u["toc"])}"><p class="toc-title">{esc(u["toc"])}</p><ol>' +
                    "".join(f'<li><a href="#{i}">{esc(t)}</a></li>' for i, t in toc) + "</ol></nav>")
    upd = fmt_date(datetime.datetime.strptime(src["modified"], "%Y-%m-%d %H:%M:%S"))
    extra = f'<p class="cookie-reopen"><button type="button" class="btn btn-primary btn-sm" data-open="cookies">{esc(u["cookie_manage"])}</button></p>' if key == "cookies" else ""
    main = (f'<div class="container-page"><div class="legal">{bc_html}<h1 class="page-title">{esc(title)}</h1>'
            f'<p class="meta">{esc(u["legal_updated"])} : <time datetime="{src["modified"][:10]}">{upd}</time></p>{toc_html}'
            f'<div class="prose">{h}</div>{extra}</div></div>')
    meta = meta_for(key, jsonld=[bc_ld], og_image=og_image(None))
    emit(layout(main, meta, "page-legal"))
    SITEMAP.append((key, alt_map(key)))


def build_about(lang):
    path = static_path("about", lang)
    begin(lang, path, alt_map("about"))
    u = U()
    src = page_raw(lambda x: x["slug"] == ("qui-sommes-nous" if lang == "fr" else "who-are-we"))
    soup = BeautifulSoup(re.sub(r"<!--.*?-->", "", src["content"]), "html.parser")
    heads = [h for h in soup.find_all("h2")]
    mission_ul = None
    values_ul = None
    for h in heads:
        t = h.get_text(" ", strip=True)
        ul = h.find_next("ul")
        if t.startswith(("Notre Mission", "Our Mission")):
            mission_ul = ul
        if t.startswith(("Nos Valeurs", "Our Values")):
            values_ul = ul

    def li_list(ul):
        out = []
        for li in ul.find_all("li", recursive=False) if ul else []:
            txt = re.sub(r"\s+", " ", li.get_text(" ", strip=True)).replace(" ,", ",")
            m = re.match(r"^(.{3,34}?)\s?:\s(.*)$", txt)
            if m and len(m.group(1).split()) <= 5:
                out.append(f'<li>{icon("plus", "i i-sm li-ico")}<span><b>{esc(m.group(1))} :</b> {esc(m.group(2))}</span></li>')
            else:
                m2 = re.match(r"^((?:Défendre la liberté de presse|Fournir une opportunité|Defend press freedom|Provide an opportunity))\s?,?\s?(.*)$", txt)
                if m2 and m2.group(1).startswith(("Défendre", "Defend")):
                    out.append(f'<li>{icon("plus", "i i-sm li-ico")}<span><b>{esc(m2.group(1))}</b>, {esc(m2.group(2).lstrip(", "))}</span></li>')
                elif m2:
                    out.append(f'<li>{icon("plus", "i i-sm li-ico")}<span><b>{esc(m2.group(1))}</b> {esc(m2.group(2))}</span></li>')
                else:
                    out.append(f'<li>{icon("plus", "i i-sm li-ico")}<span>{esc(txt)}</span></li>')
        return "".join(out)
    faq = [(q, a) for q, a in u["about_faq_q"]]
    items = crumbs("about")
    bc_html, bc_ld = crumb_html_ld(items)
    main = (f'<div class="container-page about">{bc_html}<h1 class="page-title">{esc(u["about_h1"])}</h1><p class="about-lead">{esc(u["about_lead"])}</p>'
            f'{editorial_block()}'
            f'<div class="about-cols"><section class="reveal" aria-labelledby="ab-m"><h2 id="ab-m" class="about-h2">{esc(u["about_mission"])}</h2><ul class="about-list">{li_list(mission_ul)}</ul></section>'
            f'<section class="reveal" aria-labelledby="ab-v"><h2 id="ab-v" class="about-h2">{esc(u["about_values"])}</h2><ul class="about-list">{li_list(values_ul)}</ul></section></div>'
            f'<section class="block" aria-labelledby="ab-t"><h2 id="ab-t" class="about-h2 about-h2-rule">{esc(u["about_topics"])}</h2>{tiles_block()}</section>'
            f'{search_block("q-about")}{faq_block(faq)}</div>')
    meta = meta_for("about", jsonld=[bc_ld, faq_jsonld(faq)], og_image=og_image("site/hero-africa-map") if False else og_image(None))
    emit(layout(main, meta, "page-about"))
    SITEMAP.append(("about", alt_map("about")))


def field(idn, label, type_="text", req=False, ph="", ac="", extra=""):
    u = U()
    r = ' required aria-required="true"' if req else ""
    star = f' <span class="req" aria-hidden="true">*</span><span class="sr-only"> ({esc(u["c_req"])})</span>' if req else ""
    acs = f' autocomplete="{ac}"' if ac else ""
    return (f'<div class="field"><label for="{idn}">{esc(label)}{star}</label>'
            f'<input id="{idn}" name="{idn}" type="{type_}"{r}{acs} placeholder="{attr(ph or label)}" aria-describedby="{idn}-err"{extra}>'
            f'<p class="field-err" id="{idn}-err" hidden></p></div>')


def build_contact(lang):
    path = static_path("contact", lang)
    begin(lang, path, alt_map("contact"))
    u = U()
    items = crumbs("contact")
    bc_html, bc_ld = crumb_html_ld(items)
    h1 = "Africa Global Press - Contactez la Rédaction" if lang == "fr" else "Africa Global Press - Contact the Editorial Team"
    soc = "".join(f'<li><a class="soc-item" href="{SOCIAL[k]}" rel="noopener noreferrer" target="_blank">{icon(k, "i i-lg")}<span><b>{esc(n)}</b><small>{esc(v)}</small></span></a></li>' for n, v, k in u["soc"])
    form = (f'<form class="form contact-form" id="contact-form" data-contact novalidate aria-labelledby="cf-t">'
            f'<h2 class="sr-only" id="cf-t">{esc(u["c_form"])}</h2>'
            f'<div class="form-summary" role="alert" tabindex="-1" hidden>{esc(u["c_err_summary"])}</div>'
            f'<div class="row2">{field("fname", u["c_fn"], req=True, ac="given-name")}{field("lname", u["c_ln"], req=True, ac="family-name")}</div>'
            f'{field("org", u["c_org"], ph=u["c_org_ph"], ac="organization")}'
            f'<div class="row2">{field("email", u["c_email"], "email", True, u["c_email_ph"], "email")}{field("tel", u["c_tel"], "tel", False, u["c_tel_ph"], "tel")}</div>'
            f'<div class="row2">{field("country", u["c_country"], ac="country-name")}{field("city", u["c_city"], ac="address-level2")}</div>'
            f'<div class="field"><label for="message">{esc(u["c_msg"])} <span class="req" aria-hidden="true">*</span><span class="sr-only"> ({esc(u["c_req"])})</span></label>'
            f'<textarea id="message" name="message" rows="5" required aria-required="true" aria-describedby="message-err"></textarea><p class="field-err" id="message-err" hidden></p></div>'
            f'<div class="field field-check"><label class="check"><input type="checkbox" id="consent" name="consent" required aria-required="true" aria-describedby="consent-err"><span>{esc(u["c_consent"])}</span></label><p class="field-err" id="consent-err" hidden></p></div>'
            f'<div class="field field-check"><label class="check"><input type="checkbox" id="terms" name="terms"><span>{esc(u["c_terms_pre"])} <a href="{L("legal")}">{esc(u["c_terms"])}</a> {esc(u["c_terms_mid"])} <a href="{L("privacy")}">{esc(u["c_terms_priv"])}</a></span></label></div>'
            f'<div class="hp" aria-hidden="true"><label for="website">Website</label><input type="text" id="website" name="website" tabindex="-1" autocomplete="off"></div>'
            f'<button type="submit" class="btn btn-primary btn-lg">{esc(u["c_send"])}</button>'
            f'<div class="form-ok" role="status" tabindex="-1" hidden><h3>{esc(u["c_ok_title"])}</h3><p>{esc(u["c_ok"])}</p></div></form>')
    main = (f'<div class="container-page contact">{bc_html}<p class="contact-kicker">Contact</p><h1 class="contact-title">{esc(h1)}</h1>'
            f'<div class="contact-grid"><section class="contact-main" aria-labelledby="ct-t"><h2 class="contact-h2" id="ct-t">{esc(u["c_title"])}</h2>'
            f'<p>{esc(u["c_intro1"])}<br>{esc(u["c_intro2"])}<br>{esc(u["c_intro3"])}</p>{form}</section>'
            f'<aside class="contact-side" aria-labelledby="soc-t"><h2 class="asd-title" id="soc-t">{esc(u["c_social"])}</h2><ul class="soc-grid">{soc}</ul>'
            f'<p class="soc-text">{esc(u["c_social_text"])}</p></aside></div></div>')
    meta = meta_for("contact", jsonld=[bc_ld, {"@context": "https://schema.org", "@type": "ContactPage", "name": h1, "url": f"{SITE_URL}/{path}"}], og_image=og_image(None))
    emit(layout(main, meta, "page-contact"))
    SITEMAP.append(("contact", alt_map("contact")))


def build_search(lang):
    path = static_path("search", lang)
    begin(lang, path, alt_map("search"))
    u = U()
    items = crumbs("search")
    bc_html, bc_ld = crumb_html_ld(items)
    idx = []
    for a in ARTICLES[lang]:
        m = man_entry(a["img"]) if a["img"] else None
        th = None
        if m:
            f = img_files(m)[0][1]
            th = A(f)
        idx.append({"t": a["title"], "e": a["excerpt"], "u": art_url(a), "k": a["cats"], "c": [cat_label(k) for k in a["cats"]],
                    "d": a["date"].date().isoformat(), "df": fmt_date(a["date"]), "i": th, "r": minutes_label(a), "a": a["author"]})
    data = json.dumps(idx, ensure_ascii=False, separators=(",", ":")).replace("</", "<\\/")
    cats = "".join(f'<option value="{k}">{esc(cat_label(k))}</option>' for k in ["society", "politics", "finance", "economy", "culture", "sports", "science", "health", "online-press"])
    msgs = json.dumps({"none": u["sr_none"], "prompt": u["sr_prompt"], "count": u["sr_count"], "for": u["sr_for"], "all": u["sr_all"], "more": u["sr_more"],
                       "alt": ("Illustration de l'article : " if lang == "fr" else "Illustration for the article: ")}, ensure_ascii=False).replace("</", "<\\/")
    main = (f'<div class="container-page search-page">{bc_html}<h1 class="page-title">{esc(u["sr_title"])}</h1>'
            f'<form class="search-form search-form-lg" role="search" method="get" action="{L("search")}" data-search-form>'
            f'<label class="sr-only" for="q">{esc(u["search_label"])}</label><span class="sf-ico">{icon("search")}</span>'
            f'<input id="q" class="sf-input" type="search" name="q" placeholder="{attr(u["sr_ph"])}" autocomplete="off"><button class="sf-btn" type="submit" aria-label="{attr(u["search_btn"])}">{icon("arrow-r")}</button></form>'
            f'<div class="sr-bar"><label class="sr-select"><span>{esc(u["sr_filter"])}</span><select name="cat" data-search-cat><option value="">{esc(u["sr_all_cats"])}</option>{cats}</select></label>'
            f'<label class="sr-select"><span>{esc(u["sr_sort"])}</span><select data-search-sort><option value="relevance">{esc(u["sr_relevance"])}</option><option value="date">{esc(u["sr_newest"])}</option></select></label>'
            f'<p class="sr-count" role="status" aria-live="polite" data-search-count></p></div>'
            f'<div class="sr-list" data-search-results></div><div class="more-wrap"><button type="button" class="btn btn-outline" data-search-more hidden>{esc(u["sr_more"])}</button></div>'
            f'<noscript><p class="notice">{esc(u["sr_prompt"])} <a href="{L("online-press")}">{esc(cat_label("online-press"))}</a></p></noscript></div>'
            f'<script type="application/json" id="search-index">{data}</script><script type="application/json" id="search-msgs">{msgs}</script>')
    meta = meta_for("search", jsonld=[bc_ld], og_image=og_image(None), robots="noindex,follow")
    emit(layout(main, meta, "page-search"))


# ---- root + support files -------------------------------------------------------------------
def build_root():
    CTX.update(lang="fr", cur="index.html", alts={"fr": "fr/index.html", "en": "en/index.html"})
    page = f'''<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Africa Global Press | Actualité Africaine — African News</title>
<meta name="description" content="{attr(DESCRIPTIONS["fr"]["home"])}">
<link rel="canonical" href="{SITE_URL}/fr/index.html">
<link rel="alternate" hreflang="fr" href="{SITE_URL}/fr/index.html">
<link rel="alternate" hreflang="en" href="{SITE_URL}/en/index.html">
<link rel="alternate" hreflang="x-default" href="{SITE_URL}/fr/index.html">
<meta property="og:type" content="website"><meta property="og:title" content="Africa Global Press"><meta property="og:description" content="{attr(DESCRIPTIONS["fr"]["home"])}">
<meta property="og:locale" content="fr_FR"><meta property="og:locale:alternate" content="en_US"><meta property="og:url" content="{SITE_URL}/fr/index.html">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#013079">
<link rel="icon" href="assets/icons/favicon.ico" sizes="48x48">
<link rel="icon" href="assets/icons/favicon.svg" type="image/svg+xml" sizes="any">
<link rel="icon" href="assets/icons/favicon-32.png" type="image/png" sizes="32x32">
<link rel="icon" href="assets/icons/favicon-16.png" type="image/png" sizes="16x16">
<link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
<link rel="manifest" href="manifest.webmanifest">
<noscript><meta http-equiv="refresh" content="3;url=fr/index.html"></noscript>
<style>body{{margin:0;min-height:100vh;display:grid;place-items:center;font:20px/1.4 Georgia,serif;background:#fff;color:#141414}}main{{text-align:center;padding:24px}}h1{{font-size:28px;margin:0 0 12px}}a{{display:inline-block;margin:8px;padding:12px 24px;border-radius:999px;background:#013079;color:#fff;font:700 16px system-ui,sans-serif;text-decoration:none}}a:focus-visible{{outline:3px solid #ffec00;outline-offset:2px}}</style>
</head>
<body>
<main>
<h1>Africa Global Press</h1>
<p>Choisissez votre langue — Choose your language</p>
<p><a href="fr/index.html" hreflang="fr" lang="fr">Français</a><a href="en/index.html" hreflang="en" lang="en">English</a></p>
</main>
<script>(function(){{var t="fr";try{{var s=localStorage.getItem("agp-lang");if(s==="en"||s==="fr"){{t=s}}}}catch(e){{}}location.replace(t+"/index.html")}})();</script>
</body>
</html>
'''
    (PROTO / "index.html").write_text(page, encoding="utf8", newline="\n")


def build_support():
    # sitemap
    urls = []
    for key, alts in SITEMAP:
        for lg in LANGS:
            if lg not in alts:
                continue
            loc = f"{SITE_URL}/{alts[lg]}"
            links = "".join(f'<xhtml:link rel="alternate" hreflang="{l2}" href="{SITE_URL}/{alts[l2]}"/>' for l2 in LANGS if l2 in alts)
            if "fr" in alts:
                links += f'<xhtml:link rel="alternate" hreflang="x-default" href="{SITE_URL}/{alts["fr"]}"/>'
            urls.append(f"<url><loc>{loc}</loc><lastmod>{NOW.isoformat()}</lastmod>{links}</url>")
    sm = ('<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">\n'
          + "\n".join(dict.fromkeys(urls)) + "\n</urlset>\n")
    (PROTO / "sitemap.xml").write_text(sm, encoding="utf8", newline="\n")
    (PROTO / "robots.txt").write_text(f"# SITE_URL is a placeholder host, see tools/build_site.py\nUser-agent: *\nAllow: /\nDisallow: /admin/\nSitemap: {SITE_URL}/sitemap.xml\n",
                                      encoding="utf8", newline="\n")
    icons = [
        {"src": "assets/icons/icon-192.png", "sizes": "192x192", "type": "image/png", "purpose": "any"},
        {"src": "assets/icons/icon-512.png", "sizes": "512x512", "type": "image/png", "purpose": "any"},
        {"src": "assets/icons/icon-maskable-512.png", "sizes": "512x512", "type": "image/png", "purpose": "maskable"},
        {"src": "assets/icons/favicon.svg", "sizes": "any", "type": "image/svg+xml"},
    ]
    wm = {"name": "Africa Global Press", "short_name": "AG Press", "description": DESCRIPTIONS["fr"]["home"], "start_url": "index.html",
          "scope": "./", "display": "standalone", "background_color": "#ffffff", "theme_color": "#013079", "lang": "fr", "icons": icons}
    (PROTO / "manifest.webmanifest").write_text(json.dumps(wm, ensure_ascii=False, indent=2), encoding="utf8", newline="\n")
    core = ["index.html", "fr/index.html", "en/index.html", "assets/css/app.min.css", "assets/js/app.js", "assets/js/vendor/turbo.min.js", "assets/icons/favicon.svg", "assets/icons/favicon.ico", "assets/icons/icon-192.png",
            "assets/fonts/Alegreya-normal-400-latin.woff2", "assets/fonts/EncodeSans-normal-400-latin.woff2"]
    sw = f"""/* Africa Global Press service worker (works only over http(s); ignored on file://) */
const CACHE = 'agp-v{NOW.strftime("%Y%m%d")}';
const CORE = {json.dumps(core)};
self.addEventListener('install', e => {{ e.waitUntil(caches.open(CACHE).then(c => c.addAll(CORE)).then(() => self.skipWaiting())); }});
self.addEventListener('activate', e => {{ e.waitUntil(caches.keys().then(k => Promise.all(k.filter(x => x !== CACHE).map(x => caches.delete(x)))).then(() => self.clients.claim())); }});
self.addEventListener('fetch', e => {{
  if (e.request.method !== 'GET') return;
  e.respondWith(caches.match(e.request).then(hit => hit || fetch(e.request).then(res => {{
    const copy = res.clone(); caches.open(CACHE).then(c => c.put(e.request, copy)); return res;
  }}).catch(() => caches.match('index.html'))));
}});
"""
    (PROTO / "sw.js").write_text(sw, encoding="utf8", newline="\n")


def build_urlmap():
    lines = ["# URL-MAP — old URL (WordPress) → new relative URL (FR ↔ EN)", "",
             "Generated by `tools/build_site.py`. New URLs are relative to `prototype_html/` and open from `file://`. "
             "EN old URLs are inferred from the WordPress/Polylang structure (`/en/...`).", "",
             "## Pages", "", "| Page | Old URL (FR) | FR | EN | Old URL (EN) |", "|---|---|---|---|---|"]
    old = {
        "home": ("/", "/en/home/"), "about": ("/qui-sommes-nous/", "/en/who-are-we/"), "contact": ("/contact/", "/en/contactus/"),
        "legal": ("/mentions-legales/", "/en/legal-notices/"), "privacy": ("/politique-de-confidentialite/", "/en/cookie-policy/ (slug swapped in DB)"),
        "cookies": ("/politique-en-matiere-de-cookies/", "/en/privacy-policy/ (slug swapped in DB)"), "search": ("/?s=", "/en/?s="),
        "society": ("/category/societe/", "/en/category/social-news/"), "politics": ("/category/politique-fr/ · /actualites-politiques/", "/en/category/political-news/"),
        "finance": ("/category/finance-fr/", "/en/category/finance-en/"), "economy": ("/actualites-economiques/ · /category/economie-africaine/", "/en/category/african-economy/"),
        "culture": ("/actualites-culturelles/ · /category/culture-africaine/", "/en/category/culture/"), "sports": ("/actualites-sportives/ · /category/sports-fr/", "/en/category/sports/"),
        "science": ("/actualites-sur-la-science-et-technologie/", "/en/category/science-and-technology/"), "health": ("/actualites-sur-la-sante-bien-etre/", "/en/category/health-wellness/"),
        "online-press": ("/category/presse-en-ligne/ · /press-en-ligne/", "/en/category/online-press/"), "podcasts": ("/category/radio/", "/en/category/podcast/"),
        "videos": ("/category/television-fr/", "/en/category/television/"), "magazine": ("/magazine/", "/en/category/magazine-en/"),
        "radios": ("/radios-africaines/", "/en/radio/"), "television": ("/television-africaine/", "/en/television/"),
    }
    keys = ["home"] + list(CATS.keys()) + ["about", "contact", "legal", "privacy", "cookies", "search"]
    for k in keys:
        o = old.get(k, ("", ""))
        lines.append(f"| {k} | `{o[0]}` | `{page_path(k, 'fr')}` | `{page_path(k, 'en')}` | `{o[1]}` |")
    lines += ["", "Root `index.html` redirects to `fr/index.html` or `en/index.html` (stored preference, then `navigator.language`).", "",
              "## Articles", "", "| Old URL (FR) | FR | EN | EN origin |", "|---|---|---|---|"]
    for a in ARTICLES["fr"]:
        o = alt_article(a)
        origin = "native EN article" if a["id"] in PAIRS else ("FR original · full EN translation" if (alt_article(a) and alt_article(a).get("tr")) else "FR original · EN title/summary translated")
        lines.append(f"| `/{a['date'].year}/{a['date'].month:02d}/{a['date'].day:02d}/{a['dbslug']}/` | `{a['path']}` | `{o['path'] if o else ''}` | {origin} |")
    lines += ["", "## EN-only articles (no FR counterpart)", ""]
    n = 0
    for a in ARTICLES["en"]:
        if alt_article(a) is None:
            lines.append(f"- `{a['path']}`")
            n += 1
    if not n:
        lines.append("None: every EN native article is paired with a FR article.")
    lines += ["", "## Notes", "",
              "- Category chips link to the language-specific category pages above; DB category slugs (`societe`/`social-news`, `politique-fr`/`political-news`, …) are unified per key in `tools/site_strings.py`.",
              "- Clone page `Finance` (archive layout) = `finance.html` / `finance.html`; clone page `Finance 2` (featured + grid variant, “Actualités économiques”) = `economie.html` / `economy.html`.",
              "- Inline article images are only kept when present in `data/image-manifest.json` (no hot-linking).",
              "- Absolute host is a placeholder: `SITE_URL` in `tools/build_site.py`."]
    (ROOT / "docs/mapping/URL-MAP.md").write_text("\n".join(lines) + "\n", encoding="utf8", newline="\n")


def main():
    build_articles()
    for lang in LANGS:
        (PROTO / lang / "articles").mkdir(parents=True, exist_ok=True)
        for f in list((PROTO / lang).glob("*.html")) + list((PROTO / lang / "articles").glob("*.html")):
            f.unlink()
    for lang in LANGS:
        build_home(lang)
        for k in CATS:
            build_category(lang, k)
        for k in ("legal", "privacy", "cookies"):
            build_legal(lang, k)
        build_about(lang)
        build_contact(lang)
        build_search(lang)
        for a in ARTICLES[lang]:
            build_article(a)
    for path, h in PAGES_OUT:
        out = PROTO / path
        out.parent.mkdir(parents=True, exist_ok=True)
        out.write_text(h, encoding="utf8", newline="\n")
    build_root()
    build_support()
    build_urlmap()
    n = {lg: sum(1 for p, _ in PAGES_OUT if p.startswith(lg + "/")) for lg in LANGS}
    print("pages:", n, "total", len(PAGES_OUT) + 1, "| articles:", {lg: len(ARTICLES[lg]) for lg in LANGS}, "| manifest entries:", len(MAN))


if __name__ == "__main__":
    main()
