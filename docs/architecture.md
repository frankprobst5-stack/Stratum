# Stratum CMS — Architecture

## Vision

Stratum is a community operating system for clubs, associations, nonprofits, and
member-driven organizations — not a general-purpose CMS. Where WordPress makes
you assemble 20-50 plugins to get forums, events, files, and a newsletter working
together, Stratum ships all of it in core. The admin doesn't hunt for extensions;
they flip switches.

The reference points are e107, SMF (Simple Machines Forum), and ocPortal/Composr —
systems that already solved "all-in-one community portal" at speed, with simple
admin. Stratum copies what made those systems work for admins and members
(granular permissions, forums, calendars, galleries, downloads, blocks, theming),
not their internal code. Every line of Stratum is written fresh, in modern PHP.

**Feature parity with those three systems is the actual product requirement**,
not an inspirational reference (clarified 2026-07-14). There is a real list of
clubs and groups currently running e107, SMF, or ocPortal who intend to
migrate to Stratum, and they expect to find the features they already use
and depend on — not a reimagined subset. Where a design document elsewhere
in `docs/` says a feature was deliberately trimmed or deferred "until a real
need shows up," treat that reasoning as outdated: the real need is these
migrating clubs, and it already exists. See `roadmap.md`'s "Vision Parity
Backlog" for the full, explicitly tracked list of what's still needed to
reach that parity. There's no timeline pressure attached to closing it —
it happens across as many sessions as it takes — but nothing on that list
is optional scope-creep to be waved off.

## Design principles

1. **Everything is installed. Nothing is removed. Everything can be disabled.**
   The codebase ships forums, wiki, calendar, gallery, downloads, etc. as core
   modules. An admin disables a module they don't need; the code stays on disk
   but stops running — no routes registered, no nav entries, no DB queries, no
   scheduled jobs.

2. **Hard-coded but modular.** "Modular" means every feature — first-party or
   future third-party — implements the same module interface (see
   `module-interface.md`). It does not mean "plugin-dependent." A club running
   Stratum never installs a plugin to get a calendar; it toggles the calendar
   module on.

3. **Role and rank are separate.** A role (`member`, `moderator`, `admin`,
   `officer`) determines what a user *can do* — this drives the permission
   engine. A rank (`New Member`, `Veteran`, `Founder`) is a cosmetic/points-based
   status that never gates access. See `permission-model.md`.

4. **Blocks everywhere.** Layout is not hard-coded into page templates. Admins
   place content blocks into named regions (header, sidebar-left, sidebar-right,
   footer, front-page-feature) on any page or site-wide, without editing PHP or
   templates. See `theme-block-system.md`.

5. **API-first internally.** Even though Stratum does not launch with a public
   API in Stage 1-8, every module's read/write operations go through a service
   layer that a REST API (Stage 10) can sit directly on top of — not through
   page-controller logic that would need to be duplicated for the API later.

6. **Speed and simplicity over abstraction.** No ORM framework, no DI container
   framework, no templating engine dependency, no headless split. Plain PHP 8,
   PDO, prepared statements, a hand-rolled router and a small template engine.
   This mirrors how e107 and SMF were actually built, modernized for security
   and maintainability, not replaced with a heavier stack.

7. **Modules connect, not just coexist.** The reference systems felt alive
   because everything was cross-linked through one shared identity — a forum
   post showed the poster's gallery avatar and post count, downloads counted
   toward profile stats, articles linked into discussions. A module that
   works correctly in isolation but never participates in the shared systems
   (search, notifications, activity feed, permissions) has only done half its
   job. When adding a new module or feature, ask whether it naturally plugs
   into those shared systems, not just whether it works standalone — and if
   it doesn't yet because a shared system isn't built yet, track that gap
   explicitly (see `roadmap.md`'s "Vision Parity Backlog") rather than
   letting the module ship as an island.

## System layers

```
┌─────────────────────────────────────────────────────────┐
│  Public site / Admin panel   (HTTP entry: public/index.php) │
├─────────────────────────────────────────────────────────┤
│  Router → Controller → Service layer → Module            │
│         (auth/session/CSRF/permission checks happen here) │
├─────────────────────────────────────────────────────────┤
│  Module Manager (hook registry, on/off state)             │
│  ├─ core/modules/users                                    │
│  ├─ core/modules/forum                                    │
│  ├─ core/modules/calendar                                 │
│  ├─ core/modules/gallery                                  │
│  ├─ core/modules/downloads                                │
│  └─ core/modules/...  (each implements module-interface)  │
├─────────────────────────────────────────────────────────┤
│  Core services: DB layer (PDO), Permission engine,        │
│  Template/Block engine, Session/Auth, Cache, Cron, Logger  │
├─────────────────────────────────────────────────────────┤
│  MySQL / MariaDB                                           │
└─────────────────────────────────────────────────────────┘
```

- **Public entry point**: a single front controller (`public/index.php`) handles
  all requests; no direct access to files outside `public/`.
- **Module isolation**: modules never query the database directly with raw SQL
  scattered through view files — they go through their own service class, which
  is the seam the future API reuses.
- **Disabled module = invisible**: the module manager checks enabled state
  before registering routes, nav items, blocks, or cron jobs for a module. A
  disabled module contributes nothing to the request lifecycle beyond its
  manifest being read once at boot (cached).

## Stack

- **Language**: PHP 8.2+ (typed properties, enums, readonly where useful)
- **Database**: MySQL 8 / MariaDB 10.6+, accessed exclusively via PDO with
  prepared statements
- **Dependency management**: Composer, used only for PSR-4 autoloading and a
  small number of well-audited libraries (e.g. a BBCode parser, a mail
  transport) — never a framework
- **Frontend**: server-rendered templates, responsive CSS (flexbox/grid, no
  table layouts), minimal vanilla JS; no required build step for core to run
- **No framework**: no Laravel, Symfony, Slim, or similar. No headless/decoupled
  split for the primary site.

## What's deferred, deliberately

- **Chatroom**: SMF/e107/ocPortal predate WebSockets and never solved real-time
  well, so this is the one area with no reference model — scoped to Stage 9.
  IRC-inspired (channels, operators, topics, slash commands), not the IRC
  protocol itself, over a **web-native transport tiered by hosting
  capability**: AJAX polling is the baseline (works on any shared host with
  nothing but PHP/MySQL), Server-Sent Events is an automatic upgrade where
  the host supports long-running PHP processes, WebSockets (Swoole/Ratchet)
  are an opt-in upgrade for VPS/dedicated deployments. Polling-first,
  not WebSocket-first — the target clubs are largely on cheap shared
  hosting, and a WebSocket-only chat would silently not work there. See
  `docs/roadmap.md`'s Stage 9 entry for the full design discussion
  (2026-07-18).
- **Public REST/GraphQL API, PWA, container deployment, CI/CD**: Stage 10. The
  service-layer discipline in every earlier stage exists specifically so this
  isn't a rewrite.
