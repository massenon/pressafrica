# Africa Global Press (pressmedia_laravel)

Laravel port of the HTML prototype in `../prototype_html` (see `../docs/TECHNICAL.md`). Read this file first; do not re-read the specs.

## Stack (fixed)
Laravel 12.70, PHP 8.3, MySQL in production (SQLite locally), Sanctum 4, spatie/laravel-permission 8, mews/purifier, Livewire 3 admin (Tailwind, SweetAlert2, Chart.js, Quill, Choices, flatpickr), Vue 3 + Pinia + vue-i18n + TypeScript on the public site.

## Decision: public rendering = Blade + Vue islands (not Inertia SSR)
Why: news pages must be crawlable and fast (Lighthouse > 90, < 2 s) with no Node server in production. Blade renders the page; Vue mounts only on `[data-island]` elements (`resources/js/islands/*.vue`). Inertia 2 is installed and reserved for authenticated, interactive spaces (reader account), never for article or category pages.

## Navigation without reload: Hotwire Turbo Drive (owner decision 2026-10-28)
Public layout loads `@hotwired/turbo` (npm, bundled by Vite, ~26 kB gzip measured on the 8.0.23 build). Links swap the page body, hover prefetch is on, FR/EN article switch uses the same path. Livewire `wire:navigate` stays for the admin only. Do not use Inertia SSR (no Node on the host).
- Vue islands must mount on `turbo:load` and unmount on `turbo:before-cache`; document/window listeners and intervals must be removed on `turbo:before-render` (the prototype `app.js` does this with `__agpOff`).
- Do not mark the Turbo script `data-turbo-track="reload"` when its URL can differ per page: a changed tracked asset forces a full reload.
- Third-party scripts (GA4, Meta Pixel) load once, in `<head>`, behind consent; never re-inject them per visit.
- Move focus to `<main>` after each visit (a11y). Keep `<html lang>` in sync (Turbo copies it).

## Hosting: cPanel shared, no Node, no Redis (owner, 2026-10-28)
Build assets before deploy (`npm run build` locally or in CI), web root = `public/`, `CACHE_STORE=file` (or `database`), `SESSION_DRIVER=file` or `database`, `QUEUE_CONNECTION=sync` or `database` with a cron `queue:work --stop-when-empty`, cron `* * * * * php artisan schedule:run`. Enable gzip/brotli and long cache headers on `/build`.

## Rules
- Locales: `fr` (default) and `en`. Text lives in `*_translations` tables. URLs mirror the prototype (`/fr/...`, `/en/...`).
- Sanitise every HTML body with Purifier before saving. Never render user HTML unescaped otherwise.
- Permissions are `<module>.<action>` (`RolesSeeder`). Check with policies or `can()`, never by role name.
- Tailwind everywhere (owner decision 2026-10-28, public and admin). Keep the prototype look: tokens from `../prototype_html/assets/css/src.css`. No redesign.
- One module per session; finish with tests (Pest/PHPUnit) and update `docs/PROGRESS.md`.
- Comments explain why, not what. Match surrounding style.

## Commands
`php artisan migrate:fresh --seed`, `php artisan test`, `npm run dev`, `npm run build`.
