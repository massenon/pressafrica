#!/usr/bin/env python3
"""Generate responsive WebP, OG images and LQIP for Africa Global Press prototype."""
import base64, io, json, re, sys, unicodedata
from pathlib import Path
from PIL import Image, ImageOps

ROOT = Path(__file__).resolve().parent.parent
SRC = ROOT / "pressmedia_images"
PROTO = ROOT / "prototype_html"
OUT = PROTO / "assets" / "img"
WIDTHS = (420, 860, 1280)
CLONE = ROOT / "clone" / "Africa Global Press _ L'Actualité Africaine en Direct et en Continu_files"

# (clean name, source path) ; source relative to SRC unless Path
SITE = [
    ("logo", CLONE / "logo_africa_global_press.webp"),
    ("hero-africa-map", "2025/03/Africa-Global-Press.webp"),
    ("tile-sante", "2025/03/Sante-Bien-etre-Actualite-de-la-Sante-en-Afrique-Africa-Global-Press.webp"),
    ("tile-science", "2025/03/Science-Technologie-Africaine-Africa-Global-Press.webp"),
    ("tile-sport", "2025/03/Actualites-Sportives-Africa-Global-Press.webp"),
    ("tile-politique", "2025/03/Politique-Africaine-Africa-Global-Press.webp"),
    ("tile-culture", "2025/06/Culture-Africaine-Africa-Global-Press_1.webp"),
    ("tile-business", "2025/06/Economie-Africaine-Africa-Global-Press.webp"),
]

def slugify(s):
    s = unicodedata.normalize("NFKD", s).encode("ascii", "ignore").decode()
    return re.sub(r"[^a-z0-9]+", "-", s.lower()).strip("-") or "img"

def load(p):
    im = Image.open(p)
    im = ImageOps.exif_transpose(im)
    if im.mode in ("P", "LA") or (im.mode == "RGBA"):
        im = im.convert("RGBA")
    elif im.mode != "RGB":
        im = im.convert("RGB")
    return im

def save_webp(im, path, w):
    h = round(im.height * w / im.width)
    r = im.resize((w, max(h, 1)), Image.LANCZOS) if w != im.width else im
    path.parent.mkdir(parents=True, exist_ok=True)
    r.save(path, "WEBP", quality=78, method=6)

def lqip(im):
    t = im.convert("RGB").resize((24, max(1, round(im.height * 24 / im.width))), Image.LANCZOS)
    b = io.BytesIO(); t.save(b, "WEBP", quality=30, method=6)
    return "data:image/webp;base64," + base64.b64encode(b.getvalue()).decode()

def og(im, path):
    rgb = im.convert("RGB") if im.mode != "RGB" else im
    o = ImageOps.fit(rgb, (1200, 630), Image.LANCZOS, centering=(0.5, 0.5))
    path.parent.mkdir(parents=True, exist_ok=True)
    o.save(path, "JPEG", quality=80, optimize=True, progressive=True)

def process(key, src, slug, folder, manifest, stats):
    im = load(src)
    stats["before"] += src.stat().st_size
    widths = {}
    native_rel = None
    for w in WIDTHS:
        if native_rel:  # already emitted at native width: never upscale, reuse
            widths[str(w)] = native_rel
            continue
        rel = f"assets/img/{folder}/{slug}-{w}.webp"
        save_webp(im, PROTO / rel, min(w, im.width))
        widths[str(w)] = rel
        stats["after"] += (PROTO / rel).stat().st_size
        if w >= im.width:
            native_rel = rel
    ogrel = f"assets/img/og/{slug}.jpg"
    og(im, PROTO / ogrel)
    stats["after"] += (PROTO / ogrel).stat().st_size
    manifest[key] = {"slug": slug, "widths": widths, "og": ogrel,
                     "width": im.width, "height": im.height, "lqip": lqip(im)}

def main():
    arts = json.loads((PROTO / "data" / "articles.json").read_text(encoding="utf8"))
    paths = []
    for a in arts:
        p = (a.get("image") or {}).get("path")
        if p and p not in paths:
            paths.append(p)
    for d in ("posts", "site", "og"):  # clean stale outputs
        for f in (OUT / d).glob("*"):
            if f.is_file():
                f.unlink()
    manifest, missing = {}, []
    stats = {"before": 0, "after": 0}
    used = set()
    for p in paths:
        src = SRC / p
        if not src.is_file():
            missing.append(p); continue
        slug = slugify(Path(p).stem)
        if slug in used:
            slug = slugify(p.rsplit(".", 1)[0].replace("/", "-"))
        used.add(slug)
        process(p, src, slug, "posts", manifest, stats)
    site = {}
    for name, s in SITE:
        src = s if isinstance(s, Path) else SRC / s
        if not src.is_file():
            missing.append(str(s)); continue
        process("site/" + name, src, name, "site", site, stats)
    manifest.update(site)
    (PROTO / "data" / "image-manifest.json").write_text(
        json.dumps(manifest, ensure_ascii=False, indent=1), encoding="utf8")
    n_art = len(paths) - sum(1 for m in missing if m in paths)
    print(f"articles images: {n_art}/{len(paths)}  site images: {len(site)}")
    print(f"before {stats['before']/1e6:.1f} MB  after {stats['after']/1e6:.1f} MB")
    print("missing:", missing)

if __name__ == "__main__":
    main()
