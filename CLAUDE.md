# pressafrica (Africa Global Press)

Monorepo. Read this file, then `pressmedia_laravel/CLAUDE.md` and `pressmedia_laravel/docs/PROGRESS.md`. Nothing else unless the task needs it.

## Layout
- `pressmedia_laravel/` — the product (Laravel 12 port). All new work goes here.
- `prototype_html/` — executable UI spec, ~170 generated pages (39 MB). Never list, grep or read it wholesale. To see how a page looks or behaves, open ONE representative file (`fr/index.html`, one `fr/articles/*.html`, one `admin/*.html`) or read its generator `tools/build_site.py`. Design tokens: `prototype_html/assets/css/src.css`.
- `docs/` — PRD (`PRD.md`, EX-xx/INV-xx ids), plan (`IMPLEMENTATION-PLAN.md`, phases 0-8), `TECHNICAL.md`. Grep for the EX/INV id you need instead of reading whole files.
- `tools/` — Python generators and QA scripts for the prototype only. `docs/qa/` are past reports: do not read.

## Working rules (token budget)
- One module (one PROGRESS.md row / plan phase step) per session. Finish with `php artisan test` green, update its PROGRESS.md row, commit.
- Locate with `grep -n`/`sed -n` ranges; avoid reading files over ~200 lines in full.
- Do not re-read specs already summarised in `pressmedia_laravel/CLAUDE.md`.
- Blocked on an owner question (Q4, Q5, Q7, Q8, Q9 in the PRD)? Write the blocker in PROGRESS.md and move to the next unblocked module; do not invent an answer.
