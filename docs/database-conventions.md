# Stratum CMS — Database Conventions

## Engine & charset

- MySQL 8 / MariaDB 10.6+, InnoDB only (row-level locking, FK support).
- `utf8mb4` / `utf8mb4_unicode_ci` on every table and text/varchar column — no
  exceptions, no `latin1` legacy holdovers (a direct fix of the kind of
  mojibake bugs that plagued e107/SMF-era installs on older MySQL defaults).

## Table naming

- Prefix: `strat_` on every table (configurable at install time, default
  `strat_`, mirroring the e107/SMF pattern of a configurable prefix for
  shared-hosting multi-app databases).
- Table name = `strat_<module_id>_<entity>`, snake_case, plural entity name:
  - `strat_users`, `strat_users_groups`
  - `strat_forum_boards`, `strat_forum_topics`, `strat_forum_posts`
  - `strat_calendar_events`, `strat_calendar_rsvps`
- Pivot/join tables: `strat_<a>_<b>` alphabetized by entity, e.g.
  `strat_users_roles`.
- Module-owned tables always start with the module's own ID segment so a
  disabled/removed module's tables are trivially greppable and droppable by
  `uninstall.php`.

## Columns

- Primary key: `id` (`BIGINT UNSIGNED AUTO_INCREMENT`) on every table — no
  natural/composite primary keys, even where a natural key exists, to keep FK
  references and the future API's resource IDs uniform.
- Foreign key columns: `<singular_entity>_id`, e.g. `user_id`, `board_id`.
- Timestamps: every table gets `created_at` / `updated_at`
  (`DATETIME`, not `TIMESTAMP`, to avoid the 2038 problem and timezone
  auto-conversion surprises). Soft-deletable entities (posts, events, gallery
  items) also get a nullable `deleted_at`.
- Booleans: `TINYINT(1)`, named as a predicate — `is_enabled`, `is_pinned`,
  `is_locked` — never bare `enabled`/`pinned`.
- Enums represented as PHP-level backed enums stored as `VARCHAR`, not MySQL
  native `ENUM` (native `ENUM` makes adding a value a schema migration; a
  `VARCHAR` + PHP enum + optional `CHECK` constraint doesn't).

## Foreign keys

- Every FK is declared with an explicit `ON DELETE` policy — never left to
  default (`RESTRICT`):
  - Ownership chains that should cascade (e.g. a topic's posts when the topic
    is hard-deleted): `ON DELETE CASCADE`.
  - References that should survive the referenced row's removal (e.g. a post's
    `user_id` when a user is deleted): `ON DELETE SET NULL` + a "deleted user"
    display fallback.
  - Anything module-to-module (e.g. a gallery image attached to a forum post):
    `ON DELETE CASCADE` scoped to the owning side only, never bidirectional.

## Migrations

- Location: `core/modules/<module_id>/migrations/`.
- Filename: zero-padded sequence + description —
  `001_create_tables.php`, `002_add_pinned_to_topics.php`.
- Each migration file returns a class with `up()` and `down()`, executed
  inside a transaction where the storage engine allows (InnoDB DDL is mostly
  non-transactional in MySQL, so `down()` must be hand-verified, not assumed
  safe).
- Migrations run once, tracked in `strat_core_migrations` (`module_id`,
  `migration`, `run_at`). The module manager will not re-run a migration that's
  already recorded, and disabling a module never triggers `down()` — only an
  explicit uninstall does (see `module-interface.md`).
- No raw `ALTER TABLE` outside of a migration file, ever — this is what keeps
  every environment's schema reproducible from a clean install.

## Query access

- All queries go through the PDO wrapper in `core/services/Database.php` using
  prepared statements with bound parameters — string interpolation into SQL is
  a coding-standards violation (see `coding-standards.md`), not a style
  preference.
- No module queries another module's tables directly. If `forum` needs user
  display names, it calls the `users` module's service class, not
  `SELECT ... FROM strat_users`. This is what keeps a disabled module's tables
  safely orphaned instead of causing fatal errors elsewhere.
