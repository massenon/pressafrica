# Progress (one line per module)

| Module | State |
|---|---|
| Project skeleton (Laravel 12.70, Sanctum, Spatie, Purifier, Livewire, Vue islands) | done 2026-10-26 |
| Roles and permissions (60 permissions, 4 roles, from prototype) | done, seeded |
| Content schema (categories, posts, media, podcasts, FR/EN translations) | migrated |
| Authentication (login, API tokens) | todo |
| Import of the 64 articles per language from the prototype/WordPress dump | todo |
| Public pages (home, category, article, podcasts) in Blade | todo |
| Admin Livewire: posts, categories, media | todo |
| Admin Livewire: podcasts, comments, ads, newsletter, menus, settings, users | todo |
| SEO on-page: unique meta title, meta description from the article lead, canonical, hreflang | todo |
| SEO social: Open Graph (og:image large for Facebook/WhatsApp), Twitter Card, via `@section` in the layout | todo |
| SEO structured data: Schema.org NewsArticle, BreadcrumbList, Organization, WebSite, JSON-LD | todo |
| SEO images: every `<img>` has `alt` (a11y, Google Images) and `title` (UX) | todo |
| SEO technical: robots.txt, XML sitemaps (+ news/image), core indexing, site speed (Core Web Vitals) | todo |
| Security: rate limiting (login, register, OTP; per IP and per identity) via RateLimiter/throttle | todo |
| Security: Eloquent bindings only, Blade `{{ }}` (no `{!! !!}` on untrusted data), `@csrf`, `$fillable`/`$guarded` | todo |
| Security: HttpOnly + Secure + SameSite=Strict encrypted cookies for sessions/tokens, nothing sensitive in localStorage | todo |
| Security: OTP email verification (10 min, single use, invalidated after success) | todo |
| Security: UUID/ULID (`HasUuids`) or slugs in public URLs, no sequential IDs | todo |
| Security: `APP_ENV=production`, `APP_DEBUG=false`, custom error pages (`errors/500.blade.php`), HTTPS enforced | todo |
| Security: `Password::defaults()` (min 8-12, mixed case, digits, symbols), Bcrypt/Argon2 hashing | todo |
| Security: strict upload validation (`mimes`, `file`, `max`), private storage, random file names | todo |
| GDPR: data minimisation, consent checkboxes + timestamps stored, privacy policy, personal data export, account deletion/anonymisation (scheduled command), `Crypt` for sensitive fields | todo |
| Prototype blog details page (`prototype_html/{fr,en}/articles/*`): share bar (mobile + desktop), newsletter box, share block, author box, prev/next pagination, comments box, ads sidebar, cloned from Foxiz | done 2026-10-28 in `tools/build_site.py` + `src.css` + `app.js` (64 FR + 64 EN pages rebuilt, site_check OK); to port to Blade partials |
| Prototype public pages Magazine, African Radios, African Television, Videos (data files, filters, JSON-LD; no more "Bientôt" block) | done 2026-10-28 in `build_site.py`; content is PROPOSED demo content (see `docs/PRD.md` EX-02) |
| Prototype admin modules: Analytics, Tags, Videos, Magazine, Radios, Television, Audit log, Backups; Settings > Analytics; ad clicks/CTR; public comment import; dashboard snapshot | done 2026-10-28 (Edge, 4 viewports, 74 scripted checks); analytics figures are synthetic |
| PRD (`docs/PRD.md`): 38 requirements with source and status, anomalies kept, open questions with named recipients | done 2026-10-28; owner decisions pending (Q1 to Q8) |
| Laravel port of the new modules (analytics source GA4 API or first-party log, audit table append-only, scheduled backups, tags, channel directories) | todo, blocked on PRD Q2, Q3, Q7 |
| Prototype "Explore by topic" popup (Foxiz template popup, top panel, template cloned on first open) | done 2026-10-28; port to a Blade partial + `<template>` |
| Implementation plan (phases, FR and NFR tables): `docs/IMPLEMENTATION-PLAN.md` | done 2026-10-28; phase 0 blocked on Q9 (owner checks host and PHP) |
| Tests and UAT | todo |

Notes: Livewire resolved to 3.8 (3.6 line requested, `^3.6` allows it). Vue bundle is 45 kB gzip; split i18n per island if it matters in Lighthouse.
