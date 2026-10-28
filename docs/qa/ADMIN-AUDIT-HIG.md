# Back-office audit — Apple HIG, frontend-design, ui-ux-pro-max (2026-10-26)

Scope: 12 admin pages (login excluded) at 1440 px and 390 px, Edge via Playwright, axe-core (WCAG 2.2 AA + best-practice).

## Result
Rating: **Good**. No Critical finding. 0 JS errors, 0 horizontal overflow, 0 text under 12 px, focus ring visible (3 px solid).

## Fixed in this pass
| # | Severity | Finding | Fix |
|---|---|---|---|
| 1 | Medium | Dashboard chart dots: `aria-label` on `<circle>` without a role (30 nodes, `aria-prohibited-attr`) | `role="img"` in `admin-dashboard.js` |
| 2 | Medium | Media library: `role="listitem"` on `<button>` (40 nodes, `aria-allowed-role`) | removed the list/listitem roles in `admin-media.js` (buttons keep their own semantics) |
| 3 | Low | Pager buttons and table checkboxes 24-36 px, under the 44 pt HIG target on touch | `@media (pointer:coarse)` raises them to 44 px / 28 px in `admin.src.css` |

After the fix: axe reports 0 violations on dashboard, media and posts.

## Left open (judgment, not blocking)
- Desktop checkboxes stay 24 px (meets WCAG 2.2 AA 2.5.8; dense tables). HIG default is 28 pt on desktop.
- Sidebar tag line "NEWSROOM CONSOLE" is tracked all-caps; frontend-design flags this as template chrome. Keep or switch to sentence case.
- Placeholder grey on "Post title" and "Dateline" not measured; check it is at least 4.5:1.
- Code comments: `admin-ads.js`, `admin-boot.js`, `admin-categories.js`, `admin-comments.js` have a single comment each. Header + section comments would meet the "well commented" criterion.

## Checklist from the request
| Criterion | Status |
|---|---|
| Neat, clean, simple | OK: one accent (yellow) on the primary action, quiet navy sidebar |
| Well commented code | Partial, see above |
| Major browser compatibility | Chromium (Edge) tested. Firefox and Safari not run in this pass |
| Clean, well-structured code | OK: one JS file per page, shared `admin-lib.js`, tokens in CSS variables |
| Easy customisation | OK: tokens in `:root`, light and dark |
| User-friendly | OK: empty states, toasts, focus trap in modals |
| Responsive | OK at 1440 and 390, no overflow |
| High-quality code | OK: 0 console errors, 0 axe violations |
