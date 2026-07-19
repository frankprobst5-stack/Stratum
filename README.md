# Stratum CMS

A modular, community-first portal platform for clubs, associations, and
member-driven organizations — forums, wiki, articles, calendar, gallery,
downloads, chat, classifieds, dues, donations, advertising, and more, all
built in, all individually toggleable, all running on plain PHP and MySQL
with nothing else required.

Built to replace aging e107, SMF, and Composr installs for real clubs
currently running them — same feature set members already know, modernized
code underneath, on hosting no better than what they already have.

## Why

A lot of community platforms in active use today are 10–20 years of
accumulated code on top of a feature set people genuinely love. The goal
here isn't to reinvent how a forum or a downloads section works — it's to
keep what works, rebuild it on a clean, current codebase, and drop the
two decades of accumulated cruft along the way.

## Status

Actively developed. Stages 1 through 8 are shipped — member system,
permissions, forum, wiki, articles, calendar, gallery, downloads,
classifieds, organization spaces (multi-chapter support), dues/donations/
premium memberships, advertising, and a full front-end customization layer
(drag-and-drop block placement, child themes, color/typography/dark-mode
controls, a real menu builder). Stage 9 (realtime chat, live notifications,
private messaging) is in progress. See [`docs/roadmap.md`](docs/roadmap.md)
for the complete, honest build log — every shipped feature is documented
there with what was built, how it was verified, and what was deliberately
left out.

## Features

- **Community**: forums (nested boards, polls, likes, mentions,
  attachments), wiki, articles with scheduled publishing and revision
  history, calendar with RSVPs and attendance tracking, photo galleries,
  a downloads library with mirrors and virus scanning, classifieds, and
  real-time chat rooms.
- **Membership**: capability-based permissions (not fixed roles), friends
  and following, achievement badges, reputation/ranks, dues and premium
  membership tiers, donation campaigns.
- **Organizations**: multi-chapter support with private per-chapter
  forums, calendars, galleries, and file libraries — built for a real
  18-chapter club currently migrating off e107.
- **Customization**: an admin-configurable front page built from
  swappable blocks (drag-and-drop placement, real settings forms — not
  raw JSON), child themes, an accent-color/typography manager, dark mode
  with a per-visitor toggle, and a menu builder.
- **Admin**: a real permissions/audit system, a signed self-update
  mechanism (no SSH required), a web-based installer, module and theme
  management, backups, and a full site-health dashboard.
- **Advertising & revenue**: banner ads with impression/click tracking,
  affiliate links, sponsor blocks, and Cash App–based donation/membership
  payments (no payment processor account required to get started).

## Start here

Read these before touching code — they're the settled contract every stage
builds against:

- [`docs/architecture.md`](docs/architecture.md) — vision, design principles, stack
- [`docs/roadmap.md`](docs/roadmap.md) — the full build log, stage by stage
- [`docs/module-interface.md`](docs/module-interface.md) — the module/addon contract
- [`docs/database-conventions.md`](docs/database-conventions.md) — schema/migration rules
- [`docs/permission-model.md`](docs/permission-model.md) — capability-based permissions, role vs. rank
- [`docs/theme-block-system.md`](docs/theme-block-system.md) — templates, blocks, theming
- [`docs/design-system.md`](docs/design-system.md) — the front-end visual system (colors, typography, components)
- [`docs/coding-standards.md`](docs/coding-standards.md) — PHP/security baseline

## Layout

```
public/          front controller + web-served assets
core/services/    framework internals (DB layer, router, auth, module manager — PSR-4 autoloaded)
core/admin/       admin panel
core/modules/     feature modules (forum, calendar, gallery, ...) — loaded dynamically by the
                  module manager at runtime, not via Composer PSR-4, so enabling/disabling a
                  module never requires regenerating the autoloader
themes/           theme(s); default theme ships in themes/default
storage/          uploads, cache, logs — outside the web root, gitignored
```

## Stack

PHP 8.2+, MySQL/MariaDB via PDO, no framework. Composer is used only for
autoloading `core/services` and a small number of libraries — see
`docs/architecture.md` for why. No npm, no build step, no bundler — the
front end is plain CSS/JS by design, since the target hosting environment
is ordinary cheap shared hosting, not a Node-capable host.

## Getting started

```
composer install
cp .env.example .env   # fill in DB credentials
php bin/install.php    # runs migrations, prompts to create the first admin account
php -S 127.0.0.1:8791 -t public public/index.php
```

Or use the web-based installer (`public/install.php`) if shell access isn't
available — this is the same path a non-technical club admin uses.

## Contributing

This project is under active development and could genuinely use more
hands — especially around the modules still in progress (real-time chat,
notifications) and the frontend polish pass. If you're interested in
contributing, open an issue or reach out directly.

## License

Proprietary — all rights reserved. See `composer.json`. Contact the
maintainer for licensing/collaboration inquiries.
