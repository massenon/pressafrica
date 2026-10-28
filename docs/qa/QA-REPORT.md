# QA Report - Africa Global Press prototype

Run date 2026-10-19. Browser: Microsoft Edge 155 via Playwright (file://), Python crawlers (BeautifulSoup). Scope: 171 public HTML files (fr 85, en 85, root redirect) + 13 admin pages. Raw data in `docs/qa/data/*.json`, screenshots in `docs/qa/shots/`, scripts in `tools/qa/`.
Admin was edited concurrently by another agent; admin results reflect the state at ~05:30 and should be re-run (`python tools/qa/admin_audit.py && python tools/qa/admin_flows.py`).

## Summary
| Severity | Count |
|---|---|
| Critical | 0 |
| High | 0 |
| Medium | 4 |
| Low | 12 |
| Info | 3 |

Headline: no console errors, no page errors, no failed/404 requests, no broken links or anchors, no absolute/`pressafrica.media` hrefs/srcs (obfuscated mailto only), all JSON-LD valid, canonical/hreflang pairs reciprocal on all 168 indexable pages, sitemap complete. 67 of 69 public functional checks passed (the 2 "fails" were test-script artifacts, re-verified manually as PASS/false positive); 41/42 admin checks passed (1 test-script artifact).

## Defects (severity-ranked)
| ID | Sev | Page | Viewport | Evidence | Suggested fix | Owner |
|---|---|---|---|---|---|---|
| D1 | Medium | admin/posts.html (also comments, newsletter, authors, ads) | posts: 1024 and below; others: 390 | Whole page scrolls horizontally: scrollWidth 1122 @1024, 842 @768/390 (posts); 536 comments, 643 newsletter, 604 authors, 499 ads @390. Cause: `table.a-table` (964px) is inside `.a-tablewrap{overflow:auto}`, but the wrapper's grid/flex parent has `min-width:auto`, so the wrapper grows instead of scrolling. | Add `min-width:0` (and `max-width:100%`) to the main/view column (`.a-main`, `#view`, `.a-view`) in `assets/css/admin.src.css`. | admin |
| D2 | Medium | fr/articles/science-et-technologie-linnovation-africaine-prend-corps-en-2025.html and en/articles/science-and-technology-african-innovation-takes-shape-in-2025.html | 390 | scrollWidth 521 > 390. Offender is one `<p>` in `.prose` ("Plusieurs pays africains progressent dan...") containing a long run of non-breaking / narrow-nbsp (U+202F) characters that cannot wrap. | `.prose p{overflow-wrap:anywhere}` in `src.css`; also normalise the NBSP run in the source data. | site |
| D3 | Medium | all public pages (header topbar, category chips) | all, worst 390 | WCAG 2.2 SC 2.5.8 (24px): language links 57x16 / 62x16; `.topbar-search` 21x36; `.chip` links 19-20px high (about 190 per sweep). | `min-height:24px` + padding on `.topbar a[data-lang]`, widen `.topbar-search` to 24px, `.chip{min-height:24px}` (or ensure 24px spacing circles). | site |
| D4 | Medium | admin posts/comments/newsletter/authors/ads tables, post-edit | all | Sort buttons in `th` are 17px high; row checkboxes 13x13; format radios 13x13; category collapse buttons 22x32 at 390; login "show password" checkbox 18x18. | Raise to >=24px (padding on `.a-sort`, wrap checkbox in 24px label hit area, `.a-iconbtn.sm` min-width 24px). | admin |
| D5 | Low | 48 EN article pages (hero `<img alt>`), 6 `og:image:alt` | all | Hero alt is the French title or filename, e.g. `AD Akinwumi Adesina, une gouvernance qui doit faire cas décole-1`, `Pauvreté : 4 Kenyans sur 10 touchés en 2025`. Alt has a typo/filename leftover on the ADF article. | Use the EN title as alt / `og:image:alt` on EN pages. | site |
| D6 | Low | en/privacy-policy.html, en/legal-notices.html | all | Untranslated: heading "3. Cookies et Technologies Similaires" (x2), accessibility paragraph "Africa Global Press s'engage à rendre son site web accessible à tous les utilisateurs...". | Translate the three strings. | site |
| D7 | Low | fr/politique-cookies.html (and EN equivalent if present) | all | "Sommaire" shows "1. 1. Qu'est-ce qu'un cookie ?" - `<ol list-style:decimal>` plus numbers already in the link text (see shots/cmp-cookies-0.png). | Strip leading "N. " from TOC text or use `list-style:none`. Same pattern for all 7 entries. | site |
| D8 | Low | fr/politique-de-confidentialite.html line 120, en/privacy-policy.html | all | `<a href="politique-cookies.html"></a>` empty link, no accessible name (axe "link-name"). | Fill link text (clearly intended: "Politique relative aux cookies") or remove. | site |
| D9 | Low | fr/index.html | 1440/1729 | Hero slide 1 is a generic brand slide ("Africa Global Press: Votre Source d'Information"); clone slide 1 is the BAD / Akinwumi Adesina article with "LIRE PLUS". Ticker label is "Flux Infos" (clone: "Flash Infos"). | Make slide 1 the lead article as in clone, or document the change. Rename label if 100% fidelity is wanted. | site |
| D10 | Low | all pages | 1440 | Container is 1240px max (design system); clone content width is about 90% of viewport (about 1550px at 1729). Home total height 4176px vs clone 5416px; nav items smaller than clone. Intentional per DESIGN-SYSTEM.md but visible. | Accept, or raise `.container-page` max-width to about 1440 on >=1600 viewports. | site |
| D11 | Low | fr/contact.html | 1729 | Clone: form takes about 2/3 width, socials box right and aligned with the form top. Ours: form spans full width and the social box is a short sidebar at the top; cookie overlays aside, the intro heading sits above the form instead of beside it (cmp-contact-0.png). | Align to a 2/3 + 1/3 grid below the page title. | site |
| D12 | Low | fr/qui-sommes-nous.html, category pages | 1729 | Added blocks not in clone: FAQ accordion; order "thematics -> search -> FAQ" vs clone "thematics -> search". Category lists show red category chips under every item (clone has none) and a numbered pager (clone pager has a "next" icon). | Keep if desired (value-add, FAQPage JSON-LD), note as intentional deviations. | site |
| D13 | Low | 105 pages (all article pages) | n/a | `<title>` longer than 70 chars (up to 105), truncated in SERPs. Home meta description is 171 chars (>160), also in root index.html. | Shorten to <=60 chars with the site suffix, home description <=155. | site |
| D14 | Low | fr/index.html, en/index.html | n/a | BreadcrumbList JSON-LD has 1 item with no `item` URL (valid but of no value). | Omit breadcrumb JSON-LD on home. | site |
| D15 | Low | footer, every page | n/a | Visible text "contact@pressafrica.media" (old domain, copied from clone) next to africaglobalpress@gmail.com; "[contact@pressafrica.media]" in cookies policy text. href is obfuscated mailto so no hot link. | Replace by a project-domain address, or confirm it is the real mailbox. | site |
| D16 | Low | home and category, when full-page captured / printed | n/a | `.reveal` blocks (search frame, editorial, 6 tiles) stay `opacity:0` until scrolled into view; a full-page capture without scrolling or print preview shows empty white areas (observed in first screenshot run). | Add `@media print{.reveal{opacity:1!important;transform:none!important}}` and make reveal degrade on no-IO (already handled). | site |
| D17 | Low | admin/posts.html | n/a | Sidebar badge "Posts 8" does not obviously match the list total (72); label meaning unclear. | Tooltip / aria-label "8 drafts". | admin |
| D18 | Info | EN site | n/a | 56 EN articles keep French bodies (confirmed: 56 `lang="fr"` wrappers inside `lang="en"`; 42 of their meta descriptions/OG descriptions are French). Intentional; correctly marked with `lang="fr"` and "Read this article in French". | None. | site |
| D19 | Info | sitemap.xml | n/a | 168 URLs; `fr/recherche.html` and `en/search.html` deliberately omitted. | None. | site |
| D20 | Info | all forms | n/a | Honeypot `input.hp[name=website]` has no label (aria-hidden, off-screen) - acceptable. | None. | site |

## Layout fidelity (fr/index.html and 12 other pages vs `clone/screencapture-*.png`, viewport 1729, side-by-side in `shots/cmp-*.png`)
| Page | Ref height | Ours | Result |
|---|---|---|---|
| home | 5416 | 4176 | Section order identical: topbar, logo band, black nav (A la Une, Podcasts, Videos, Presse en ligne + yellow "Explorer par thematiques" + S'abonner + theme switch), ticker, hero slider, Dernieres Actualites (lead + 2 + 3 text), Actualites economiques (2 large + 4 small + pager), search frame with country shortcuts, editorial block + newsletter CTA, 6 tiles, black footer with yellow divider. Deviations D9, D10. |
| societe / finance / presse-en-ligne | 2950 | 2587 | Lead story + side card, wave divider, list with yellow newsletter card, pager. Ours adds chips and FAQ (D12). |
| politique / economie / culture / sport | 3830 / 4323 / 3172 / 2622 | 3277 / 3277 / 2802 / 2389 | Same structure. |
| contact | 2274 | 1652 | Fields identical (Prenom, Nom, Entreprise, E-mail, Telephone, Pays, Ville, Message, 2 consents, submit); D11. |
| mentions / privacy / cookies | 2796 / 4686 / 2959 | 2824 / 4831 / 3227 | Same copy; ours adds Sommaire (D7). |
| about | 3473 | 2984 | Same blocks; mission/values accordion list and tiles reordered slightly (D12). |

Menus (verified in DOM, FR and EN): topbar Société, Politique, Finance, Culture, Science et technologie + Français/Anglais + Rechercher + Se connecter; main nav À la Une, Podcasts, Vidéos, Presse en ligne (each with "Afficher plus" mega menu); footer SPORTS / CULTURE / FINANCE / POLITIQUE / SCIENCE ET TECHNOLOGIE and Qui sommes-nous?, Nous contacter, Mentions légales, Politique de confidentialité, Politique relative aux cookies. All match the clone ("A la Une" is correctly accented). Offcanvas holds the clone's mobile items (Magazine, Radios africaines, Télévision africaine, Presse en ligne) plus Podcasts, Vidéos, thematics (extra Économie, Santé & Bien-être - addition).

## Checklist
| Check | Result |
|---|---|
| Console errors / page errors, 171 public pages x 4 viewports | PASS (0) |
| Failed or 4xx requests | PASS (0) |
| Broken internal links / anchors / images | PASS (0) |
| href/src to pressafrica.media or other absolute hosts | PASS (only 4 social profile links with rel=noopener) |
| Alt text on all images, dimensions set (CLS) | PASS (all `<img>` have alt, width, height) |
| One H1 per page, no heading skips | PASS public; admin renders one H1 per page after JS |
| Unique title and description per language | PASS (root index.html duplicates fr/index.html description by design) |
| Canonical = self, hreflang fr/en/x-default reciprocal, og:url = canonical | PASS 168/168 |
| OG / Twitter complete, og:image file exists, og:locale matches lang | PASS |
| JSON-LD parses and has required fields (NewsArticle 128, FAQPage 30, ContactPage 2, WebSite, Organization, BreadcrumbList) | PASS |
| Sitemap coverage + dead URLs | PASS (168/168, search pages excluded) |
| Lazy loading / srcset / hero preload with fetchpriority | PASS |
| Image weight | PASS: 17.1 MB total, largest delivered image 309 KB (`vie-des-burundais-1-1280.webp`) |
| CSS / JS budget | PASS: app.min.css 53 KB, app.js 26.5 KB, 6 self-hosted variable woff2 fonts (about 175 KB), 2 preloaded, `font-display:swap`; no render-blocking JS in head |
| Contrast (light and dark key pairs) | PASS: yellow pill 17:1, primary button 12.4:1, chip 4.77:1, meta 6.7:1, dark meta 7.8:1; only overlay text on images could not be auto-measured |
| Focus visible, skip link first stop, tab order nav -> main | PASS (3px solid outline, "Aller au contenu principal" first) |
| Offcanvas: focus trap, Esc closes, focus returns, aria-expanded | PASS |
| Mega menus: hover, click, Esc, in viewport 1440/1024 | PASS |
| Hero slider next/prev/arrow keys, pause, reduced motion (no infinite animations) | PASS |
| Dark mode toggle + persistence | PASS |
| FR <-> EN switch lands on equivalent page (home, society, contact, about, cookies, article) | PASS 12/12 |
| Search overlay + results page (FR/EN, no-result message, highlighting, Esc, focus return) | PASS |
| Contact form: empty -> errors + focus first invalid, bad email flagged, valid -> success; EN error messages in English | PASS |
| Cookie banner: shows, customise, accept/reject persist, not reshown, reopen from footer | PASS |
| FAQ accordions: aria-expanded, aria-controls, keyboard | PASS |
| Newsletter modal invalid email | PASS |
| No horizontal scroll 1440/1024/768/390 (home, article, category, contact, EN home) | FAIL only D2 (science article); everything else PASS |
| Burger/offcanvas visible <1024 | PASS (burger at 768 and 390; "Explorer" pill at >=1024) |
| Touch target >= 24px (WCAG 2.2 AA) | FAIL (D3, D4) |
| Admin: auth guard, login errors, demo login, dark mode | PASS |
| Admin: posts quick-add, validation, toast, persistence, delete + confirm + Undo toast, sort, search, empty state | PASS |
| Admin: editor 8 formats, quiz builder add question/answer + live preview + answer feedback, empty-title save lists issues | PASS |
| Admin: AI writer generate/stop/finish/insert (aria-busy, live region) | PASS |
| Admin: categories create + empty-name rejection, 9 other pages render without errors, role switcher (5 roles) | PASS |
| Admin: no horizontal page scroll | FAIL (D1) |
| Admin: mobile burger + aria-expanded | PASS |
| EN completeness | PASS with D5, D6 |

## EN completeness detail
Scan of 85 EN files (`tools/qa/en_french_scan.py`): menus, nav, offcanvas, footer, forms, errors, cookie banner and JSON-LD of EN pages are all English. Remaining French: D5 (48 hero alts + 6 og:image:alt), D6 (3 strings). 56 EN articles intentionally keep a French body (marked `lang="fr"`).

## Not covered / limits
Real Lighthouse and axe-core were not run (not installed offline); equivalent proxies above. Service worker not testable over file://. Admin tests ran against localStorage demo data. 390px full-page screenshot of home is in `shots/resp-home-390.png` (reveal sections appear blank without scroll, see D16).

## Scripts (tools/qa)
`static_audit.py` (SEO/links/JSON-LD/sitemap/images), `browser_audit.py` (console/network/overflow/targets on all pages x 4 viewports; note admin pages without session redirect to login, use admin_audit), `compare_layout.py` (clone vs ours), `functional_public.py`, `responsive_shots.py`, `admin_audit.py`, `admin_flows.py`, `summ_admin.py`, `en_french_scan.py`. Run with `python tools/qa/<script>.py` (Edge required, Playwright 1.63, bs4, Pillow).

---

# Round 2 (re-verification after owner fixes)

Method: same scripts (`browser_audit.py` at 1440/390/320 on all 184 pages, `static_audit.py`, `admin_audit.py` at 5 viewports, `admin_flows.py`, new `round2_checks.py`, new `axe_audit.py`), plus Lighthouse 12 and axe-core. Raw: `docs/qa/data/browser_r2.json`, `axe.json`, `r2_meta_lengths.txt`.

## Status of round-1 defects
| ID | Status | Evidence |
|---|---|---|
| D1 admin sideways scroll | FIXED at 1024/768/390 on all 12 admin pages. New minor residual R2-6 at 320. | admin_audit: no overflow at 1440-390; at 320 scrollWidth 326 (topbar `.a-user-btn`). |
| D2 science article overflow | FIXED (FR+EN, 1440/390/320 sw = viewport). Crawl of all public pages at 1440/390/320: 0 overflow, except home 1px at 320 (R2-7). | |
| D3 public target size | MOSTLY FIXED: lang links, search button, chips now >=24px (only 7 pages show any small target at 390). Residual R2-2. | |
| D4 admin target size | FIXED (no sub-24px controls flagged on any admin page). | |
| D5 EN alt / og:image:alt | FIXED. 5 remaining hits are proper nouns (Côte d'Ivoire, Sékou, São Tomé, Zémidjan) - correct. One leftover French word: "Burkina Faso/Économie" in EN alt/og alt (R2-8, Low). | |
| D6 EN privacy/legal French | FIXED (0 French strings on privacy, legal, cookie pages). | |
| D7 cookies TOC | FIXED (no duplicate numbers). | |
| D8 empty link | FIXED (0 empty anchors in fr/en pages). | |
| D9 hero slide 1 / ticker | FIXED: slide 1 = "BAD : Akinwumi Adesina, une gouvernance qui doit faire cas d'école", ticker "Flash Infos". | |
| D13 titles / descriptions | PARTLY FIXED. Titles >70 characters (measured in characters): 6 left, all 71-73: fr/articles/djibouti-risques-a-eviter-par-les-investisseurs.html (71), en/articles/africa-drug-addiction-a-multidimensional-crisis.html (71), en/articles/fagace-ceo-yambaye-insists-he-is-not-a-careerist.html (71), en/articles/hdi-2025-south-africa-a-giant-with-feet-of-clay.html (71), en/articles/mauritania-exports-soon-to-drive-economic-growth.html (71), index.html (73). Descriptions outside 70-160: fr/index.html 171, index.html 171, en/index.html 161. | Still open (Low). |
| D14-D17 | not re-tested individually; D14 still present (home breadcrumb 1 item). | |

## Regression crawl (171 public pages)
Console errors 0, page errors 0, failed requests 0, broken images 0 at 1440/390/320. Static audit: 0 broken links/anchors, 0 absolute hosts, 0 missing alt, 0 invalid JSON-LD, canonical/hreflang/sitemap unchanged (all PASS). Admin flows: 42/42 PASS after fixing the test selector (see below).

## New / open defects
| ID | Sev | Page | Viewport | Evidence | Suggested fix | Owner |
|---|---|---|---|---|---|---|
| R2-1 | High (a11y) | every public page | <=~480 px | axe `button-name` critical x2: `button.topbar-search` and `button.topbar-login` have an SVG only (label text hidden on mobile) and no aria-label. Lighthouse also fails `button-name` on fr/index.html (accessibility 92). Same on admin pages: `.a-user-btn` unnamed at 390 (axe, admin/posts.html, post-edit.html). | Add `aria-label` (Rechercher / Se connecter / account menu) to the buttons, or keep a visually-hidden text span. | site, admin |
| R2-2 | Medium | all public pages, 1440 | desktop; mobile ticker | axe `target-size` serious: `button.nav-chev` is 22x44 (684 hits over 171 pages, 4 per page); `.icon-btn-sm` ticker prev/pause/next < 24 at 390; home `a.tile-name` 17px high. Lighthouse `target-size` fails on home. | `.nav-chev{min-width:24px}`; ticker buttons min 24x24; `.tile-name{display:inline-block;padding-block:4px}`; consent checkboxes in contact are 18x18 (give them a 24px label hit area). | site |
| R2-3 | Low | admin/post-edit.html | all | axe `aria-prohibited-attr`: `div.a-stream[aria-label]` has no role. | Add `role="region"` (or `role="log"`) or drop the aria-label. | admin |
| R2-4 | Low | all public pages | all | axe `region`: `a.back-top` is outside a landmark. | Put the back-to-top link inside `<footer>`/`<main>`. | site |
| R2-5 | Medium | fr/articles/*.html (probe: afrique-antiterrorisme... and tiktok article) | Lighthouse emulated mobile, headless | Lighthouse run fails with NO_FCP twice on article pages (home paints fine, performance 80 / accessibility 92 / best-practices 100 / SEO 100; LCP 4.6 s on throttled mobile, CLS 0, TBT 80 ms). Playwright renders the same pages fine, so probably the page is blank until a script/IntersectionObserver adds a class (reveal), or a hidden initial state. Needs a look with Chrome devtools throttling. | Make sure first paint never depends on JS (`.js .reveal` only below the fold; main title and lead image visible at load). | site |
| R2-6 | Low | admin, all pages | 320 | scrollWidth 326: `.a-user-btn` in topbar overflows by 6px. | Shrink gap or hide user name under 360px. | admin |
| R2-7 | Low | fr/index.html, en/index.html, index.html | 320 | scrollWidth 321 (1px). | Find the 1px (likely `.hero` margin or ticker) and clip. | site |
| R2-8 | Low | en/articles/burkina-faso-economie-between-resilience-and-persistent-fragilities.html | all | EN alt and og:image:alt say "Burkina Faso/Économie". | "Economy". | site |
| R2-9 | Low | admin/login.html | all | Lighthouse: performance 81 (LCP 4.1 s, CLS 0.141), accessibility 100, best-practices 96 (404 in console is `favicon.ico` requested over http; admin has no favicon link), SEO 45 is expected (noindex). | Add an admin favicon `<link>`; reserve space to remove CLS 0.141. | admin |

Lighthouse and axe: both ran (Lighthouse via `npx lighthouse` against a local `http.server`, Edge as Chrome; axe-core 4 injected with Playwright). Scores: fr/index.html perf 80, a11y 92, BP 100, SEO 100; admin/login.html perf 81, a11y 100, BP 96, SEO 45 (noindex by design); article pages: Lighthouse could not complete (R2-5). admin/posts.html needs a session so Lighthouse cannot load it; axe on it at 1440 is clean, at 390 only R2-1.

## Script notes
- `admin_audit.py` "stopped after 7 PASS lines" was not a crash: it has exactly 7 flow lines (3 login checks, dashboard landing, dark mode, posts rows, toolbar) before writing `admin.json`; all page sweeps print only `vp N`. Now also covers 320. Detailed flows are in `admin_flows.py`.
- `posts-search-empty-state` was a selector bug: the script typed into the first `input[type=search]` (the global topbar search) instead of `#view input[type=search]`; fixed and re-run, now PASS (42/42).

---

# Round 3 (final)

Data: `docs/qa/data/browser_r3.json`, `axe.json`, `layout.json`; Lighthouse 12 (gzip server `scratchpad/gzsrv.py`, mobile + desktop). Scripts changed: `functional_public.py` (contact success check now reads the visible `.form-ok`), `axe_audit.py` (adds category page and admin/login.html).

## Verification of round-2 items
| ID | Status | Evidence |
|---|---|---|
| R2-1 accessible names | FIXED | axe (wcag2a/aa, 2.1 AA, 2.2 AA, best-practice) at 1440 and 390 on fr/index, en/index, an article, fr/societe, fr/contact, admin/login, admin/posts, admin/post-edit (logged in through the mock session): 0 `button-name`, 0 `target-size`, 0 `aria-prohibited-attr`, 0 `region` on public/admin posts. |
| D3 target sizes | FIXED except 3 text links | Only residual sub-24px: three home-page article title links 23px high (fr/index, en/index, index.html: e.g. "Égypte : la franc-maçonnerie..." 313x23, "Afrique: les stars..." 240x23). Everything else (nav chevrons, ticker, chips, tiles, consent boxes) >=24. Admin: none at any viewport. Fix: `min-height:24px` on those `.card-text a` (site). |
| D13 titles/descriptions | FIXED | All 171 pages: title <=70 characters, description 70-160 characters (script in round 2 listing now prints nothing). |
| 320 px overflow | FIXED | Public 171 pages x 320: 0 overflow incl. home 1px; admin 12 pages x 320: 0 overflow (R2-6 fixed). |
| R2-9 admin login CLS / favicon | PARTLY | Favicon 404 gone (best-practices 100). CLS on mobile is still 0.141 (desktop 0.013). |
| Burkina EN alt | FIXED | alt reads "Africa Global Press"/English ("Illustration for the article: ..."); remaining accented words are proper nouns (Côte d'Ivoire, Zémidjan, Sékou, São Tomé). |
| R2-5 article NO_FCP | FIXED | Article now scores perf 100 desktop / 97 mobile. |
| R2-3 / R2-4 / R2-7 / R2-8 | FIXED | no axe hit; 1px overflow gone. |

## Full crawl, all 184 pages at 1440 / 390 / 320
Console errors 0, page errors 0, failed or 4xx requests 0, broken images 0, horizontal overflow 0. Static audit: 0 broken links/anchors, 0 absolute hosts, 0 missing alt, 0 invalid JSON-LD, canonical/hreflang/sitemap clean. Only informational static findings remain: breadcrumb JSON-LD of 1 item on the two home pages, honeypot input unlabeled (aria-hidden), search pages absent from the sitemap by design.

## Interaction suites
Public suite (`functional_public.py`): 68/69 PASS; the one FAIL (contrast sweep) is a known false positive: yellow author name over the dark image gradient of the lead card (measured against the white page background). `admin_flows.py`: 42/42 PASS. `admin_audit.py`: all 12 pages x 5 viewports render with no console errors, no overflow, no unnamed controls, no sub-24px controls.
New axe note: `color-contrast` serious on `<strong>Business Africa</strong>` (home tile caption) was reported on fr/index@390 and en/index@390/1440 during the scripted run; it is intermittent (reveal fade-in in progress at measurement time) and does not appear in Lighthouse (accessibility 100 everywhere). Worth one manual check (R3-1, Low, site).

## Lighthouse (mobile / desktop)
| Page | Perf | A11y | Best practices | SEO | LCP | CLS |
|---|---|---|---|---|---|---|
| fr/index.html | 93 / 99 | 100 / 100 | 100 / 100 | 100 / 100 | 2.8 s / 1.0 s | 0 / 0.015 |
| en/index.html | 96 / 99 | 100 / 100 | 100 / 100 | 92 / 92 | 2.3 s / 0.9 s | 0 / 0 |
| fr article | 97 / 100 | 100 / 100 | 100 / 100 | 100 / 100 | 2.2 s / 0.4 s | 0 / 0.007 |
| fr/societe.html | 92 / 100 | 100 / 100 | 100 / 100 | 100 / 100 | 3.1 s / 0.6 s | 0.017 / 0.011 |
| admin/login.html | 92 / 100 | 100 / 100 | 100 / 100 | 45 / 45 (noindex by design) | 2.4 s / 0.5 s | 0.141 / 0.013 |
en/index SEO 92: Lighthouse `link-text` fails on the "READ MORE" link of the hero slide (non-descriptive; R3-2, Low, site: add visually hidden article title or aria-label).

## Layout regression vs clone (home, societe, contact, about; also 9 others in `shots/cmp-*.png`)
Section order, topbar, logo band, black nav, ticker (now "Flash Infos"), hero (slide 1 = BAD article, "LIRE PLUS"), Dernières Actualités, Actualités économiques, search frame, editorial block, 6 tiles and footer are identical in order to the clone; copy matches. Menu items verified in DOM on 4 pages: topbar (Société, Politique, Finance, Culture, Science et technologie), main nav (À la Une, Podcasts, Vidéos, Presse en ligne), footer legal five links, offcanvas 14 links. Page heights are still 15-30% shorter than the clone (home 4208 vs 5416) because of the 1240px container; accepted design-system deviation (D10). No regression after the performance work.

## Go / No-go
| Area | Decision |
|---|---|
| Public site (171 pages) | GO |
| Admin back-office (13 pages) | GO |
| SEO / metadata / structured data | GO |
| Accessibility (axe, Lighthouse 100) | GO |
| Performance (Lighthouse 92-100) | GO |
| Layout fidelity vs clone | GO (documented deviations D10-D12) |

## Still open (none blocking)
| ID | Sev | Item | Owner |
|---|---|---|---|
| R3-1 | Low | Verify `Business Africa` tile caption contrast after reveal animation | site |
| R3-2 | Low | "READ MORE" link text on hero slide (EN) not descriptive | site |
| R3-3 | Low | 3 home title links 23px high | site |
| R2-9b | Low | admin/login.html mobile CLS 0.141 (reserve space for card/fonts) | admin |
| D14 | Info | home BreadcrumbList with 1 item | site |
| D10-D12 | Info | container width, contact layout, extra FAQ/chips (intentional) | site |

---

# Round 4 (final, after EN translations)

Scope: 171 public pages (fr 85, en 85, root index) + 13 admin pages; EN now has 64 real article pages paired with FR originals. Scripts: `tools/qa/*` (axe target list updated); raw outputs were written outside the repo (session scratchpad) to keep the docs tree read-only.

## Crawl (920 page loads: 184 pages x 1440 / 1024 / 768 / 390 / 320)
Console errors 0, page errors 0, failed or 4xx requests 0, broken images 0, horizontal overflow 0, h1 != 1: 0 (admin pages included), controls under 24px: 0, missing focus style: 0.

## Static audit (171 public pages)
Titles all present, <= 70, unique; descriptions all 70-160; only duplicate is the root `index.html` vs `fr/index.html` (same description, intentional redirect copy). One H1, no heading skips, alt AND title on every img, 0 absolute hosts (pressafrica.media, wp-content), 0 invalid JSON-LD, NewsArticle `inLanguage` = en on all 64 EN articles and fr on FR ones, canonical self-referencing, hreflang fr/en reciprocal on every pair, sitemap coverage complete (only search pages absent, by design). Informational as before: breadcrumb of 1 item on both home pages, honeypot input without label (aria-hidden).

## EN leftover-French detection
Stopword detector (text blocks, title, description, og/twitter metas, alt, title, aria-label) over 85 EN pages: 0 French UI strings, 0 French metas, 0 French bodies. The legacy scanner's 173 hits are all false positives ("Manage cookies", "Essential cookies" contain the token `cookies`). One proper-noun hit: "Place de la Nation" in N'Djamena (chad-governance article alt / og:image:alt), legitimate.

## Translation spot-check (all 64 pairs compared, 8 read in detail)
EN/FR block counts equal on 62 of 64; the other 2 (africa-air-transport 23 vs 27, tanzania 18 vs 19) have extra headings or list items in EN, body length ratio EN/FR 0.83-0.95 (median 0.90, normal for FR to EN), 0 truncated bodies (every paragraph ends with terminal punctuation or is a heading), 0 per-character newline corruption, 0 replacement characters or mojibake, bylines translated (Writer / Reporter), dates localised (e.g. "6 November 2025"). Detailed: ethiopia-finance, ecowas, africa-smes, mauritius, cotonou-zemidjan, tunisia, angola, africa-stars. One cosmetic: a literal `&#46;` entity appears once in each EN article HTML (renders correctly as a period).

## Interaction suites
Public suite 68/69 PASS; the single FAIL is the known false positive (yellow author name over image gradient, measured against white). admin_flows 42/42 PASS. admin_audit: 12 pages x 5 viewports, no console errors, no overflow, no unnamed controls, no small controls.

## axe-core (wcag2a/aa/21aa/22aa + best-practice), 1440 and 390
fr/index, en/index, EN article, en/society, fr/societe, fr/contact, en/contact, admin/posts (after mock login): 0 violations at both widths. admin/login: 1 moderate `region` (3 nodes, brand block outside a landmark; unchanged since R2, best-practice only).

## Lighthouse (gzip server, median of 3 runs, mobile / desktop)
| Page | Perf | A11y | BP | SEO | LCP | CLS | TBT |
|---|---|---|---|---|---|---|---|
| fr/index | 81 / 98 | 100 / 100 | 100 / 100 | 100 / 100 | 3.0 s / 1.0 s | 0 / 0 | 498 / 98 ms |
| en/index | 83 / 98 | 100 / 100 | 100 / 100 | 100 / 100 | 2.4 s / 1.0 s | 0 / 0 | 526 / 99 ms |
| EN article (cape-verde) | 89 / 100 | 100 / 100 | 100 / 100 | 100 / 100 | 2.4 s / 0.6 s | 0 / 0 | 366 / 59 ms |
| en/society | 88 / 99 | 100 / 100 | 100 / 100 | 100 / 100 | 3.0 s / 0.7 s | 0.001 / 0 | 307 / 73 ms |
| admin/login | 97 / 100 | 100 / 100 | 100 / 100 | 45 / 45 (noindex by design) | 2.5 s / 0.6 s | 0 / 0 | 44 / 0 ms |
Closed since R3: en/index SEO 92 -> 100 (R3-2), admin/login mobile CLS 0.141 -> 0 (R2-9b), small home title links (R3-3: 0 controls < 24px in the crawl).
New: mobile performance on home and listing pages dropped from 93-96 (R3) to 81-88. Cause is main-thread Style & Layout (about 1.6 s vs 0.6 s under 4x throttle) and app.js evaluation, with unchanged byte weight (686 KiB) and unchanged app.js; `app.min.css` and `fr/index.html` were modified after R3. A re-run on an otherwise idle benchmark (index 2650-2900) gave 79-81, so it is not noise (R4-1).

## Layout fidelity vs clone (home, societe, contact, about; also finance, presse-en-ligne, politique, economie, culture, sport, legal pages)
Section order, topbar and main nav items, ticker, hero, "Dernieres Actualites", "Actualites economiques", search frame, editorial block, 6 tiles, footer identical in order to clone. Heading and copy diff: only clone typos (`acess`, `arlets`), clone dynamic date, and prototype extras (FAQ, "A propos", legal block, newsletter) differ. Page heights unchanged vs R3 (home 4208 vs 5416, accepted D10). No regression.

## Business Africa tile caption contrast (R3-1, closed)
Computed after the reveal animation completes (about 1 s): dark mode rgb(237,237,237) on rgb(11,11,11) = 16.81:1, light mode rgb(40,40,40) on white = 14.74:1, same for FR "Business Afrique", at 1440 and 390. The earlier axe hit was measured mid-fade (figure opacity 0.9 at 300 ms). Not a defect. Evidence: `shots/r4-business-africa-dark.png`, `r4-business-africa-light.png`, `r4-tiles-390.png`.

## Go / No-go
| Area | Decision |
|---|---|
| Public site FR (85 pages) | GO |
| Public site EN (85 pages, 64 translated articles) | GO |
| Admin back-office (13 pages) | GO |
| SEO / metadata / hreflang / JSON-LD / sitemap | GO |
| Accessibility (axe 0, Lighthouse 100) | GO |
| Translation quality (no leftover French, no truncation) | GO |
| Performance (desktop 98-100; mobile 81-97) | GO with condition: investigate R4-1 |
| Layout fidelity vs clone | GO (documented deviations D10-D12) |

## Still open
| ID | Sev | Item | Owner |
|---|---|---|---|
| R4-1 | Medium | Mobile Lighthouse perf 81-89 on home/listing (was 93-96): Style & Layout 1.6 s, TBT 300-530 ms; diff `app.min.css` / home markup changed since R3, check content-visibility, reveal observers, layout thrash in app.js | site |
| R4-2 | Low | 2 EN articles (air transport, tanzania) have 1-4 more blocks than FR (headings/list items split); verify intent | content |
| R4-3 | Info | Stray `&#46;` entity once per EN article (renders fine) | build |
| R2-x | Info | admin/login axe `region` moderate | admin |
| D14 | Info | home BreadcrumbList with 1 item | site |
| D10-D12 | Info | container width, contact layout, extra FAQ/chips (intentional) | site |

# Round 5 (after design/perf fixes)

Scripts added: `tools/qa/favicon_check.py`, `favicon_render.py`, plus r5 scripts below. Screenshots: `docs/qa/shots/r5-*`.

## 1. Favicon (PASS)
184 HTML files (171 public incl. root index + 13 admin): every one has ico (48), svg, 32x32, 16x16 and apple-touch-icon links; 0 hrefs fail to resolve on disk. Manifest icons resolve with true dimensions 192, 512, maskable 512, plus svg; sw.js CORE precaches svg/ico/192. Rendered at 16/32 (and scaled) on light and dark tab backgrounds: globe+Africa mark stays legible, light-blue fill reads on both; evidence `shots/r5-favicons.png`. Network 404s are covered by the crawl (item 6).

## 2. Layout regression and the 4176 vs 4208 delta (PASS, no Round 5 regression)
Script `tools/qa/r5_layout.py` (full-page shots after scrolling the page so content-visibility sections are laid out): fr/index, en/index, fr/societe, article cap-vert, fr/contact at 1440 and 390, `shots/r5-<page>-<w>.png`, side-by-side `shots/r5-cmp-home.png` (clone left, ours right). Section geometry equals the R4 baseline (`data/layout.json`) to the pixel: home 4208 (header 226 incl. topbar 37 + logo band 137 + nav 52, ticker 49, hero 418, content 3035, footer 360), societe 2596, contact 1667; home 390 = 7521 before and after (site agent's before/after PNGs both 4208 and 7521). Section order, nav, ticker, hero, Dernieres Actualites, economiques, search frame, editorial, tiles, footer unchanged vs clone.
- 4176 vs 4208: 4176 is the Round 1 measurement (D10). 4208 is already recorded in R3 and R4 reports and in `layout.json`, so the +32px was introduced between R1 and R3 (R2 fixes: "Flash Infos" ticker, hidden h1, enlarged targets), not by the design/perf fixes: the pre-fix and post-fix home are both exactly 4208. The R1 per-element geometry was not stored, so the exact element cannot be proven from data; it is invisible in any case (32px on 4200, no reordering).
- NEW finding R5-1 (Medium): `content-visibility:auto` uses a single `contain-intrinsic-size: auto 600px` for `.search-frame`, `.editorial`, `.tiles`. Real heights at 1440 are 222 / 357 / 666 px; at 390 they are 333 / 693 / 1976. Before the user scrolls, document height is 4832 (1440) and 6379 (390) instead of 4208 / 7521, so the scrollbar thumb and anchor/scroll position jump by up to 1142px as sections render (mobile grows by 1142px, a scroll-jump hazard when jumping to footer or using back-navigation). Fix: per-section intrinsic sizes (`auto 222px / 357px / 666px` desktop, `auto 333px / 693px / 1976px` mobile) or drop it for sections near the footer. Final laid-out heights are correct, so this is not a visual layout change.

## 4. Re-run of suites and axe (PASS)
- Public interaction suite `functional_public.py`: 68 PASS / 1 FAIL (69 checks; the "28" quoted in the brief is the old count). The FAIL is the known false positive `contrast-sweep-home-light` (yellow `b.author` over the hero image gradient measured against white), unchanged since R3. reduced-motion check passes (0 infinite animations), focus-visible passes.
- `admin_flows.py`: 42 / 42 PASS.
- `admin_audit.py` (full output read, run twice): 13 pages x 5 viewports (1440/1024/768/390/320) = 65 page-viewport combos, 0 console errors, 0 horizontal overflow, 0 unnamed controls, 0 controls under 24px, one h1 on each; guard redirect, invalid / unknown-account errors, demo login to dashboard, dark toggle, posts list 10 rows and toolbar buttons all PASS.
- axe-core (wcag2a/aa/21aa/22aa + best-practice) at 1440 and 390 on fr/index, en/index, EN article cape-verde, en/society, fr/societe, fr/contact, en/contact, admin/login, admin/posts, admin/post-edit: 0 violations on all 20 runs (admin/login `region` moderate from R2 is now closed). `axe_audit.py` now includes admin/post-edit.

## 3. 200% text zoom (root font-size 200%) at 1440 and 720 css px (= browser zoom 200%) (PASS with 2 findings)
Script `tools/qa/r5_zoom_media.py` on home, article, contact, admin/login, admin/posts, shots `shots/r5-zoom200-*.png`; text-clipping probe finds 0 clipped text nodes (only `.a-vh` visually-hidden helpers). Browser-zoom equivalent (720 css px, normal root size): no horizontal scroll on any of the 5 pages, text reflows to the mobile layout. Text-only 200% at 1440: no horizontal scroll, no overlaps in content, admin login and posts usable. Findings:
- R5-2 (Medium-Low, public): with text-only 200% at 1440 / 1280 the topbar row (categories + language + search + "Se connecter") does not wrap, the login button ends 83px beyond the viewport (right edge 1523 vs 1440) and is cut off (`shots/r5-zoom200-home-1440.png`). Fix: let `.topbar-in`/`.topbar-right` wrap or hide topbar categories under a rem-based breakpoint (`min-width: 90rem`) instead of px.
- R5-3 (Low, admin): with text 200% AND 720px viewport (equals 400% effective), admin/posts has a page-level scroll of 841px: the topbar user button ("Pressafrica Media" + chevron) overflows by 121px. Not reproduced at normal 720px or text 200% at 1440. Fix: hide user name (`sm:inline`) under a rem breakpoint.
- Posts table at 200% text crops the Date and Views columns inside its own scroll container (expected, scrollable region).

## 4b. Emulated media (PASS)
`shots/r5-forced-colors-{home,admin-posts}.png`, `r5-contrast-more-*`, `r5-reduced-motion-*`: forced-colors (matchMedia true) renders system colors, borders on buttons/inputs/cards, focus ring and hero title on a Canvas box stay readable, no overflow; prefers-contrast:more gives solid borders, no layout shift (scrollWidth 1440); reduced-motion has no infinite animations (suite check) and the admin/home screenshots are identical in layout. Admin sidebar active item in forced-colors is outlined (visible).

## 5. Lighthouse (gzip server on 127.0.0.1:8767, median of 3 runs, mobile / desktop)
Script `tools/qa/lh3.sh` (raw runs kept in the session scratchpad, 30 runs).
| Page | Perf | A11y | BP | SEO | LCP | CLS | TBT |
|---|---|---|---|---|---|---|---|
| fr/index | 88 / 99 | 100 / 100 | 100 / 100 | 100 / 100 | 2.8 s / 0.9 s | 0 / 0.015 | 240 / 0 ms |
| en/index | 93 / 98 | 100 / 100 | 100 / 100 | 100 / 100 | 2.6 s / 0.9 s | 0 / 0 | 126 / 33 ms |
| fr/societe (listing) | 89 / 100 | 100 / 100 | 100 / 100 | 100 / 100 | 3.1 s / 0.7 s | 0 / 0.011 | 218 / 1 ms |
| EN article cape-verde | 96 / 100 | 100 / 100 | 100 / 100 | 100 / 100 | 2.6 s / 0.5 s | 0 / 0.006 | 133 / 0 ms |
| admin/login | 98 / 100 | 100 / 100 | 100 / 100 | 45 / 45 (noindex, by design) | 2.4 s / 0.5 s | 0.006 / 0.003 | 0 / 0 ms |
Versus R4: mobile perf fr/index 81 -> 88, en/index 83 -> 93, listing 88 -> 89, EN article 89 -> 96, admin/login 97 -> 98; TBT 498 -> 240 ms (fr home), 526 -> 126 ms (en home). R4-1 is substantially closed (content-visibility, no mobile backdrop-filter). Residual: fr/index mobile 88 (TBT 240 ms) and one noisy listing run at 77; fr/index and listing have a small desktop CLS 0.011-0.015 (and one 0.024 mobile run on the article) that was 0 in R4. Probable cause is R5-1 (600px placeholder sizes of `content-visibility:auto` sections replaced by the real heights as they render); all values far below the 0.1 threshold.

## 6. Crawl smoke test (PASS)
`browser_audit.py --vp 1440,390 --out browser_r5.json`: 184 pages (171 public + 13 admin) x 2 viewports = 368 loads (file://, favicon requests included): console errors/warnings 0, failed or 4xx requests 0 (so no favicon, manifest-icon or font 404), broken images 0, horizontal overflow 0, h1 != 1: 0, controls under 24px 0, missing focus style 0.

## 7. Go / No-go (Round 5)
| Area | Decision |
|---|---|
| Favicon / icons (184 pages, manifest, sw) | GO |
| Layout fidelity vs clone (home 4208, societe 2596, contact 1667; mobile home 7521; unchanged from R4) | GO |
| Accessibility (axe 0 on 20 runs, LH 100, forced-colors / contrast / reduced-motion) | GO |
| 200% text zoom | GO with conditions R5-2 (public topbar), R5-3 (admin, low) |
| Interaction suites (public 68/69 known false positive; admin_flows 42/42; admin_audit clean) | GO |
| Crawl (368 loads) | GO |
| Performance (mobile 88-98, desktop 98-100, CLS <= 0.024) | GO; R4-1 mostly closed |
| Public site / Admin back-office | GO |

## Still open after Round 5
| ID | Sev | Item | Owner |
|---|---|---|---|
| R5-1 | Medium | content-visibility intrinsic size 600px vs real 222/357/666 (desktop), 333/693/1976 (mobile): document height jumps 4832 to 4208 / 6379 to 7521, desktop CLS 0.011-0.015 | site |
| R5-2 | Med-Low | topbar does not wrap at text-only 200%, login button cut off | site |
| R5-3 | Low | admin topbar user chip overflows at text 200% on 720px | admin |
| R4-1 | Low (residual) | fr/index mobile 88, TBT 240 ms | site |
| R4-2, R4-3, D10-D12, D14 | Low / Info | unchanged (see Round 4) | content / build / site |
| Design audit | Low | P-06..P-11, A-04..A-06, A-08, A-10 not re-measured, see DESIGN-AUDIT section 5 | site / admin |
