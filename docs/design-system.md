# Stratum Design System

Reference spec for the visual foundation built 2026-07-19, derived directly
from `look.png` (the project's confirmed target mockup) after an audit
found the public site had accumulated ~40 independently-styled module
templates with no shared typography, spacing, or component layer — see
`docs/roadmap.md`'s "Design System Foundation" entry for the full context
and audit findings. Check every new template/block against this doc rather
than re-deriving conventions ad hoc.

**Scope note**: this governs the *public* site (`themes/default/templates/`
and the module templates it wraps). The admin panel already has its own
complete, working system in `core/admin/templates/admin-layout.php`
(`--accent`/`--border`/`--text-dim`, `.admin-panel`, etc.) — a separate,
pre-existing design system, not part of this doc. It gets the same
CSS-externalization treatment as a later, lower-priority follow-up (see
roadmap), but its component names/values aren't changed by this pass.

## Where the CSS lives

`assets/css/theme.css` — static, structural, cacheable (linked via
`<link rel="stylesheet" href="/assets/css/theme.css?v=N">`, bump `N` on
any change since there's no build-step fingerprinting). Everything in
this doc lives there.

`themes/default/templates/layout.php` keeps only a small inline
`:root { ... }` block for values that are genuinely per-request/per-install
dynamic: `--strat-accent` (admin-chosen), the resolved font stack, and
whichever dark-mode code path applies. Never move structural rules back
into the inline block — if it doesn't depend on a PHP variable, it
belongs in `theme.css`.

## Color

**Backgrounds/text/cards** — the existing Stage 8 dark-mode variables,
unchanged, just applied more consistently:

| Variable | Light | Dark |
|---|---|---|
| `--strat-bg` | `#f4f5f7` | `#15171c` |
| `--strat-text` | `#1a1a1a` | `#e4e6eb` |
| `--strat-card-bg` | `#fff` | `#1e2128` |
| `--strat-card-border` | `#d1d5db` | `#333a4a` |
| `--strat-muted-text` | `#666` | `#9aa0ab` |

**Accent** — `--strat-accent`, admin-chosen via `/admin/settings` (Stage 8
color/typography manager). This is what "blue" means everywhere below —
never a second, independent hardcoded blue.

**Badge/category colors** — new, fixed (not admin-configurable; these
exist purely to give different card *types* visual variety, matching
`look.png`'s per-category icon colors, not to be re-themed per install):

```css
--strat-color-blue: var(--strat-accent);
--strat-color-green: #22c55e;
--strat-color-orange: #f59e0b;
--strat-color-purple: #a855f7;
--strat-color-gold: #eab308;
--strat-color-teal: #14b8a6;
--strat-color-red: #ef4444;
--strat-color-cyan: #06b6d4;
```

## Radius, shadow, transition, z-index, spacing tokens

Added 2026-07-19, ported in (renamed to the `--strat-*` convention) from
a CSS scaffold the user shared — not theme-dependent, so these live once
in the base `:root` block, no light/dark variants needed.

```css
--strat-radius-sm: 6px;
--strat-radius: 10px;
--strat-radius-lg: 16px;
--strat-shadow-sm: 0 1px 3px rgba(0,0,0,0.07);
--strat-shadow: 0 8px 30px rgba(0,0,0,0.25);
--strat-transition-fast: 0.15s ease;
--strat-transition: 0.25s ease;
--strat-z-dropdown: 100;
--strat-z-sticky: 500;
--strat-z-modal: 900;
--strat-z-toast: 1000;
--strat-space-1: 0.25rem;  /* through --strat-space-6: 2rem */
```

Use these instead of a new hardcoded literal wherever one of these
already-solved values is needed — e.g. `border-radius: var(--strat-radius-sm)`
on any small rounded element, `var(--strat-shadow-sm)` on any card-level
surface. `.strat-block-card`, `.strat-icon-badge`, `.strat-card-cta`,
`.strat-quick-link-tile`, and `.strat-header-dropdown-panel` were
retrofitted to use these tokens in the same pass that added them.

**What was deliberately NOT ported from that scaffold**: its full modern
CSS reset (zeroes every element's margin, strips all list-style/table-
border defaults globally) and its global `*,*::before,*::after {
transition: ... }` rule. The reset is genuinely good but risky to land
*before* the content-page pass — most module templates still lean on
default browser paragraph/list/table spacing, and a global reset with
nothing yet replacing that spacing would make untouched pages look
worse, not better. Revisit alongside the content-page pass, where
compensating rules can be verified page-by-page as they're added. The
global transition rule is a known anti-pattern (forces the browser to
watch every property on every element for a possible transition) and
wasn't adopted at all — only specific, deliberate `transition:` declarations
on interactive elements (buttons, tiles, dropdown items) are used instead.

## Typography scale

| Element | Size | Weight |
|---|---|---|
| `h1` | 1.75rem | 700 |
| `h2` | 1.25rem | 700 |
| `h3` / card title | 1.05rem | 600 |
| body / `p` | 1rem | 400 |
| muted / `small` | 0.85rem | 400, `color: var(--strat-muted-text)` |

Base `line-height: 1.5`. `box-sizing: border-box` on `*` (matching the
admin panel's own reset, now applied publicly too).

## Spacing scale

`0.5rem / 0.75rem / 1rem / 1.5rem / 2rem` — the increments already used
ad hoc throughout the codebase, just formalized rather than re-invented.

## Components

**`.strat-block-card`** (existing, unchanged) — the card shell: bg/
border/radius/shadow. Still applied by `BlockRegistry::renderRegion()`
when `$wrapInCards` is true.

**`.strat-card-header`** (new) — icon badge + title row, rendered by
`BlockRegistry` itself (not per-block markup) for any block implementing
the new `CardBlock` interface. Contains one `.strat-icon-badge`.

**`.strat-icon-badge`** (new) — small rounded-square badge, background
`color-mix` of the block's declared accent color at low opacity, icon
(emoji, same convention as `layout.php`'s `$navIcons`) centered, full
accent color as the icon's own color. Color chosen via a `data-accent`
attribute (`blue`/`green`/`orange`/`purple`/`gold`/`teal`/`red`/`cyan`)
rather than one CSS class per color, to keep the CSS rule count fixed
regardless of how many blocks exist.

**`.strat-card-cta`** (new) — the "View All →" footer link/button
style, rendered by `BlockRegistry` when a `CardBlock`'s `viewAllUrl()`
returns non-null. Subtle/outlined, not a solid accent button (that
treatment is reserved for primary actions like `WelcomeCtaBlock`'s
"Join Now").

**`.strat-quick-link-tile`** (new) — one tile in the Quick Links grid
(icon + label, accent-colored per tile), plus a `.strat-quick-link-grid`
2-column container. Used by the rebuilt `QuickLinksBlock`.

**`.strat-stat-row`** (new) — formalizes the existing 3-number layout
`StatsBlock`/`site_stats.summary` already used inline, as a real,
reusable class other blocks can adopt later.

**`.strat-pill`** (new, content-page pass, not this slice) — will
replace the `background:#eef1f7;color:#2f6fed;border-radius:12px`
tag-pill snippet currently copy-pasted inline across `forum/topic.php`,
`articles/show.php`, `wiki/show.php`, and `tags/index.php`.

**`.strat-inline-box`** (new, content-page pass, not this slice) — will
replace the `background:#f4f5f7;border-radius:6px` post/comment box
snippet copy-pasted inline across the same set of files.

The last two are documented here now (so the eventual content-page pass
has a name and shape to build toward) but not implemented in this slice
— see `docs/roadmap.md`'s sequencing note.
