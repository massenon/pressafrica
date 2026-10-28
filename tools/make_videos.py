#!/usr/bin/env python3
"""Prototype video clips: one short silent MP4 per entry of data/videos.json (slow zoom on the linked article's photo).
Real footage replaces these files later; build_site.py only needs assets/video/<id>.mp4 and the duration written here.
Usage: python tools/make_videos.py   (needs ffmpeg + ffprobe on PATH)"""
import json
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PROTO = ROOT / "prototype_html"
DATA = PROTO / "data"
OUT = PROTO / "assets" / "video"
SECONDS = 10

videos = json.loads((DATA / "videos.json").read_text(encoding="utf8"))
articles = {a["id"]: a for a in json.loads((DATA / "articles.json").read_text(encoding="utf8"))}
manifest = json.loads((DATA / "image-manifest.json").read_text(encoding="utf8"))
OUT.mkdir(parents=True, exist_ok=True)

for v in videos["videos"]:
    img = manifest[articles[v["article"]]["image"]["path"]]["widths"]["1280"]
    dst = OUT / f'{v["id"]}.mp4'
    # 1280x720 crop centred on the photo, zoom 1.0 -> 1.2 over the clip; H.264 baseline-compatible, no audio
    vf = (f"scale=1920:-2,zoompan=z='min(1+0.2*on/{SECONDS * 25},1.2)':x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)':d={SECONDS * 25}:s=1280x720:fps=25,format=yuv420p")
    subprocess.run(["ffmpeg", "-y", "-loglevel", "error", "-loop", "1", "-i", str(PROTO / img), "-vf", vf, "-t", str(SECONDS),
                    "-c:v", "libx264", "-crf", "30", "-preset", "veryfast", "-movflags", "+faststart", "-an", str(dst)], check=True)
    dur = float(subprocess.run(["ffprobe", "-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", str(dst)], capture_output=True, text=True, check=True).stdout)
    v["duration"] = round(dur)
    v["file"] = f"assets/video/{v['id']}.mp4"
    v["poster"] = img.replace("-1280", "-860")
    print(v["id"], v["duration"], "s", dst.stat().st_size // 1024, "KB")

(DATA / "videos.json").write_text(json.dumps(videos, ensure_ascii=False, indent=2) + "\n", encoding="utf8")
