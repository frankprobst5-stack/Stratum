# Stratum CMS — Theme & Block System

## Template engine

No Twig/Blade/Smarty dependency — a small in-house compiler, in keeping with
"no framework." Templates are plain PHP files with a restricted, escaped-by-default
output helper, compiled to opcode-cached PHP once and reused (same performance
approach as e107's compiled shortcodes, without the shortcode DSL — plain PHP is
easier to maintain and debug in 2026 than a custom template language).

```php
<h1><?= e($event->title) ?></h1>
<p><?= e($event->description) ?></p>
```

- `e()` HTML-escapes by default; raw output requires an explicit `raw()` call —
  this makes XSS the exception a reviewer notices, not the default a reviewer
  has to catch.
- Templates live in `core/modules/<module>/templates/` and are resolved through
  a theme override chain (below) before falling back to the module default.

## Theme override chain

Resolution order for any template path, first match wins:

1. `themes/<active_theme>/overrides/<module>/<template>.php`
2. `themes/<active_theme>/parent/overrides/<module>/<template>.php` (if the
   active theme declares a parent — child theme support)
3. `core/modules/<module>/templates/<template>.php` (module default)

This is the e107 "modify core theme without touching core files" behavior:
a theme can override a single template from one module without forking
anything else, and a core module update doesn't clobber the override because
it lives outside `core/modules/`.

## Block system

A block is a self-contained, named unit of renderable content
(`UpcomingEventsBlock`, `RecentForumPostsBlock`, `LoginBlock`,
`CustomHtmlBlock`). Modules register the block classes they provide
(`module.json` → `blocks`); the admin places *instances* of a block into
*regions*.

```
strat_block_regions    (id, key, label)         -- 'header', 'sidebar_left', 'sidebar_right', 'footer', 'front_feature'
strat_block_placements (id, block_type, region_id, page_scope, weight, config_json, is_enabled)
```

- `page_scope`: `site_wide`, `front_page_only`, or a specific route pattern —
  covers the vision doc's "blocks can be added all over the site or only front
  page."
- `weight` orders multiple blocks within one region.
- `config_json` holds per-instance settings (e.g. an `UpcomingEventsBlock`
  configured to show 5 events from a specific calendar).
- A block whose owning module is disabled is skipped at render time even if a
  placement row still exists — consistent with "disabled module contributes
  nothing," and the placement isn't deleted so re-enabling the module restores
  it automatically.

## Layout

**Default front-page layout, confirmed 2026-07-18 against real reference
mockups** (see the design note in `docs/roadmap.md`'s Stage 8 entry for
the full discussion and the block list this implies):

```
┌──────────────────────────────┬───────────────────┐
│  front_hero_main               │  front_hero_side    │
│  (big slider, admin-picked     │  (static list,      │
│   category, auto most-recent   │   admin-picked      │
│   N, ages out automatically)   │   category, ~5      │
│                                │   items, scrolls if  │
│                                │   more configured)   │
├───────────────┬───────────────┼───────────────────┤
│  front_col_1    │  front_col_2    │  front_col_3        │
│  (any block)    │  (any block)    │  (any block)         │
└───────────────┴───────────────┴───────────────────┘
```

Five new region keys (`front_hero_main`, `front_hero_side`, `front_col_1`,
`front_col_2`, `front_col_3`), each `page_scope = 'front_page_only'` —
that scope already exists in `BlockRegistry::appliesToPath()` and is
currently unused. `front_hero_main` and `front_hero_side` are both the
same parameterized "Latest Content" block with different `config_json`
(hero-slider display vs. compact-list display), not two block types.
None of this is hard-coded into PHP — it's the default set of region
placements a fresh install seeds (migration-seeded, same "no admin
placement UI yet" pattern `ads`/`sponsors`/`ticker`/`search` already use),
fully rearrangeable by the admin afterward once Stage 8's drag-drop editor
exists. The old `front_feature` region this replaced was seeded in core
migration 001 but never actually wired into any template — dead until now.

## Stage 8 (visual editor) builds on this, doesn't replace it

The drag-drop visual theme editor planned for Stage 8 is a UI on top of
`strat_block_placements` — dragging a block in the browser writes the same
`region_id` / `weight` / `page_scope` columns a Stage-1 admin would set by
hand in a form. No separate "visual layout" data model.

**Header banner (Stage 8, not yet implemented)**: the header region is
planned to carry an admin-uploadable banner image (default/reference art:
`Stratum/de1f5f5a-5a3c-4994-9ea9-543de047cc43.png`), horizontally centered in
the header, with the header background blending into the image's blue tones
instead of today's flat `#12141c` bar, and the nav/link bar rendered below
the image. See `docs/roadmap.md`'s Stage 8 entry for the full note.
