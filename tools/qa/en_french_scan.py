#!/usr/bin/env python3
"""Find French text in EN pages (UI strings, menus, metas, aria/alt/title attrs, JSON-LD). Article bodies on the
EN articles whose lang attribute on the body is fr / flagged 'Read this article in French' are reported separately.
Output docs/qa/data/en_french.json. Run: python tools/qa/en_french_scan.py"""
import json, re
from pathlib import Path
from collections import defaultdict, Counter
from bs4 import BeautifulSoup

ROOT = Path(__file__).resolve().parents[2] / "prototype_html"
OUT = Path(__file__).resolve().parents[2] / "docs/qa/data"
FR_WORDS = set("le la les un une des du de d' l' et est sont pour par avec dans sur au aux ce cette ces qui que quoi nous vous votre vos notre nos ne pas plus ou où mais donc car en à été être avoir fait faire lire voir toutes tous tout toute aucun rechercher consulter découvrir abonner connecter accueil société politique finance économie sport santé vidéos presse ligne mentions légales confidentialité cookies gérer fermer ouvrir suivant précédent résultats".split())
ACC = re.compile(r"[àâçéèêëîïôùûüœ]", re.I)


def frscore(t):
    w = re.findall(r"[a-zA-Zàâçéèêëîïôùûüœ']+", t.lower())
    if len(w) < 2:
        return 1 if ACC.search(t) and len(t) > 3 else 0
    hits = sum(1 for x in w if x in FR_WORDS)
    return hits / len(w) + (0.15 if ACC.search(t) else 0)


# English words that legitimately contain accents / proper nouns -> allow list
ALLOW = re.compile(r"Côte d.Ivoire|Cameroun|Sénégal|Gabon|Niger|Maroc|Africa Global Press|PRESS AFRICA|Pressafrica|Burundi|Français|Café|Coup|Congo|Mauritanie|Bénin|Tchad|Algérie|Tunisie|Égypte|Comores|Guinée|Malabo|Sénégal", re.I)
res = defaultdict(list)
fr_body = []
for p in sorted((ROOT / "en").rglob("*.html")):
    rel = p.relative_to(ROOT).as_posix()
    s = BeautifulSoup(p.read_text(encoding="utf8"), "lxml")
    is_art = "/articles/" in rel
    body = s.select_one("article.article .prose, article.article .article-body, .article-body, .prose") if is_art else None
    body_fr = False
    if body:
        bt = body.get_text(" ", strip=True)
        body_fr = frscore(bt[:600]) > 0.3
        if body_fr:
            fr_body.append(rel)
    # metas
    for m in s.find_all("meta"):
        c = m.get("content")
        k = m.get("name") or m.get("property")
        if c and k in ("description", "og:title", "og:description", "twitter:title", "twitter:description", "og:image:alt") and frscore(c) > 0.3:
            if is_art and body_fr:  # description of a French-bodied article is French by design
                res["meta_fr_body_article"].append(f"{rel} {k}: {c[:90]}")
            else:
                res["meta"].append(f"{rel} {k}: {c[:90]}")
    if s.title and frscore(s.title.get_text()) > 0.3:
        (res["title_fr_body_article"] if body_fr else res["title"]).append(f"{rel}: {s.title.get_text()[:90]}")
    # attributes
    for t in s.find_all(True):
        if body is not None and body in t.parents:
            continue
        for a in ("aria-label", "alt", "title", "placeholder", "aria-description"):
            v = t.get(a)
            if v and frscore(v) > 0.3 and not ALLOW.fullmatch(v.strip()):
                res["attr"].append(f"{rel} <{t.name} {a}>: {v[:80]}")
    # visible text nodes outside article body
    for n in s.find_all(string=True):
        par = n.parent
        if par.name in ("script", "style", "title", "noscript"):
            continue
        if body is not None and body in par.parents:
            continue
        t = n.strip()
        if len(t) < 3:
            continue
        if par.find_parent(attrs={"lang": "fr"}) or par.get("lang") == "fr":
            continue
        # card titles of French-bodied articles are translated in EN; flag anyway if French
        if frscore(t) > 0.3 and not ALLOW.fullmatch(t):
            res["text"].append(f"{rel}: {t[:90]}")
    # json-ld
    for sc in s.find_all("script", type="application/ld+json"):
        try:
            j = json.loads(sc.string)
        except Exception:
            continue
        for k in ("name", "headline", "description", "inLanguage"):
            v = j.get(k)
            if isinstance(v, str) and k != "inLanguage" and frscore(v) > 0.3 and not body_fr:
                res["jsonld"].append(f"{rel} {j.get('@type')}.{k}: {v[:80]}")
        if j.get("@type") in ("NewsArticle",):
            res["jsonld_inLanguage"].append(f"{rel} {j.get('inLanguage')} body_fr={body_fr}")
        if j.get("@type") == "FAQPage":
            for q in j.get("mainEntity", []):
                if frscore(q.get("name", "")) > 0.3 and not body_fr:
                    res["jsonld"].append(f"{rel} FAQ: {q.get('name')[:80]}")
    # html lang for French bodies
    if body_fr:
        wrapper_lang = (body.get("lang") or (body.find_parent(attrs={"lang": True}) or {}).get("lang") if body.find_parent(attrs={"lang": True}) else body.get("lang"))
        res["fr_body_lang_attr"].append((rel, body.get("lang"), body.find_parent(attrs={"lang": True}).get("lang") if body.find_parent(attrs={"lang": True}) else None))

# aggregate by distinct string
agg = Counter()
for kind in ("text", "attr", "meta", "title", "jsonld"):
    for row in res[kind]:
        key = row.split(": ", 1)[-1] if kind in ("text", "title") else row.split("> ", 1)[-1] if kind == "attr" else row.split(": ", 1)[-1]
        agg[(kind, key[:80])] += 1
summary = {"fr_body_articles": len(fr_body), "counts": {k: len(v) for k, v in res.items() if not k.startswith("_")},
           "top_distinct": [[k[0], k[1], c] for k, c in agg.most_common(60)]}
json.dump({"summary": summary, "fr_body": fr_body, "res": {k: v[:200] for k, v in res.items()}}, open(OUT / "en_french.json", "w", encoding="utf8"), ensure_ascii=False, indent=1)
print(json.dumps(summary, ensure_ascii=False, indent=1)[:6000])
