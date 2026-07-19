# Stratum CMS — Permission Model

## The problem with "admin" and "moderator"

Most CMS permission systems stop at two or three fixed levels. Clubs need finer
control: someone who can approve memberships but not manage dues; someone who
can moderate the gallery but not the forum. Stratum's permission engine is
capability-based from the start, not retrofitted.

## Three separate concepts — never conflated

1. **Role** — drives permissions. A user has one or more roles
   (`strat_users_roles` pivot). Built-in roles: `guest`, `member`, `moderator`,
   `admin`, `founder`. Clubs can define custom roles (`officer`, `committee_chair`).
2. **Rank** — cosmetic/points-based status (`New Member`, `Veteran`, `Founder`
   badge). Driven by post count, tenure, or admin assignment. **Never** joined
   into a permission check. A 10-year veteran member with no officer role still
   can't delete other people's posts just because their rank is high — this is
   the SMF membergroup-vs-post-count-title split, done as a hard architectural
   rule rather than a convention someone can accidentally violate.
3. **Capability** — the actual unit of permission:
   `calendar.create_event`, `gallery.moderate`, `dues.manage`,
   `articles.approve`, `banners.manage`, `newsletter.edit`. Every module
   declares its own capabilities in `module.json` (`provides_capabilities`,
   see `module-interface.md`); the permission engine doesn't hard-code a list —
   it's assembled at boot from whichever modules are enabled.

## Data model

```
strat_roles            (id, name, is_builtin)
strat_capabilities      (id, key, module_id, label)     -- assembled from enabled modules
strat_role_capabilities (role_id, capability_id)          -- the permission matrix
strat_users_roles       (user_id, role_id)
strat_ranks             (id, name, min_points, icon)
strat_users             (..., rank_id, points)
```

- A capability granted to a role applies globally by default. Per-object
  overrides (e.g. "moderator of *this* board only") are expressed as scoped
  grants: `strat_role_capabilities` gains an optional `scope_type` /
  `scope_id` pair (`scope_type = 'forum_board'`, `scope_id = 12`), null scope
  meaning site-wide. This gives SMF-style per-board moderation and e107-style
  per-item visibility from one mechanism instead of two.

## Check API

```php
$user->can('calendar.create_event');                 // site-wide check
$user->can('forum.moderate', scope: $board);          // scoped check
$user->cannot('dues.manage');
```

Internally: union of capabilities across all of the user's roles, plus any
scoped grants matching the given scope. Denied by default — a capability with
no grant anywhere is `false`, never assumed `true` for admins implicitly
(`admin` role is simply granted every capability at seed time, not special-cased
in the check logic, so there's exactly one code path to audit).

## Organization spaces (multi-chapter clubs)

An "organization space" (Stage 4e, `org_spaces` module) is a scope
container: officers, roster, private forum, calendar, files, gallery, dues,
announcements — this session, only officers/roster/announcements exist —
all scoped to `scope_type = 'org', scope_id = <org_id>`. A user holds the
org's auto-provisioned officer role for one org and is a plain roster
member in another — same role/capability system, just scoped, no parallel
permission system for orgs, exactly as designed.

**Current status (2026-07-13): true, for officers/roster/announcements.**
`PermissionEngine::grant()`/`revoke()` gained scope support the same
night, proven first on forum's per-board moderation (one auto-provisioned
role per board, holding a scoped `forum.moderate` grant; role *assignment*
itself stays completely unscoped — see "Scoped Permission Engine" in
`roadmap.md`), then retrofitted onto `org_spaces` the same night once that
mechanism had proven itself on a second real consumer. `org_spaces.manage`
split into two capabilities: `org_spaces.manage` stays site-wide-only
(creating/archiving orgs — not a per-org action, no object to scope to
yet), and the new `org_spaces.moderate` is what gets scoped per org, via an
auto-provisioned "Officers — {org name}" role exactly mirroring forum's
"Moderators — {board name}" pattern. The `is_officer` roster-row flag is
gone — `OrgSpaceService::listRoster()` now computes officer status from
real scoped-role membership (`PermissionEngine::usersInRole()`), so there's
one source of truth instead of a flag that could drift from the real grant.
`private forum`/`calendar`/`files`/`gallery`/`dues` scoping to orgs remains
unbuilt (those features either don't exist yet at all — files/gallery are
Stage 5, dues is Stage 6 — or existing ones like calendar/forum haven't
been wired to orgs specifically), but the mechanism they'd use when that
work happens is now proven on two independent real features, not just one.

## Admin UX

One matrix screen: roles down the side, capabilities across the top (grouped
by module), checkboxes at the intersections — this is the "everything
controlled individually" requirement from the vision doc, and it's one screen
because capabilities are assembled dynamically from enabled modules rather than
scattered across each module's own settings page.

## What each module must do

A module declares its capabilities in `module.json`. On enable, the module
manager inserts any missing rows into `strat_capabilities`; on disable, rows
are left in place (so a re-enabled module doesn't lose its configured grants) —
consistent with "disabling never destroys data" in `module-interface.md`.
