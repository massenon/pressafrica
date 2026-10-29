# -*- coding: utf-8 -*-
"""Export the prototype's editorial rules for the Laravel importer (`php artisan content:import`).

The generator keeps these rules in Python (site_strings.CATS, site_en_translations.PAIRS/T); the importer
reads this JSON instead of duplicating them in PHP, so both builds stay in step. Re-run after editing either file:
    python tools/export_laravel_import.py
"""
import json
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE))
from site_strings import CATS                      # noqa: E402
from site_en_translations import PAIRS, T          # noqa: E402

out = {
    "categories": [
        {"key": k, "fr": c["fr"], "en": c["en"], "slug_fr": c["p_fr"].removesuffix(".html"),
         "slug_en": c["p_en"].removesuffix(".html"), "db": c["db"]}
        for k, c in CATS.items()
    ],
    "pairs": PAIRS,                                   # FR post id -> native EN post id
    "en": {k: {"title": v[0], "excerpt": v[1]} for k, v in T.items()},   # EN title/excerpt of FR-only posts
}
dest = HERE.parent / "prototype_html" / "data" / "laravel-import.json"
dest.write_text(json.dumps(out, ensure_ascii=False, indent=1) + "\n", encoding="utf8")
print(f"wrote {dest} ({len(out['categories'])} categories, {len(PAIRS)} pairs, {len(T)} EN stubs)")

# Article bodies exactly as the prototype prints them (build_site.clean_body() for WordPress bodies,
# sanitised translations as they are), keyed by language then WordPress id of the row the body comes from.
import build_site as B  # noqa: E402  (guarded by __main__, importing does not run the build)

B.build_articles()
bodies = {"fr": {}, "en": {}}
for lang in ("fr", "en"):
    for a in B.ARTICLES[lang]:
        B.CTX["lang"], B.CTX["cur"] = lang, a["path"]
        bodies[lang][a["id"]] = a["body"] if a.get("tr") else B.clean_body(a["body"], a)
dest = HERE.parent / "prototype_html" / "data" / "laravel-bodies.json"
dest.write_text(json.dumps(bodies, ensure_ascii=False) + "\n", encoding="utf8")
print(f"wrote {dest} ({len(bodies['fr'])} FR + {len(bodies['en'])} EN bodies)")
