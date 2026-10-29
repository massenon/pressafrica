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

**Phase 5 (2026-10-29):** media sections in one `media_items` store, imported from the prototype JSON, public
pages identical to the prototype, generic admin module per type. Blocked on owner: Q4 (real magazine/radio/TV
content, real videos), Q5 (outbound directory links, Settings `directoryLinks`, on as in the prototype).
**Seeders:** `php artisan migrate:fresh --seed` builds the whole site (content always; demo staff + comments
outside production).

**Spec 2026-10-29 (carousel, Q&A, admin look) — arbitrated and delivered:** steps A (Featured carousel, default =
prototype slides), B (Q&A under articles, newsroom answers with badge, reader e-mail on opt-in, anti-bot rules),
C (admin in the prototype look, Livewire navigation, MediaPicker, PostPicker, Quill). App timezone = Benin.
SMTP of the owner in `.env.example` (password only on the server). Demo staff = `*@pressafrica.media`.

**Phase 6 (2026-10-29):** Q7 answered (first-party log; GA4 built, off). Delivered: dynamic menu + mega panels +
topic popup (Admin > Menus), first-party analytics + GA4 module (Admin > Analytics), newsletter with double opt-in,
ad zones with CTR, contact form to SUPPORT_MAIL with an admin inbox. Demo staff accounts also in production
(owner), never overwritten on re-seed.

**Next:** phase 7 (security/GDPR: data export and erasure, backups, error pages, production checklist) and
phase 8 (Lighthouse, UAT). Open: Q8 (DPO: GA4/AdSense consent categories, retention), Q9 (host checks).
