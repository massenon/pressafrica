# Cartographie — docs · design-system · prototypes

```
pressafrica/
├─ clone/                  READ-ONLY reference (9 saved pages + 16 full-page screenshots)
├─ exxhrgce_wp623.sql      READ-ONLY source of content (72 posts, 42 pages, 24 categories FR/EN)
├─ pressmedia_images/      READ-ONLY originals (1149 files, /YYYY/MM/)
├─ keyfeatures.txt         product scope (CMS features) → admin back-office
├─ tools/                  extract_sql.py (done), build_site.py, optimize_images.py, qa/*
├─ docs/
│  ├─ design-system/DESIGN-SYSTEM.md
│  ├─ mapping/WORKFLOW-MAP.md  (this file)
│  ├─ mapping/URL-MAP.md       old pressafrica.media URL → new relative URL (FR ↔ EN)
│  ├─ prd/PRD.md               requirements traced to keyfeatures.txt
│  └─ qa/QA-REPORT.md          written by the QA/follow-up agent
└─ prototype_html/         the deliverable, opens from index.html (no server needed)
   ├─ index.html           language redirect (navigator.language → fr/ or en/)
   ├─ fr/  en/             public site, same structure, same slugs per language
   ├─ admin/               back-office (HTML + Tailwind + vanilla JS, data in localStorage)
   ├─ assets/{css,js,img,fonts}
   └─ data/                articles.json categories.json clone_pages.json
```

## Workflow (understand → build → verify)
1. **Understand** (done): audit clone, extract SQL → `data/*.json`, tokens → design system.
2. **Build in parallel** (4 agents, disjoint folders): images · admin · public site FR/EN · (then) QA.
3. **Verify**: QA agent runs Lighthouse-style checks, 4 viewports, link crawl, console errors; writes `docs/qa/QA-REPORT.md`; sends defects back to owners.

## Folder ownership (no two agents write the same path)
| Agent | Owns |
|---|---|
| images | `assets/img/**`, `tools/optimize_images.py`, `data/image-manifest.json` |
| admin | `admin/**`, `assets/js/admin*.js` |
| site | `fr/**`, `en/**`, `index.html`, `assets/js/app.js`, `assets/css/src.css`, `tools/build_site.py`, `docs/mapping/URL-MAP.md`, sitemap/robots |
| QA | `docs/qa/**`, `tools/qa/**` (read-only on everything else) |
