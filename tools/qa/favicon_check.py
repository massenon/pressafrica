#!/usr/bin/env python3
"""Round 5: every page has resolving icon links; manifest icons resolve."""
import re, json, struct
from pathlib import Path
from urllib.parse import urljoin
ROOT = Path(__file__).resolve().parents[2] / "prototype_html"
pages = [ROOT/"index.html"] + sorted(ROOT.glob("fr/**/*.html")) + sorted(ROOT.glob("en/**/*.html")) + sorted(ROOT.glob("admin/*.html"))
bad = []; kinds_missing = {}
for p in pages:
    h = p.read_text(encoding="utf8")
    links = re.findall(r"<link\b[^>]*>", h)
    got = set()
    for l in links:
        m = re.search(r'rel="([^"]*)"', l); hr = re.search(r'href="([^"]*)"', l)
        if not m or not hr or "icon" not in m.group(1): continue
        t = (re.search(r'type="([^"]*)"', l) or [None,""])[1]; sz = (re.search(r'sizes="([^"]*)"', l) or [None,""])[1]
        tgt = (p.parent / hr.group(1).split("?")[0]).resolve()
        if not tgt.exists(): bad.append((p.relative_to(ROOT).as_posix(), hr.group(1))); continue
        r = m.group(1)
        if "apple" in r: got.add("apple")
        elif "svg" in t: got.add("svg")
        elif sz == "16x16": got.add("16")
        elif sz == "32x32": got.add("32")
        elif hr.group(1).split("?")[0].endswith(".ico"): got.add("ico")
    miss = {"apple","svg","16","32","ico"} - got
    if miss: kinds_missing[p.relative_to(ROOT).as_posix()] = sorted(miss)
    if '<link rel="manifest"' not in h and p.parent.name != "admin": pass
print("pages", len(pages), "broken hrefs", len(bad), bad[:5]); print("missing kinds", len(kinds_missing), list(kinds_missing.items())[:5])
mf = json.loads((ROOT/"manifest.webmanifest").read_text(encoding="utf8"))
for i in mf["icons"]:
    f = ROOT/i["src"]; print(i["src"], f.exists(), i["sizes"], i.get("purpose"))
    if f.suffix==".png" and f.exists():
        w,hh = struct.unpack(">II", f.read_bytes()[16:24]); print("   actual", w, hh)
for f in sorted((ROOT/"assets/icons").glob("*")):
    d=f.read_bytes(); print(f.name, len(d), struct.unpack(">II", d[16:24]) if f.suffix==".png" else "")
