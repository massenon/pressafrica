# Session log (history kept in the repo)

One block per working session: what was decided and done, what is open. Newest first. Details live in
`pressmedia_laravel/docs/PROGRESS.md` (one row per module) and in commit messages; this file is the narrative.

## 2026-10-28 → 2026-10-29 — phases 0 (setup) to 3, branch `claude/brave-heisenberg-53zohy`

**Token strategy (owner request, keep applying):**
- Root `CLAUDE.md` says what to read (2 files) and what never to scan (`prototype_html/`, `docs/qa/`).
- Work module by module; read files by `grep -n`/`sed -n` ranges; one commit per module with tests green.
- Reuse instead of rewriting: the importer reads the generator's rules (`tools/export_laravel_import.py`),
  the public site reuses the prototype's compiled CSS/JS (phase 4).
- Owner chose to keep one long session (2026-10-29); this log is the memory if a new session starts.

**Done (66 tests green at the end of phase 3):**
| Phase | Result |
|---|---|
| 1 | Admin login/logout + Sanctum tokens, rate limits; append-only audit log (`AuditLog::record()` / `::system()`); role × permission screen (admin locked, no self-escalation); 100 permissions from the prototype's 20 modules; settings (AI keys encrypted, security values drive password rule and limiter); `/fr` `/en` routing, `/` → remembered locale else French (never Accept-Language); light theme by default via `theme` cookie |
| 2 | Posts (FR required, EN optional, draft/pending/published/scheduled, authors own posts only, Purifier); `posts:publish-scheduled` every minute; nested categories; tags (merge); media library (JPEG/PNG → WebP, finfo type check); public comment form (consent, honeypot, throttle) + moderation queue |
| 3 | `php artisan content:import`: 64 FR + 64 EN = source; 128 slugs identical to the prototype URLs; 64 covers; 41 source anomalies listed in `storage/app/private/import-report.json` |

**Decisions taken in this session:**
- Admin screens are plain controllers + Blade for now; Livewire/Quill come with the admin layout module.
- `app.fallback_locale = en` (framework strings), `app.content_locale = fr` (content fallback, INV-10).
- Imported bylines become `author` accounts `<slug>@authors.invalid` with no usable password (owner may prefer one shared account).
- No DB trigger on `audit_logs` (shared-host MySQL may lack the privilege, Q9); protection is in the model and routes.

**Open / needs the owner:** Q9 host + PHP + `storage:link` on cPanel; Q7 analytics source (phase 6); Q8 DPO (phase 7);
Q4/Q5 magazine/radio/TV content and outbound links (phase 5).

**Phase 4 progress (2026-10-29):** article page done and pixel-checked against the prototype (see PROGRESS.md).
Approach chosen to save tokens and keep the look 100 %: reuse the prototype's generated chrome, compiled CSS/JS and
cleaned bodies instead of re-writing them. Found and fixed on the way: Purifier's default config dropped `<h2>`.

Then (same day): sections, home, fixed pages, search, robots/sitemaps/news sitemap/RSS. Parity tool
`tools/qa/laravel_parity/sweep.sh` (normalised HTML diff) replaced screenshots: all FR pages identical; EN differs
only for the 8 native EN WordPress rows (other date/author, one post keeps the FR one; listed in the import report).
Generated from the prototype, never hand-edited: `resources/chrome/*`, `lang/*/proto.php`, `lang/*/seo.php`,
`prototype_html/data/laravel-*.json` (re-run `tools/export_chrome.py` and `tools/export_laravel_import.py`).

**Next:** phase 5 — media landing pages (podcasts, videos, magazine, radios, TV) public + admin; then refresh the
chrome's dynamic zones (mega-menu cards) and the admin layout module (Livewire, prototype admin look).
