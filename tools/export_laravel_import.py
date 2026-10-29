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
