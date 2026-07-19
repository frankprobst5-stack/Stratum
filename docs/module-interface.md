# Stratum CMS — Module Interface Specification

Every feature in Stratum — forum, calendar, gallery, downloads, wiki, commerce,
banners, everything — is a **module**. First-party and (eventually) third-party
modules use the identical contract described here. This is what lets the admin
panel show one uniform "Modules" screen with on/off switches instead of a
patchwork of feature-specific settings pages.

## Directory layout

```
core/modules/<module_id>/
├── module.json          # manifest (see below)
├── Module.php            # entry point, implements ModuleInterface
├── install.php            # runs once on enable-for-first-time
├── uninstall.php          # optional, only runs if admin explicitly removes data
├── migrations/            # numbered SQL/PHP migration files
│   └── 001_create_tables.php
├── routes.php             # route definitions, only loaded if module is enabled
├── services/               # business logic, DB access — the API-reusable layer
├── controllers/            # thin HTTP layer, calls services
├── blocks/                 # block classes this module contributes (see theme-block-system.md)
├── templates/              # default templates, themes may override
└── lang/                   # translation strings
```

## `module.json` manifest

```json
{
  "id": "calendar",
  "name": "Calendar & Events",
  "version": "1.0.0",
  "core": false,
  "requires": ["users"],
  "provides_capabilities": [
    "calendar.create_event",
    "calendar.edit_own_event",
    "calendar.edit_any_event",
    "calendar.rsvp",
    "calendar.moderate"
  ],
  "nav": [
    { "label": "Calendar", "route": "calendar.index", "region": "main_nav" }
  ],
  "blocks": ["UpcomingEventsBlock", "MiniCalendarBlock"],
  "settings_schema": "settings.php"
}
```

- `core: true` means the module can never be disabled — reserved for `users`
  alone in practice (`ModuleManager`'s hardcoded `NON_DISABLEABLE` list is the
  actual source of truth for that; `core` itself only drives the "Core" vs.
  "Optional" label on `/admin/modules`, it doesn't gate anything by itself).
  Every other first-party feature module (forum, calendar, wiki, ...) sets
  `core: false` even though it ships with every install — being first-party
  and being non-disableable are different things here, and only the latter
  gets `core: true`. A club genuinely might not want a forum or a calendar;
  it can't run without `users`.
- `requires` lists other module IDs that must be enabled first. The module
  manager refuses to enable a module whose dependencies are off.
- `provides_capabilities` is the module's contribution to the permission engine
  (see `permission-model.md`) — declared here, not hard-coded into the engine.

## `ModuleInterface`

```php
interface ModuleInterface
{
    public function id(): string;
    public function onEnable(): void;   // runs every time the module transitions off->on
    public function onDisable(): void;  // runs every time the module transitions on->off
    public function registerHooks(HookRegistry $hooks): void;
    public function registerBlocks(BlockRegistry $blocks): void;
}
```

- `onEnable`/`onDisable` are for cheap, reversible state (e.g. flushing a nav
  cache) — **not** for schema changes. Schema changes only ever happen via
  `migrations/`, applied once when a module first transitions from "never
  installed" to "installed," and never reversed by disabling.
- Disabling a module does **not** drop its tables or delete its data. Toggling
  calendar off and back on must not lose events. Only an explicit "Remove module
  data" admin action (running `uninstall.php`) does that, with a confirmation step.

## Module manager lifecycle (per request)

1. Boot reads the cached list of enabled module IDs (invalidated on any
   enable/disable action, rebuilt from `module.json` + DB `module_state` table).
2. For each enabled module, its `routes.php` is included and its
   `registerHooks()` / `registerBlocks()` run.
3. Disabled modules are skipped entirely at this step — their PHP is never
   loaded past the manifest scan, so a disabled module cannot register a route,
   contribute a nav item, hook into an event, or run a cron job.

## Hooks

Cross-module interaction happens through named hooks, not direct calls between
module classes (this is what keeps "hard-coded but modular" from turning into a
tangle of hard dependencies):

```php
$hooks->listen('user.profile.tabs', function (User $user, TabCollection $tabs) {
    $tabs->add('Gallery', route('gallery.user', $user->id));
});
```

Example core hook points: `user.profile.tabs`, `admin.dashboard.widgets`,
`content.searchable_index`, `nav.main.items`, `cron.daily`.

`cron.daily` is the one of these actually wired up (the others remain
aspirational — nothing fires them yet): `bin/cron.php` builds a full `App`,
boots modules so `registerHooks()` runs, then fires it once under a
non-blocking file lock. Not on a system crontab automatically — see
`roadmap.md`'s "Minimal Cron Infrastructure" entry for the deployment line.
`rss_aggregator` is the first real listener (automatic feed refresh,
replacing what used to be a manual-only "Refresh now" admin action).

## First-party modules using this same interface

`users`, `forum`, `wiki`, `articles`, `pages`, `calendar`, `newsletter`,
`rss_aggregator`, `ticker`, `gallery`, `video`, `downloads`, `commerce`,
`classifieds`, `donations`, `banners`, `ad_tracker`, `chat` (Stage 9). No
first-party feature gets a shortcut around this contract — that consistency is
what makes the eventual public API (Stage 10) and third-party module support
possible without a rewrite.
