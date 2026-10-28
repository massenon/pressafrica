# Africa Global Press — Design System (v1)

Source: tokens extracted from pressafrica.media (2026-10-19). Tokens are a system, not a copy: the **layout is kept 100 %** (topbar, logo band, black nav, ticker, hero slider, sections, footer, offcanvas); the **finish is new** (type rhythm, motion, glass/soft elevation, micro-interactions).

## Tokens (implemented in `prototype_html/tailwind.config.js`)
| Token | Value | Use |
|---|---|---|
| brand | `#013079` | CTAs, links, search frame, newsletter button |
| brand-soft | `#345994` | accents, focus ring, hover |
| flash | `#ffec00` | yellow pills (Consulter le journal, Explorer par thématiques, contact CTA), footer divider |
| alert | `#e02200` | category chips |
| ink / ink-2 | `#000` / `#141414` | topbar, nav, footer / raised cards |
| muted | `#949494` | meta, captions |
| radius | 8px (ds), 12px (card), pill for buttons | |
| spacing | 4·8·12·16·20·24·32·52 | no arbitrary values |
| shadow-soft | `0 4px 30px rgba(0,0,0,.3)` | single elevation tier for overlays |
| fonts | Alegreya 400/700/800/900 (headings+body), Encode Sans 400–700 (UI) | self-host, preload, `display=swap` |
| type scale | H2 24/900/1.29 · H3 20/900/1 · H4 17/800/1.29 · body 20/400/1.4 | |

## Components (all in `assets/css/src.css`, `@layer components`)
`.btn .btn-primary .btn-flash` · `.chip` (category) · `.sec-title` (coloured square + title + 4px rule) · `.container-page` (max 1240px).

## Rules
DO: `#013079` for primary actions; stay on the spacing scale; one radius vocabulary; WCAG 2.2 AA contrast (flash text is always black; white text only on brand/ink/alert); visible focus ring; honour `prefers-reduced-motion`.
DON'T: add competing primaries; one-off radii; hot-link `pressafrica.media`.

## Dark mode
`[data-theme="dark"]` on `<html>`, toggled by the nav switch, persisted in `localStorage`, defaults to `prefers-color-scheme`.
