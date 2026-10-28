# Design audit - Africa Global Press prototype (public + admin)

Date 2026-10-19. Read-only audit. Lenses applied: apple-design-dickwu (HIG: accessibility, layout, typography, color, forms, feedback), apple-design (response, interruptibility, tracking/leading, reduced motion/transparency), frontend-design (intent vs template). Reference: `docs/design-system/DESIGN-SYSTEM.md`.

Method: system Chrome via Playwright (bundled browser not installed), served by `python -m http.server`. Screenshots in `docs/qa/design-shots/` (names `<page>-<width>-<light|dark>.png`; 1440/768/390 for home, cookie banner, admin login/dashboard/posts/post-edit/comments/media/settings; 1440+390 for article, category, contact, search, offcanvas; mega + login modal at 1440; slider is the home hero). Extra: `admin-quiz-builder-1440-light.png`, `admin-empty-posts-1440-light.png`, `admin-aipanel-1440-light.png`, `admin-confirm-*`/`admin-toasts-*`, `home-1440-rootfont200.png`, `home-zoom200-720.png`, `home-1440-forced-colors.png`, `focus-tab-1440.png`. Computed values come from `getComputedStyle` in the live pages; token counts come from parsing `assets/css/app.min.css` and `admin.min.css` (Tailwind escaped utilities excluded from the selector lists, included in raw counts).

Limits (not invented, not verified): modal focus order and the quiz "preview" interactions were not driven; the forced-colors screenshot was captured but not reviewed pixel by pixel; the 768 admin set is only dashboard/posts/post-edit; a background capture batch hung, so dark `admin-quiz-*`, `admin-aipanel-*` and `admin-empty-*` files may be missing or partial (the 1440 light versions are the evidence).

## 1. Scorecard (0-10)

| Area | Public | Admin |
|---|---|---|
| Hierarchy | 8 | 8 |
| Typography | 7 | 7 |
| Colour / contrast | 7 | 8 |
| Spacing / grid | 6.5 | 6.5 |
| Components | 7 | 7.5 |
| States / feedback | 6.5 | 6.5 |
| Motion | 7 | 5.5 |
| Dark mode | 8 | 8 |
| Accessibility | 7 | 7 |
| Originality ("new-generation magazine") | 6.5 | 7 |
| Token fidelity | 6 | 6.5 |
| **Overall** | **7.0** | **7.0** |

Why not higher: the system is well intended but the tokens are documented more than enforced (section 2, T-findings), text is pixel-locked, touch targets are desktop-sized on mobile, and the admin has no motion language beyond a 0.15s `ease`.

Token counts (measured):

| Metric | app.min.css | admin.min.css | DS rule |
|---|---|---|---|
| Spacing values (margin/padding/gap, px/rem) off the 4/8/12/16/20/24/32/52 scale | 86 of 352 (24%): 6px x22, 10px x22, 14px x8, 28px x6, 40px x4, 48/56/36/44/18/72 | 63 of 195 (32%): 10px x21, 6px x11, 14px x8, 2px x6, 18/9/22/28/48/56/64 | no arbitrary values |
| Radii not in 8 / 12 / pill | 12 declarations: 2px x3 (focus ring, search frame, mark), `999px` x9 plus `9999px` x1 (two pill spellings) | 18 declarations: 10px x6, 14px x4, 16px x2, 18px, 6px x2, 4px x3; pill written `99px` x6 | radius 8 / 12 / pill |
| Distinct font-size literals | 17 px values (10, 11, 12, 13, 14, 16, 17, 18, 19, 20, 22, 26 plus rem and clamp) | 18 (11, 11.5, 12, 12.5, 13, 14, 15, 19, 21, 22, 26 plus rem and clamp) | scale 24/20/17/20 |
| Distinct colour literals (hex + rgba) | 69, including Tailwind leaks `#9ca3af`, `#e5e7eb`, `rgba(59,130,246,.5)` (default ring) | 58, same leaks | 7 named tokens |
| Shadow recipes | 7 (`0 4px 30px .3` x5, `0 12px 32px -12px`, `0 18px 40px -22px`, `0 10px 24px -10px`, focus halo...) | 4 (`--shadow`, `--shadow-pop`, 1px/3px, inset) | one tier (`shadow-soft`) |
| Distinct transition/animation durations | 15 (.15 to .8s) | 5 | none defined |
| `var(--ease)` vs bare `ease` | 42 vs 44 | 0 vs 1 | n/a |
| `:hover` / `:active` rules | 54 / 3 | 16 / 0 | pressed state expected |
| `prefers-reduced-motion` / `-transparency` / `forced-colors` / `prefers-contrast` | 1 / 0 / 0 / 0 | 1 / 0 / 0 / 0 | |

## 2. Findings

Layout-safe = the change does not alter positions or sizes of the cloned structures at the default text size. Where it could, the cell says so.

### P1 (fix first)

| ID | Area | Evidence | Principle violated | Concrete fix | Layout-safe | Owner |
|---|---|---|---|---|---|---|
| P-01 | Public, type scaling | `home-1440-rootfont200.png`. With `html{font-size:32px}` computed sizes stay `body 20px, .card-title 26px, .hero-title 38px, .meta 12px, .nav-link 18px, .excerpt 14px, .topbar a 12px` (px literals in `src.css`; `body{font-size:20px}`). Only rem-based utilities moved. | HIG accessibility / typography: text must scale with the user setting (Dynamic Type to 200%). WCAG 1.4.4. | In `src.css` convert every `font-size:Npx` to `N/16 rem` (`body 1.25rem`, `.excerpt .875rem`, `.meta .75rem`, `.hero-title 2.375rem`), keep `html{font-size:100%}`. Result is identical at the default setting. | Yes at default size | site |
| P-02 | Public, forms contrast | `contact-1440-light.png`, `.field input::placeholder` = `rgb(156,163,175)` on `rgb(245,245,245)` = **2.33:1** (needs 4.5:1). Dark is fine (6.78:1). Tailwind gray-400 leaked in. | HIG color / accessibility: 4.5:1 for text up to 17pt. | `.field input::placeholder,.field textarea::placeholder{color:#6b6b6b}` = 4.9:1 on `#f5f5f5`. Keep dark `#9ca3af`. | Yes | site |
| P-03 | Public, touch targets (390) | Measured 75 interactive elements under 44px at 390: `.hero-dot` 24x24 (visible dot 8px), `.hero-pause` 28x28, `.hero-nav` 36x36, `.icon-btn` 32x32, `.topbar-search` 24x36, `.chip` 24px tall x28, `.ticker-item` 135x24, `.footer-cats a` 56x31. `.nav-chev` 28x44. | HIG accessibility: mobile controls 44x44 (min 28), spacing matters. | Keep the visuals, enlarge the hit area: `.hero-dot,.hero-pause,.hero-nav,.icon-btn,.topbar-search,.nav-chev{position:relative}` and `::after{content:"";position:absolute;inset:-10px}`; for chips `inset:-10px 0` (they touch horizontally); `.footer-cats a{padding-block:12px}` is the only visible change and is inside the footer lists. | Yes (hit areas) | site |
| A-01 | Admin, font loading | `admin-login-1440-light.png`: labels and the "Africa Global Press" brand render in Arial/Georgia fallbacks while the dashboard uses Encode Sans/Alegreya. Cause: `admin.src.css` `@font-face{...font-display:optional}` (public uses `swap`). With `optional`, a font that arrives after about 100ms is never applied for that page view. | frontend-design / craft: typography carries identity; consistent first impression. | Change `optional` to `swap` on the 5 `@font-face` rules (the size-adjusted fallbacks already limit shift) and add `<link rel="preload" as="font" type="font/woff2" crossorigin href="../assets/fonts/EncodeSans-normal-400-latin.woff2">` plus Alegreya to `login.html` and the shell pages. | n/a | admin |
| A-02 | Admin, mobile top bar | `admin-dash-390-dark.png`: the "Search posts..." field collapses to an empty 26px pill beside the yellow "+" (measured `.a-input` 26x42 at 390). | HIG layout: content must adapt to width; controls need a usable size. | `@media (max-width:640px){.a-top .a-search{display:none}}` and add a 44x44 search icon button that opens the field full width (or `.a-top input{flex:1 1 0;min-width:0}` and wrap the row). | n/a | admin |
| A-03 | Admin, touch targets (390) | Posts table at 390: `.a-iconbtn` 32x32 x40 (view/edit/copy/delete per row), row checkboxes 24x24 x11, `.a-btn` 30x32, editor toolbar buttons 34x34 x10 (`adm-targets-*`). | HIG: 44x44 on touch; destructive trash sits 8px from edit. | `@media (pointer:coarse),(max-width:640px){.a-iconbtn,.a-rte-bar button{min-width:44px;min-height:44px}.a-table .a-iconbtn{margin-inline:2px}.a-check{min-height:44px}}`; wrap row actions into one overflow menu under 640px. | n/a | admin |

### P2

| ID | Area | Evidence | Principle violated | Concrete fix | Layout-safe | Owner |
|---|---|---|---|---|---|---|
| P-04 | Public, type floor | Computed: `.card-mini .chip` 10px (`src.css` L400), `.card-md .chips .chip` 11px (L231), `.meta`, `.topbar a`, `.breadcrumb` 12px, `.brandband-sub` 12px at 390 (`pub-390-light`). | HIG typography: mobile minimum 11pt, 17 default; avoid tiny bold caps. | Floor at 12px (`.chip` 12px everywhere, padding `4px 8px`); body-support text 14px minimum where the clone allows. | Mostly (chip box grows by about 1-2px in card-mini; check once) | site |
| P-05 | Public, accessibility media queries | grep: `forced-colors` 0, `prefers-contrast` 0, `prefers-reduced-transparency` 0 in `app.min.css`, while `backdrop-filter` is used 8 times (search overlay `search-1440-light.png`, offcanvas, cookie, modals). `home-1440-forced-colors.png` captured. | apple-design s14: three independent signals; HIG accessibility. | Add `@media (prefers-reduced-transparency:reduce){.modal-backdrop,.oc-backdrop,.cookie,.mega{backdrop-filter:none;background:var(--c-surface)}}`, `@media (forced-colors:active){.btn,.chip,.hero-dot::before,.theme-switch{border:1px solid ButtonText;forced-color-adjust:auto}.hero-dot[aria-current=true]::before{background:Highlight}}`, `@media (prefers-contrast:more){:root{--c-meta:#333;--c-line:#767676}}`. | Yes | site |
| P-06 | Public, motion tokens and pressed state | 15 distinct durations (.15 to .8s); 44 declarations use bare `ease` next to 42 using `var(--ease)`; only 3 `:active` rules vs 54 `:hover`. `.btn:hover{transform:translateY(-2px)}` on every button (including small flash pill). 37 `.reveal` elements fade up 16px over `.8s` on scroll (`src.css` L487). | apple-design s1 (respond on press), s4 (one spring/curve family), frontend-design (fade-and-slide-up on every block is the generic default). | Define `--dur-1:.15s;--dur-2:.25s;--dur-3:.45s`, replace bare `ease` with `var(--ease)`, add `:active{transform:scale(.98);transition-duration:.1s}` to `.chip,.icon-btn,.nav-link,.pager-btn,.tile`; cut `.reveal` to opacity-only 0.4s and apply only below the fold row 1. | Yes | site |
| P-07 | Public, token drift | See table above: 86 off-scale spacing values, `2px`/`999px`/`9999px` radii, 69 colour literals (Tailwind grays and the default blue ring `rgba(59,130,246,.5)` in a brand-blue system), 7 shadow recipes against a one-tier rule (`0 4px 30px .3` is used for overlays only). | DS rules "stay on the spacing scale", "one radius vocabulary", "single elevation tier"; craft. | Map `6->8` (gaps), `10->12`, `14->16`, `28->24`, `40->32`, `48->52` in non-clone-measured component internals (forms, footer legal list, chips, share, FAQ); `999px/9999px -> 9999px`; replace `#9ca3af/#e5e7eb/rgba(59,130,246,.5)` with `--c-meta/--c-line/theme(brand.soft/.5)`; collapse card/overlay shadows into `--shadow-soft` + `--shadow-lift:0 12px 32px -12px rgba(0,20,58,.35)`. | Colours, radii, shadows: yes. Spacing: partly; the `grid-3 gap 28`, `editorial gap 48`, `about-cols 56` values are layout and must stay | site |
| A-04 | Admin, radius and spacing vocabulary | 18 off-token radii: `.a-card` 16, `.a-modal` 18, `.a-quick a`/`.a-dz`/`.a-q`/`.a-media-item` 14, inputs/nav/`.a-opt` 10, `.a-ai` 16; `.a-btn` 99px. 63 off-scale spacings (10px, 14px, 18px, 9px, 22px, 64px). | DS: radius 8/12/pill, spacing 4/8/12/16/20/24/32/52. | Introduce `--r:8px;--r-card:12px;--r-pill:9999px` in `admin.src.css`; inputs, nav items, `.a-opt`, `.a-stream`, `.a-pre` -> 8px; cards, modal, `.a-ai`, `.a-quick a`, `.a-q`, `.a-dz`, `.a-media-item` -> 12px; `99px -> var(--r-pill)`; padding `10/14/18 -> 12/16/20`. | n/a (admin free) | admin |
| A-05 | Admin, micro-labels | Computed: `.a-eyebrow` 11px/700, `letter-spacing:1.76px`, uppercase, 6.8:1; `.a-table th` 11.5px, 0.92px tracking; `.a-nav h2` 11px; KPI labels uppercase 11px (`admin-dash-1440-light.png`). Same treatment above every page title. | HIG typography (11pt floor, avoid small caps-heavy labels); frontend-design: tracked ALL-CAPS eyebrow above every heading is a template tell. | Page-head eyebrow: delete it or make it a real breadcrumb ("Newsroom / Posts", sentence case, 13px). `th`, `nav h2`, KPI labels: sentence case, 12px/600, tracking `.02em`. | n/a | admin |
| A-06 | Admin, editor form bugs | `admin-postedit-1440-light.png`: in "Article settings" the checkbox "Allow comments" sits flush against the end of "Show an auto-generated table of contents (from H2/H3)" (no gap, checkbox at x=718 vs label end x=717); the "Post format" caption straddles the top border of its card (y=434). | HIG forms: grouped controls need clear spacing; craft. | Give the checkbox row `display:flex;flex-wrap:wrap;gap:8px 24px`; give the format fieldset `padding-top:16px` and put the caption inside the card header. | n/a | admin |
| A-07 | Admin, KPI decoration and grid | `.a-kpi::after` = 90px yellow circle at `opacity:.16` on all 7 cards (`admin.src.css` L141): decoration that encodes nothing; at 390 dark it reads as olive blobs clipped by the card (`admin-dash-390-dark.png`). 7 cards in an `auto-fit` grid leave a 5 + 2 orphan row at 1440. | Hierarchy: structure should encode information; frontend-design: decorative gradient/circle accent is a default. | Remove `::after`; add a 3px left border in `--accent` only for the "needs action" cards (In review, Comments to moderate) and a delta line ("+4 vs last week"); set `grid-template-columns:repeat(4,1fr)` at wide so 7 cards become 4 + 3 with a wide chart tile filling the last cell. | n/a | admin |
| A-08 | Admin, toast behaviour | `admin.js` toast: roles are correct (`status`/`alert`), but dismiss is `all:unset` with `padding:2px 6px` (about 16x20 hit area); removal is `removeChild` with no exit animation, a fixed 4.5s timer, no pause on hover/focus. In dark (`admin-toasts-1440-dark.png`) toasts are light `var(--fg)` blocks on `#0a0d13`, and three stacked toasts cover the table's status column. | HIG feedback: feedback in-interface, not blocking; apple-design s3 interruptibility. | `.a-toast button{min-width:32px;min-height:32px;display:grid;place-items:center}`; add `@keyframes a-out` + `kill()` that adds `.out` and removes on `animationend`; `toast:hover,toast:focus-within` clears the timer; dark theme: `background:var(--surface);color:var(--fg);border:1px solid var(--line-2)`. | n/a | admin |
| A-09 | Admin, empty and zero states | `admin-empty-posts-1440-light.png`: whole empty state is one muted line "No posts match. Clear the search or filters." with no icon and no action; AI Writer output box is a blank white rectangle (`admin-aipanel-1440-light.png`); the Quiz "Quiz type" select renders empty by default (`admin-quiz-builder-1440-light.png`). | HIG onboarding/empty: invite the next action; generative-ai: state what will appear. | `.a-empty` = 32px icon + 16px title + one `a-btn a-btn-ghost` "Clear filters"; AI output placeholder "Generated text appears here. Nothing is saved until you choose Insert."; preselect first quiz type. | n/a | admin |

### P3

| ID | Area | Evidence | Principle violated | Concrete fix | Layout-safe | Owner |
|---|---|---|---|---|---|---|
| P-08 | Public, optical tracking | Computed `ls`: `.hero-title` 38px -0.19px (-0.005em), `.card-title` 26px -0.13px, `.footer-cats a` +0.28px, all headings share `-.005em` (`h1..h4` rule). | apple-design s15: tracking is size-specific; display tighter, small text looser. | `h1,.hero-title,.article-title{letter-spacing:-.015em}` `h2,.card-lg .card-title{-.01em}` `h3,h4,.card-title{-.005em}`; Encode Sans 12-13px labels `+.02em`. Width change under 1% of title line. | Yes (verify line breaks on hero) | site |
| P-09 | Public, breadcrumb baseline | `contact-1440-light.png` and `article-1440-light.png`: "Africa Global Press" sits about 5px lower than the separator and current-page item (text y=254 vs 249) although the `li` boxes share top=242. | Craft: misaligned baselines read as unfinished. | `.breadcrumb ol{align-items:baseline}` `.breadcrumb li{display:inline-flex;align-items:baseline;line-height:16px}`. | Yes | site |
| P-10 | Public, copy | `midpage-1440-light.png`: "4 lecture minimale" under every card. French needs "4 min de lecture". | HIG writing: clear, correct labels. | Change the string in the generator/template (`{n} min de lecture`); also check the EN equivalent. | Yes (shorter) | site |
| P-11 | Public, serif leading | `body` Alegreya 20/28 (1.4), `.excerpt` 14/19.6 (1.4) in a 400 weight serif with small x-height (`pub-*`). | frontend-design: serif body wants slightly more leading than sans; HIG layout readability. | Article prose `.prose p{line-height:1.55}`; excerpt `1.45`. Excerpts in the clone grid stay 14px (layout). | Prose yes; card excerpt no (could change wrapping) | site |
| A-10 | Admin, motion language | 0 uses of an easing token; 14 of 21 transitions are `.15s ease`; modal and toast use `a-pop .2s` with no exit; no interruptible/spring behaviour; only `a-fade`, `a-pop`, `a-blink` keyframes. | apple-design s3-s4, s7: enter and exit symmetric, anchored to origin. | `--ease:cubic-bezier(.22,1,.36,1)` shared with the site; modal enter `scale(.97)->1` from the trigger's origin 220ms, exit 140ms reverse; table row hover `background` 120ms. | n/a | admin |
| A-11 | Admin, dark toasts and surfaces | `admin-toasts-1440-dark.png`: inverted toasts are 15:1 against a near-black page (glare, not a contrast failure). `--brand:#345994` buttons with white text are 7.0:1 (fine). | HIG dark mode: avoid bright slabs. | See A-08 dark fix. | n/a | admin |

## 3. Quick wins (15 or fewer, ordered)

1. P-01: `font-size` px to rem across `src.css` (zero visual change, unlocks 200% text).
2. P-02: placeholder colour `#6b6b6b` on `.field` (2.33 to 4.9:1).
3. A-01: admin `font-display:swap` + preload (removes the Arial/Georgia first view).
4. A-02: admin mobile search (kills the 26px orphan field).
5. P-03: `::after` hit-area expansion on hero dots, icon buttons, chips, nav-chev.
6. A-03: 44px coarse-pointer sizes for admin icon buttons, checkboxes, editor toolbar.
7. P-05: three media queries (reduced-transparency, forced-colors, prefers-contrast).
8. A-06: fix the checkbox row gap and the "Post format" caption.
9. A-07: delete `.a-kpi::after`, add action accent and delta line.
10. P-06: duration/easing tokens, `:active` states, softer `.reveal`.
11. A-09: designed empty states and AI/quiz placeholders.
12. A-08: toast exit animation, hover pause, 32px dismiss, dark surface.
13. P-04 and A-05: raise the 10-11px micro-type to 12px, drop uppercase eyebrows in admin.
14. A-04 and P-07: radius/shadow/colour clean-up (8/12/pill, remove Tailwind leaks).
15. P-09 and P-10: breadcrumb baseline and "min de lecture" copy.

## 4. What is already excellent (keep)

- Clear information hierarchy on the public layout: black nav, yellow pills as a single recurring accent, red chips (white on `#e02200` = 4.77:1), brand `#013079` for primary action. One colour means one thing.
- Typeface pairing is a real choice: Alegreya 900 headlines (newsroom voice) over Encode Sans UI. Fonts are self-hosted, preloaded, with size-adjusted fallbacks; `text-wrap:balance/pretty`, `font-optical-sizing:auto` and `onum` are set.
- Dark mode is complete on both sites: `[data-theme]`, `prefers-color-scheme` default, `color-scheme` set, yellow focus ring on dark (`outline 3px #ffec00`, `focus-tab-1440.png`), measured text contrast 16-20:1 on body, 7.8:1 on muted meta, 4.8:1 on chips.
- Focus management: skip link, 3px offset ring on every interactive element, `main:focus{outline:none}` correctly scoped, ring colour flips per surface (brand-soft on white, yellow on black).
- Reduced motion is handled in CSS and JS (`.01ms` override, `.reveal` shown, ticker and slider paused when `reduce`).
- No horizontal overflow at 1440, 720 (200% zoom equivalent), 390 or 320 on home, contact and admin login (scrollWidth equals viewport).
- Cookie banner offers Accept / Refuse / Customise with equal visual weight (yellow vs outlined, both pills) and does not trap the page.
- View-transition navigation (`@view-transition`) gives continuity with 180/280ms fades without custom JS.
- Admin: semantic status badges with tokens for ok/warn/danger/info in both themes (6.5-10.5:1), inline validation on login, `AGP.confirm` with a danger style, `role=status/alert` toasts with an Undo-capable action slot, focus-trapped modals that restore focus, sticky sidebar with a visible current-page marker, badge counts on Posts and Comments, a clear "Mock" label on the AI panel with disclosure that output is simulated (matches `generative-ai.md` transparency).
- Admin dashboard quick-access tiles and the workflow-oriented post editor (format switch, SEO checklist, JSON-LD preview) are strong product thinking; the new-generation feel is closer to a newsroom tool than a CMS skin.

## 5. Re-audit (Round 5, after design/perf fixes)

Method: Playwright (Edge) against a local gzip server, probes in `tools/qa/r5_design_probe.py`, `r5_zoom_media.py`, `r5_layout.py`; evidence in `docs/qa/shots/r5-*`. Items marked "not re-measured" were not individually probed.

### Findings status
| ID | Status | Evidence |
|---|---|---|
| P-01 type scaling | Closed | root font-size 200%: body 40px, card-title 44, hero-title 52, meta 24, nav-link 36, excerpt 28, topbar a 24, chip 24 (all scale); 1440 layout and order unchanged |
| P-02 placeholder contrast | Closed | `rgb(107,107,107)` on `rgb(245,245,245)` = 4.9:1 |
| P-03 touch targets (390) | Mostly closed | axe 0 violations, crawl 0 controls under 24px; visual boxes under 44px still exist (92 of 121 on home) because hit areas are enlarged with pseudo-elements; 3-5 visible boxes per page still under 24px (hit area extended). Verify on a device |
| P-04 type floor | Closed | chip, meta, breadcrumb, brandband-sub = 12px |
| P-05 forced-colors / contrast / transparency | Closed | 1 block each in app.min.css and admin.min.css; forced-colors and contrast-more screenshots readable, no overflow |
| P-10 copy | Closed | "N min de lecture"; "lecture minimale" 0 occurrences |
| A-01 admin font loading | Closed | login uses Encode Sans / Alegreya, `font-display:optional` count 0 |
| A-02 admin mobile search | Closed | search input 358x42 at 390 |
| A-03 admin touch targets | Mostly closed | 0 controls under 24px; 42 of 99 visible boxes still under 44 on posts at 390 (not re-measured with hit areas) |
| A-07 KPI | Closed | `::after` circle removed (content none), cards now carry action line ("Open review queue") |
| A-08 toasts | Not re-measured (flows 42/42 pass, undo toast shot still passes) | |
| A-09 empty states | Closed | "No posts match / Nothing fits this search or filter / Clear search and filters" |
| A-06 editor checkbox gap, A-04 radii, A-05 uppercase micro-labels, A-10 admin motion tokens, P-06 motion tokens + pressed states, P-07 token drift, P-08 tracking, P-09 breadcrumb baseline, P-11 serif leading | Not re-measured, treated as open | no change requested in this round |
| NEW R5-1 | Open (Medium) | `content-visibility:auto` uses `contain-intrinsic-size: auto 600px` for search-frame / editorial / tiles; real heights 222 / 357 / 666 (desktop), 333 / 693 / 1976 (mobile); scroll height jumps 4832 to 4208 (desktop) and 6379 to 7521 (mobile); likely source of desktop CLS 0.011-0.015 |
| NEW R5-2 | Open (Medium-Low) | text-only 200% at 1280-1440: topbar row does not wrap, "Se connecter" 83px outside viewport |
| NEW R5-3 | Open (Low) | admin topbar user button overflows 121px at text 200% on a 720px viewport |

### Re-score (0-10)
| Area | Public (was) | Admin (was) |
|---|---|---|
| Hierarchy | 8 (8) | 8 (8) |
| Typography | 8 (7) | 7.5 (7) |
| Colour / contrast | 8 (7) | 8 (8) |
| Spacing / grid | 6.5 (6.5) | 6.5 (6.5) |
| Components | 7.5 (7) | 8 (7.5) |
| States / feedback | 7 (6.5) | 8 (6.5) |
| Motion | 7 (7) | 5.5 (5.5) |
| Dark mode | 8 (8) | 8 (8) |
| Accessibility | 8.5 (7) | 8.5 (7) |
| Originality | 6.5 (6.5) | 7.5 (7) |
| Token fidelity | 6 (6) | 6.5 (6.5) |
| **Overall** | **7.4 (7.0)** | **7.5 (7.0)** |

### Still open, with owner
| ID | Sev | Item | Owner |
|---|---|---|---|
| R5-1 | Medium | per-section `contain-intrinsic-size` (222/357/666 desktop, 333/693/1976 mobile) | site |
| R5-2 | Med-Low | topbar wrapping at 200% text (rem breakpoint) | site |
| R5-3 | Low | admin topbar user chip at 200% text + narrow viewport | admin |
| P-06, P-07, A-04, A-10 | Low | motion, radius, spacing and colour tokens (design-system enforcement) | site / admin |
| A-05, A-06, A-08 | Low | uppercase micro-labels, checkbox row gap, toast behaviour: verify or fix | admin |
| P-08, P-09, P-11 | Low | tracking by size, breadcrumb baseline, serif leading in prose | site |
| R4-2 / R4-3 | Low / Info | 2 EN articles with extra blocks; stray `&#46;` | content / build |
