# Stratum CMS — Development Roadmap

Ten build stages plus the vision/architecture stage below. Each stage produces
something usable end-to-end before the next stage starts — no stage leaves the
app in a broken or half-wired state. Expect multiple sessions per stage.

## Stage 0 — Vision & Architecture ✅ (this pass)

**Deliverables**: `architecture.md`, `module-interface.md`,
`database-conventions.md`, `permission-model.md`, `theme-block-system.md`,
`coding-standards.md`, this roadmap.
**Usable outcome**: a settled contract every later stage codes against — no
runtime yet.

## Stage 1 — Core Framework ✅

**Deliverables**: CLI installer (`bin/install.php` — DB connection + schema
bootstrap + first admin account), PDO database layer, router/URL handler,
sessions & authentication (Argon2id, CSRF, login rate limiting), module
manager (enable/disable, `users` hard-coded non-disableable), template + block
engine with theme override chain, minimal admin shell (dashboard, module
toggle list, site settings). Demo module `core/modules/hello/` proves the
module lifecycle end-to-end.
**Usable outcome**: verified in-browser — fresh install boots, admin logs in,
`/admin/modules` toggles `hello` off and its nav entry + route (`/hello`)
disappear (404), re-enabling restores both. Server-side guard confirmed
blocking a direct attempt to disable `users`.

## Stage 2 — User System & Permissions ✅

**Deliverables**: profiles (`about_me`, `avatar_url` as a URL field — file
upload deferred to Stage 5), the role/rank split (`strat_roles` drive
permissions, `strat_ranks` are cosmetic/points-based, never joined for access
checks), a capability-based permission engine (`PermissionEngine`, scope-aware
per `permission-model.md` though nothing scopes yet), admin UI for the
permission matrix (`/admin/roles`, with custom-role creation) and user
management (`/admin/users`, create + role assignment), self-service
`/profile`. `is_admin` retired in favor of the `admin.access` capability.
**Usable outcome**: verified end-to-end — the pre-existing admin account
migrated from `is_admin` to the `admin` role with no loss of access; a new
`member`-only account got 403 on all of `/admin`; granting `member` the
`modules.manage` capability via the matrix UI gave that account `/admin/modules`
access while `/admin/settings` stayed 403 — concretely proving differentiated,
capability-driven admin access. Along the way, found and fixed a real Stage 1
bug: the logout form never included a CSRF token, so logging out always 400'd.

## Stage 3 — Community Core ✅ (split across three sessions — 3a, 3b, 3c)

**Stage 3a — Pages, Articles & Comments ✅**: `pages` (static pages, admin
CRUD), `articles` (categories, admin CRUD, public index/show), `comments`
(generic polymorphic module — `commentable_type`/`commentable_id` — so
gallery/forum can attach to it later), all built on the Stage 1 module
interface. Along the way: `ModuleManager` gained real `requires` enforcement
(both directions — can't enable a module whose dependency is off, can't
disable a module something else still needs), since `articles` depending on
`comments` was the first real test of that documented-but-unenforced
contract. Admin nav also became module-declared (`admin_nav` in
`module.json`) instead of hand-edited per stage, since this was the second
stage in a row adding admin sections.
**Verified**: admin created a category + published article, viewed it at
`/articles/{slug}` and in the `/articles` index; created a static page at
`/pages/{slug}`; attempting to disable `comments` while `articles` was
enabled was correctly blocked; granted `member` the `comments.create`
capability via the roles matrix, then logged in as `member` and posted a
comment that appeared on the article — while a logged-out visitor's POST to
`/comments` was rejected without creating anything.

**Stage 3b — Forum ✅**: `forum` module — categories,
boards (flat, no sub-boards — not asked for), topics/posts, moderation
(pin/unpin/lock/unlock/delete, capability-gated, no self-edit yet),
allow-listed BBCode (`[b] [i] [u] [url] [quote] [code]`, escape-then-rewrite
so arbitrary HTML can never be smuggled in), and file attachments (MIME
sniffed via `finfo` against a fixed allow-list, stored outside the web root
under `storage/uploads/forum/`, served through a controller that sets
`Content-Type`/`Content-Disposition` explicitly). New capabilities:
`forum.create_topic`, `forum.reply`, `forum.moderate`, `forum.manage`. Along
the way: `Request` gained `$_FILES`/`file()` support (first module needing
uploads), `Response` gained a `file()` factory for streaming downloads with
explicit headers, and `App` gained `rootDir` (needed by `AttachmentService`
to resolve the storage path — nothing before this needed to know it outside
`public/index.php`'s local scope).
**Doc audit note (2026-07-14)**: the original vision notes (`these are
features clubs.txt`) list sub-boards, polls, reports, likes, bookmarks,
mentions, and signatures under forum — none of these shipped, and none were
previously recorded as a deliberate cut. Confirmed as an intentional
scope-lean decision (keep the forum flat and BBCode-simple, add any of
these only if real usage demands it), not an oversight that needs
retroactive fixing. Recorded here so it reads as a decision, not a gap.
**Verified**: admin created a category and board (`/admin/forum`, appears
via `admin_nav` same as articles/pages); granted `member`
`forum.create_topic`/`forum.reply` via the roles matrix; as `member`, posted
a topic with `[b]`/`[i]`/`[url=]` BBCode (confirmed rendered as
`<strong>`/`<em>`/a `rel="nofollow noopener"` link, not literal brackets)
and a real PNG attachment (downloaded byte-identical with correct
`Content-Type`); replied to the topic; as admin, pinned and locked the
topic, confirmed `member` got 403 attempting to reply to a locked topic,
deleted the reply post via moderation and confirmed it vanished from the
thread; confirmed a `.php` file renamed `.jpg` was rejected by the `finfo`
MIME check (and never written to `storage/`) despite passing the extension.
**Stage 3c — Wiki ✅**: `wiki` module — categories, pages, and true
append-only revision history (`strat_wiki_revisions` is the source of
truth; a page has no `body` column of its own, "current" is simply the
latest revision — same "compute, don't cache" choice forum made for its
counts). Editing never overwrites history: every save inserts a new
revision, and restoring an old one copies its body into a *new* revision
rather than rewriting the past. One `wiki.edit` capability covers both
creating and editing pages (unlike forum's split
`create_topic`/`reply` — a page's first revision and its fifth aren't
meaningfully different actions the way a topic and a reply are);
`wiki.manage` covers admin category/page management. Body uses BBCode (not
TinyMCE — wiki is edit-by-anyone content, the same untrusted-authorship
trust boundary as forum/articles, not the narrow admin-only surface that
justified TinyMCE for pages) and reuses the `comments` module exactly like
articles did. Along the way: slug generation — duplicated across
articles/pages/forum with a standing note to promote it once a fourth
consumer showed up — got promoted to `core/services/Slug.php`; wiki was
that fourth.
**Verified**: admin created a category (`/admin/wiki`, appears via
`admin_nav`); granted `member` `wiki.edit` via the roles matrix; as
`member`, created a page with `[b]`/`[i]` BBCode (rendered correctly, not
literal brackets); edited it (a second revision, with an edit summary);
confirmed `/wiki/{slug}/history` listed both revisions newest-first with
correct authors/summaries; viewed the original (first) revision directly
and restored it as admin — confirmed a *third* revision appeared (not a
history rewrite) and the live page immediately reflected the restored
content; posted a comment on the wiki page via the already-established
`comments` module and confirmed it appeared.
**Usable outcome (full Stage 3)**: a working forum + wiki + articles site,
permission-gated — pages, articles, comments, forum, and wiki all verified
end-to-end. **Stage 3 is complete.**

## Stage 4 — Organization Tools ✅ (newsletter removed by design, not left open)

**Stage 4a — Calendar & RSVP ✅**: `calendar` module — multiple calendars,
events (title/description/location/start/end), recurring events materialized
as real rows sharing a `series_id` (not computed on the fly — keeps RSVP
trivial, every occurrence is an independent row with its own RSVPs; capped
at 26 occurrences, no full RRULE engine), RSVP (going/maybe/declined,
upsert on `UNIQUE(event_id, user_id)` so changing your response updates in
place rather than duplicating). Chronological upcoming-events list grouped
by date rather than a month-grid UI (genuinely usable, far less work — grid
view is a plausible Stage 8 customization item, not required here). Event
discussions reuse the `comments` module exactly like articles/wiki. New
capabilities: `calendar.create_event`, `calendar.rsvp`, `calendar.manage`.
**Verified**: admin created a calendar (`/admin/calendar`, appears via
`admin_nav`); granted `member` `calendar.create_event`/`calendar.rsvp` via
the roles matrix; as `member`, created a weekly-recurring event with 4
occurrences — confirmed 4 distinct rows in the DB sharing one `series_id`,
each exactly 7 days apart, all appearing on their correct dates in
`/calendar`'s upcoming list; RSVP'd "going" then changed to "maybe" —
confirmed the same row updated in place (no duplicate) via direct DB
inspection; posted a comment on an event and confirmed it appeared; as a
logged-out visitor, confirmed both event creation and RSVP correctly
redirect to `/login` rather than silently failing.
**Stage 4b — Ticker/Announcements ✅**: `ticker` module — admin-only rotating
announcement strip (`ticker.manage`, no member-facing creation, no public
routes). First real use of the block/region system that had existed since
Stage 1 but had never been exercised (`BlockRegistry`/`Block`, rendered into
`layout.php`'s `header` region) — its own migration seeds the one
`strat_block_placements` row directly since there's still no placement admin
UI (that's Stage 8). Messages support scheduling (`starts_at`/`ends_at`),
severity `level` (info/warning/urgent), `weight` ordering, and rotate via a
dependency-free vanilla-JS interval, matching the no-build-step precedent
set by the BBCode toolbar.
**Verified**: scheduled/expired messages correctly shown/hidden by date;
multiple simultaneous messages rotated client-side; disabling the module
made the block vanish with no error and re-enabling restored it without
duplicating the placement row; member account got 403 on `/admin/ticker`.

**Stage 4c — RSS/XML Aggregator & Feed Export ✅**: `rss_aggregator` module,
covering both directions from the roadmap's original single bullet.
Aggregator: admin-added external RSS 2.0 sources, manual "Refresh now" per
source — no cron/scheduler exists anywhere in this codebase, so this is a
standing constraint on any future "scheduled"/"automatic" feature, not just
this one — deduped via `UNIQUE(source_id, guid)`, public aggregated view at
`/feeds`. Export: the site's own articles exposed as valid RSS 2.0 at
`/feed.xml` (`Response::xml()` added as a new factory, mirroring `html()`/
`json()`). `requires: ["articles"]`.
**Verified**: a real external feed fetched successfully with zero duplicate
items on re-fetch; a deliberately bad hostname failed gracefully without
crashing the admin page; `/feed.xml` parsed as valid RSS with correctly
BBCode-rendered `<description>`s; unpublished draft articles excluded from
the export; disabling `articles` while `rss_aggregator` was enabled was
correctly blocked by dependency enforcement.

**Stage 4d — Membership Sign-up Forms ✅**: `membership` module — the first
public self-registration path (`/register`); every account before this was
admin-created via `/admin/users/create`. A signup lands as a pending
`strat_membership_applications` row (including the password hash) with no
`strat_users` row created until an admin approves it, so rejected/unreviewed
applicants never touch the real users table. Approval grants the built-in
`member` role via a new `AuthService::createUserWithHash()` (extracted from
`createUser()`). Admin-configurable custom sign-up fields (text/textarea/
checkbox/select) store answers as one JSON blob per application rather than
a new EAV table. Added `ModuleManager::guestNavItems()` — a reusable
logged-out-only nav mechanism, twin of the existing `navItems()`.
**Verified**: required-field validation rejects incomplete submissions;
duplicate *pending* applications for the same username/email are rejected,
but reapplying after a rejection is allowed; approval produces a real,
working login; disabling/re-enabling the module toggles `/register` and its
nav link together.

**Stage 4e — Organization Spaces ✅**: `org_spaces` module — orgs with
officers, a membership roster, and a BBCode announcement feed, with public
org profile pages. Officer permissions originally shipped on a plain
`is_officer` roster-row flag, since the real scoped-capability engine
didn't exist yet — retrofitted onto it the same night (see **Scoped
Permission Engine** below), so this is no longer a gap. No self-service
join flow (officers/admins add roster members directly); full roster is
members-only, officers/description/announcements are public.
**Doc audit note (2026-07-14)**: the original vision notes describe org
spaces as potentially having their own private forum, calendar, shared
files, and photo gallery, plus committee pages — a per-org slice of the
whole platform, not just officers/roster/announcements. What shipped is
deliberately the v1 slice (announcements + roster + officer permissions,
proven on the scoped-capability engine). The richer per-org
forum/calendar/files/gallery is a real, larger future extension — not
re-scoped into Stage 4e retroactively, but not forgotten either.
**Update (2026-07-16)**: no longer hypothetical — confirmed one of the 8
real clubs migrating to Stratum has 18 chapters nationally, exactly the
case this note was hedging on. Confirmed the same day: their launch needs
the fuller per-chapter forum/calendar/files/gallery slice, not just this
v1. See "Organization Spaces parity" in the Vision Parity Backlog — it's
now a go-live blocker, not a maybe.
**Verified (original build)**: officer-flag-gated actions (add/remove
roster, post announcements) worked with zero site-wide capability and
didn't leak to a second org the same user wasn't an officer of; roster
visibility tiered correctly for guest/member/officer/admin; module
disable/re-enable preserved all org/roster/announcement data; found and
fixed a real bug during verification (not code review) — the announcements
query joined author names via raw SQL and ignored `deleted_at`, so a
soft-deleted author's name kept showing instead of falling back to "Unknown"
like forum/calendar/wiki already do via `AuthService::findById()`.
**Verified (retrofit onto the real engine, same night)**: `is_officer`
column dropped, both existing officer rows lost their flag as expected;
re-promoted through the actual `/organizations/{slug}` UI (not raw SQL) —
confirmed this lazily created an "Officers — Riverside Chapter (#1)" role,
granted it `org_spaces.moderate` scoped to that org, and added the user to
it; that officer then managed their org with zero site-wide capability and
got a hard 403 on a second, unrelated org; admin's site-wide access kept
working on both orgs unchanged; a plain non-officer roster member still saw
the full roster (visibility unaffected) with no management controls; the
global `/admin/roles` matrix still showed only the 5 built-in roles.

**Removed by design (2026-07-14)**: the newsletter dispatcher was originally
tabled pending an SMTP/email provider decision (not blocked technically —
the cron piece it needs, see "Minimal Cron Infrastructure" below, already
exists). User has since decided to drop it entirely rather than resume it —
not deferred, not planned for a later track, cut from Stratum's scope. The
cron infrastructure built for it stays (real payoff already realized via
`rss_aggregator`'s automatic fetch), but no email-sending module is planned.
**Usable outcome (full Stage 4)**: a club can run an event with RSVPs, post
announcements (site-wide and per-org), aggregate/export RSS feeds, accept
public sign-ups, and give multi-chapter clubs their own officer/roster/
announcement space — Stage 4's original scope is complete with newsletter
removed, not left open.

## Minimal Cron Infrastructure ✅ (same night)

**Why**: `HookRegistry::fire()` existed since Stage 1 but nothing ever
called it, and `cron.daily` was a hook name documented in
`module-interface.md` since Stage 0 with no wiring — `rss_aggregator`
(Stage 4c) hit this wall and stayed manual-refresh-only as a result. The
newsletter dispatcher's "scheduled" requirement needs real scheduling too,
but rather than build it newsletter-specific (and end up with nothing to
build until email is sorted), this session built the general piece: it
stands alone and its first payoff is making `rss_aggregator` automatic.
**What shipped**: `bin/cron.php` — a new CLI entrypoint building the same
`App` container `public/index.php` does (minus HTTP dispatch), booting
modules so `registerHooks()` runs, then firing `cron.daily` once under a
non-blocking exclusive file lock (`storage/cron.lock`) so an overlapping
invocation skips instead of running concurrently. `HookRegistry::fire()`
now returns caught `\Throwable[]` instead of `void`, isolating each
listener's failures from the others (zero existing callers, so this was a
safe signature change) — `bin/cron.php` logs each via the existing `Logger`
service. `rss_aggregator`'s `Module.php` gained a constructor capturing
`App` (mirrors `ticker`'s Module.php from Stage 4b, needed for the same
reason) and now listens for `cron.daily`, looping enabled sources through
the exact same `RssFetcher::fetchAndStore()` the manual "Refresh now" admin
action already calls — no parallel fetch logic. No system crontab is
actually installed anywhere (nothing to install it into in this
environment); the real deployment line is documented directly in
`bin/cron.php`'s header: `0 3 * * * php /path/to/bin/cron.php >>
storage/logs/cron.log 2>&1`.
**Verified**: a real external RSS source stayed unchanged until `bin/cron.php`
was run directly (not through `/admin/rss`), at which point it fetched new
items automatically and `last_fetched_at` updated — proving the automatic
path, not just that the manual one still works; running it again immediately
produced zero duplicate items (same dedup as manual refresh, same
underlying call); holding the lock file open from a separate process made a
concurrent run log "skipped, already running" and exit cleanly instead of
double-running; a deliberately broken source's failure was caught and
logged per-listener without stopping the `cron.daily finished` log line or
blocking other sources in the same run.
**Usable outcome**: `rss_aggregator` is genuinely automatic now instead of
manual-only, and any future module gets real scheduling for free by
listening on `cron.daily` — no more per-feature scheduling workarounds
needed. (The newsletter dispatcher this was originally motivated by has
since been dropped from scope entirely — see Stage 4e's "Removed by
design" note — but the cron infrastructure itself is real, general
payoff, not stranded work.)

## Scoped Permission Engine ✅ (reopened Stage 2, same night as Stage 4e)

**Why**: `docs/permission-model.md` has described scope-aware capability
grants (`scope_type`/`scope_id` on `strat_role_capabilities`) since Stage 0,
and `PermissionEngine::userCan()` has accepted scope parameters since Stage
2, but `PermissionEngine::grant()`/`revoke()`/`listGrants()` had only ever
written/read site-wide (`scope_type IS NULL`) rows — nothing in the codebase
had ever been a real consumer of scoping. Two features had worked around
the gap with their own ad-hoc mechanism instead of the documented one:
forum's per-board moderation (flat `forum.moderate`, no per-board scoping)
and Stage 4e's `org_spaces` (a plain `is_officer` roster flag).

**What shipped**: the key finding was that `userCan()`'s SQL already handled
capability-grant scoping correctly — the only missing piece was a way to
*write* a scoped grant, plus a mechanism for "this specific user gets this
role's capabilities, but only for this specific object." Landed on: one
auto-provisioned role per scoped object (e.g. "Moderators — Announcements
(#1)"), created lazily on first use, holding a `forum.moderate` grant scoped
to that board — specific users join *that* role via the existing plain
`strat_users_roles` mechanism, completely unscoped. This means role
*assignment* needed zero schema or query changes; `strat_roles` only gained
nullable `scope_type`/`scope_id` columns (migration `003_add_role_scope.php`)
so the global `/admin/roles` matrix can keep excluding these auto-provisioned
roles (`listRoles()` now defaults to site-wide-only). `grant()`/`revoke()`
extended with optional scope params via MySQL's null-safe `<=>` matching.
New `PermissionEngine` methods: `findRoleForScope()`, `findCapabilityByKey()`,
`usersInRole()`, `addRoleToUser()`/`removeRoleFromUser()` (deliberately not
reusing the bulk `setRolesForUser()`, which would silently wipe a user's
scoped assignments alongside their site-wide ones).
**Retrofit proof**: forum per-board moderation first — a new
`/admin/forum/boards/{id}/moderators` screen, with `ForumController`'s
`forum.moderate` checks all now board-scoped. Chosen over migrating
`org_spaces`' `is_officer` flag in the same pass (smaller, no existing data
to migrate, closes a gap open since Stage 3b). `org_spaces` got retrofitted
onto the same mechanism later the same night, once the engine had already
proven itself on forum — see Stage 4e above for that pass's specifics
(capability split into `org_spaces.manage`/`org_spaces.moderate`, the
`is_officer` column dropped in favor of computed role membership). Two real
consumers now, not one — this is the "no parallel permission system"
promise from `permission-model.md` actually holding up under a second use,
not just a proof of concept that stayed a one-off.
**Verified**: a throwaway account added as one board's moderator could
pin/lock/delete only on that board (server-side POST tested directly, not
just UI) and got a hard 403 on a second board; removing them revoked access
immediately; admin's pre-existing site-wide moderation kept working
unchanged everywhere; the global roles matrix still shows exactly the 5
built-in roles; a no-grants account got 403 on the new admin screen itself;
visiting a board that predated this feature correctly self-healed by
lazily creating its scoped role rather than erroring. (`org_spaces`'
retrofit verification is under Stage 4e above.)
**Usable outcome**: a role's capability can be granted for one specific
object instead of site-wide, with a working admin UI, and two real
features — forum moderation and org officer management — running on it
instead of a workaround.

## Stage 5 — Media Center ✅ (split across three sessions — 5a, 5b, 5c)

**Stage 5a — File Repository / Downloads ✅**: `downloads` module —
categories, files with true version history (each upload is a new,
immutable version row; "current" is just the latest, same "compute, don't
cache" choice wiki's revisions and forum's post counts already made), and
a per-file download counter incremented on every download regardless of
which version was served. "Storage limits" scoped down to a fixed 10MB
per-file cap (matching forum attachments' existing precedent) rather than
a full admin-configurable quota system — same "don't build the admin UI
until there's a real need" discipline as block placements and scoped
capability grants. Viewing/downloading is public, same as
articles/forum/wiki/calendar; only uploading (`downloads.upload`) and
category/delete management (`downloads.manage`) are capability-gated.
Along the way: promoted forum's `AttachmentService::validate()` (MIME-sniff
against an allow-list via `finfo`, never trusting the client's claimed
type) to a new core service, `FileUploadValidator` — downloads is the
second real consumer of that exact validation logic, the same "promote on
2nd/3rd consumer" rule that already promoted `BBCodeParser` and `Slug`.
Forum's own attachment storage/DB glue (`forum_attachments`, tied to
`post_id`) was untouched, just delegates validation to the shared service.
**Verified**: a real PNG upload was stored under `storage/uploads/downloads/`
and downloaded back byte-identical with correct headers, incrementing the
download counter by exactly one; a second version upload made "current"
serve the new content while the original version's own download link still
served the untouched original bytes; a `.php` file renamed `.jpg` was
rejected by the promoted validator (same test forum's attachments already
passed, proving the promotion didn't regress the security property);
granting `member` `downloads.upload` let that account upload while a
logged-out visitor could still view/download but got redirected to
`/login` on upload; soft-deleting a file removed it from `/downloads`
while the row and its version history survived in the database; posting a
real forum attachment after the `AttachmentService` refactor still worked
byte-identical, confirming zero regression from the promotion.
**Stage 5b — Video Embedding ✅**: `video` module — categories, videos that
are either a YouTube/Vimeo embed or a native upload (one `videos` table
with a `source_type` column, not separate tables, since the metadata shape
is identical either way). YouTube/Vimeo URLs are parsed, never stored or
interpolated raw — a small regex extractor pulls just the video ID and the
embed iframe's `src` is always constructed from that validated ID
(`https://www.youtube.com/embed/{id}`, same posture as `BBCodeParser`'s
`[url]` handling: never trust raw user input in emitted markup). Native
upload is `FileUploadValidator`'s third real consumer (after forum
attachments and downloads), with its own video allow-list (MP4/WebM) and a
50MB cap. Comments reuse the `comments` module exactly like
calendar/wiki/articles. Added `Response::streamFile()` — the existing
`Response::file()` always forces a download prompt via
`Content-Disposition: attachment`, which breaks inline `<video>` playback;
the new factory omits that header so native uploads actually play in the
browser instead of downloading. YouTube thumbnails render for free (no API
key, a stable public URL convention); Vimeo/native uploads show a plain
label instead of a thumbnail rather than adding an oEmbed call or an
FFmpeg dependency for a v1. Playlists and likes (from the fuller vision
notes) are explicitly deferred, not built.
**Verified**: a real `youtube.com/watch?v=` URL correctly extracted its
11-character ID and rendered a working embed with the exact expected
`src`; a `vimeo.com/<digits>` URL did the same for Vimeo; a real small MP4
uploaded through the actual multipart path was stored under
`storage/uploads/video/`, streamed back byte-identical with the correct
`Content-Type` and, unlike downloads, **no** `Content-Disposition` header
— confirmed actually playable inline, not forced to download; a garbage
non-YouTube/Vimeo URL was rejected before any row was created; a `.php`
file renamed `.mp4` was rejected by the MIME sniff (same test forum/
downloads already passed); posting a comment on a video correctly used
`commentable_type = 'video'`; a guest could view/watch but not upload,
`member` was 403'd without the capability and could upload after being
granted it; view count incremented on page load; soft-deleting a video
removed it from `/videos` while the row survived; forum attachments and an
existing downloads file both still worked byte-identical after this
session's third reuse of `FileUploadValidator` and the new `Response`
factory — no regression.
**Stage 5c — Image Gallery ✅**: `gallery` module — albums, photos, real
GD-generated thumbnails, real EXIF extraction, bulk upload, comments,
likes. Both `exif` and `gd` PHP extensions were confirmed available, so
unlike video's thumbnail cut (no dependency taken on for Vimeo/native
uploads), real thumbnails and EXIF are in scope here — no new dependency,
and thumbnails matter far more to a gallery's usability than they did to
video's listing. Albums are treated as user-generated content, not admin
taxonomy — a deliberate difference from downloads/video's `.manage`-only
categories: creating an album is closer to starting a forum topic than
creating a forum category, so it uses the lighter `gallery.upload`
capability and follows `createTopicWithFirstPost()`'s "container + first
content in one step" shape — an album is never created empty (a batch that
fails validation entirely just doesn't create the album, redirecting back
instead). No separate admin panel either: with no admin-curated taxonomy
to manage, moderation (delete) happens inline on the public pages when
`can('gallery.manage')`, the same self-service pattern `org_spaces` used
for officers, not a dedicated `/admin/gallery` screen. Likes use a real
join table + `COUNT(*)`, not an incrementing counter like downloads'/
video's monotonic counters — a like can be toggled off, so only a computed
count is actually correct. Bulk upload needed a new `Request::files()`
method to unpack PHP's parallel-array `$_FILES` structure for a
`name="photos[]" multiple` field (nothing before this needed multi-file
input) — each file in a batch validates independently through
`FileUploadValidator` (4th real consumer now, after forum/downloads/
video), so one bad file doesn't fail the whole batch. `Response::
streamFile()` (added for video) got its 2nd consumer here for inline
`<img>` display. Comments reuse `commentable_type = 'gallery_photo'`,
same as every other content type.
**Verified**: a real mixed JPEG+PNG bulk upload in one form submission
stored both with real generated 300px-wide JPEG thumbnails on disk; the
JPEG's EXIF camera model extracted correctly while the PNG's stayed null
(proving the JPEG-only path); both full image and thumbnail streamed back
byte-identical/correctly-sized with no `Content-Disposition` header; a
batch with one valid PNG and one `.php`-renamed-`.jpg` correctly stored
only the valid file, not a whole-batch failure; adding photos to an
already-existing album appended rather than replaced; liking a photo took
the count 0→1, unliking correctly brought it back to 0 (not just
incrementing forever); a comment posted with `commentable_type =
'gallery_photo'` appeared correctly; a guest could browse/view but not
create albums or like (redirected to login), `member` was blocked from
`gallery.upload` actions until granted, then succeeded; soft-deleting a
single photo removed it from the album grid while the row survived;
soft-deleting a whole album removed it from `/gallery` while its own row
*and* its photos' rows survived untouched, confirming the schema's `ON
DELETE CASCADE` on `photos.album_id` never actually fires in normal
use (the app only ever soft-deletes) — it's a manual-DB-cleanup safety net
only, not a code path; forum attachments, an existing downloads file, and
a video stream all still worked byte-identical after this session's 4th
`FileUploadValidator` reuse and 2nd `Response::streamFile()` reuse — no
regression anywhere in the media stack.
**Usable outcome (full Stage 5)**: members can share files with real
version history, embed/upload videos with comments, and post photo albums
with EXIF/thumbnails/likes/comments — Stage 5 is complete.

## Stage 6 — Commerce & Dues ✅ (split across three sessions — 6a, 6b, 6c)

**Stage 6a — Dues ✅**: `dues` module. The vision notes are explicit that
cash/dues support should be "via payment links or integrations rather than
direct processing" (confirmed with the user) — no payment gateway
integration, no card data touches the app. An admin sets a payment link
per dues plan (PayPal.me, Stripe Payment Link, Venmo, etc.); a member
clicks "I'm paying this" to record intent (no money moves yet — mirrors
`membership`'s pending-application-then-approve flow exactly: `status`,
`recorded_by`/`confirmed_at` instead of `reviewed_by`/`reviewed_at`); an
admin manually confirms the payment once it arrives, entering the amount
and an optional period label/note. Two capabilities matching the
light/heavy split every module uses: `dues.pay` (member — record intent,
view own payment history) and `dues.manage` (admin — plan CRUD, confirm
any payment). Dues plans are admin-curated (closer to downloads/video's
categories than org_spaces' user-created albums), so plan management lives
in a dedicated `/admin/dues` screen, not inline. Payment link validation is
minimal — non-empty, `http://`/`https://` only, same scheme discipline
`RssFetcher` and the video URL parser already apply; no attempt to
identify which provider it is, since it's only ever displayed as a link,
never parsed or embedded. Plan viewing is public (a club's fee structure
isn't secret); a member's own payment history is private to them and to
`dues.manage`, same public/private tiering `org_spaces` used for roster vs.
officers. "Subscriptions" from the roadmap line turned out to just be a
plan's `period` field (`one_time`/`monthly`/`annual`) — there's no real
recurring-billing engine to build since there's no payment processing to
actually recur.
**Verified**: a payment link with a `javascript:` scheme was rejected at
plan creation (422, no row created) — same scheme-allowlist discipline as
everywhere else, incidentally also a real XSS-relevant test, not just a
formality; a real plan with a valid `https://` PayPal.me link listed
publicly with no login required; clicking "I'm paying this" twice as a
granted member created exactly one `pending` row, not two (dedup
confirmed); admin's confirmation correctly set `status='paid'`,
`amount_paid`, `period_label`, `recorded_by`, `confirmed_at`, and the
member's own plan page immediately reflected it; a second member with no
payment records saw no payment history section at all (correct isolation);
a guest was redirected to `/login` on `/admin/dues`, and a non-`dues.manage`
account got 403 attempting `confirmPayment` directly; deactivating a plan
removed it from the public `/dues` listing while its detail page and all
payment records remained fully intact and reachable.
**Stage 6b — Donations ✅**: `donations` module, reusing dues' mechanism
almost exactly — campaigns instead of plans, contributions instead of
payments, a `goal_amount` with computed progress (`SUM(amount) WHERE
status='confirmed'`, live, not cached — same "compute, don't cache"
reasoning gallery's likes used) instead of a per-user paid/unpaid status.
Same two-tier capability split (`donations.contribute`/`donations.manage`),
same `http(s)://`-only payment link validation, same public-campaign-info/
private-own-history tiering. One genuinely new piece: an admin can record
a contribution *directly* — attributed to either an existing username or a
free-text `donor_name` — covering cash/check contributions from people who
aren't site members at all. This stayed entirely inside the existing
capability model (an admin action gated by `donations.manage`) rather than
attempting true anonymous public submission, since `Auth::can()` returns
`false` unconditionally for logged-out users in this codebase (documented
Stage 2 limitation — guest-role resolution is deferred) and building that
out was explicitly not this session's scope. No public donor list — the
campaign page shows only the aggregate progress bar, never who gave how
much or how much any one person gave.
**Verified**: a `javascript:` payment link was rejected at campaign
creation (422, no row); a real campaign with a `$2,000` goal listed
publicly with a correct 0% progress bar; double-clicking "I'm donating" as
a granted member created exactly one pending row; admin's confirmation of
a `$200` contribution correctly updated the live-computed progress to
exactly 10%; the admin direct-entry path recorded a `$25` cash
contribution under a free-text donor name as already `confirmed` (no
pending step) and the progress bar recalculated to 11% immediately; no
public page anywhere (index or campaign detail) leaked individual
contributor names or amounts; a guest was redirected to `/login` on
`recordIntent` and on `/admin/donations`; a `donations.manage`-lacking
account got 403 on both `confirmContribution` and `recordContribution`;
deactivating a campaign removed it from the public listing while its
detail page and both contribution records stayed fully intact.
**Stage 6c — Classifieds ✅ — Stage 6 complete**: `classifieds` module —
admin-curated categories, member-posted listings with an optional single
photo. The first real "edit/delete own content" pattern in this project:
`classifieds.post` covers create-and-manage-your-own (mark sold, delete),
`classifieds.manage` is the admin override for moderating anyone's listing
— every earlier module's mutations were either fully open to any capable
user (forum replies, wiki edits) or admin-only, never ownership-gated
until now. No comments on listings (a public back-and-forth doesn't fit a
classified ad's actual use case, and there's no private messaging yet to
route real inquiries through — Stage 9, unbuilt) — just the seller's
username, same as a real classified ad's "contact John" line. Along the
way: promoted `GalleryService`'s thumbnail-generation logic to a new core
service, `ImageThumbnailer` — classifieds is the second real consumer of
that exact GD resize-and-re-encode-to-JPEG logic (same "promote on 2nd/3rd
consumer" rule that already promoted `BBCodeParser`, `Slug`, and
`FileUploadValidator`, itself now a 5th-consumer-strong shared validator).
`GalleryService` was retrofitted to delegate to the promoted service,
behavior-identical.
**Verified**: a real photo upload produced a real GD thumbnail on disk via
the promoted service, streamed back correctly with no `Content-Disposition`
and byte-identical full-image content; a listing posted with no photo at
all rendered its detail page cleanly with no broken `<img>` tag; the
listing's own owner (holding only `classifieds.post`, no `.manage`)
successfully marked their own listing sold; a different, unrelated member
got a hard 403 attempting to modify that same listing directly, and saw no
management buttons on the page at all; an account with `classifieds.manage`
(not the owner) successfully deleted someone else's listing — the
admin-override half of the ownership check, proven independently of the
owner half; a `.php` renamed `.jpg` photo was rejected (same MIME-sniff
test every upload path in this app has now passed); a sold listing stayed
visible on the public listing (marked, not hidden) while a soft-deleted
one disappeared correctly; re-uploading a real EXIF-bearing JPEG to
gallery after the `ImageThumbnailer` extraction produced an
identically-sized thumbnail file and correct EXIF extraction — zero
regression from the promotion.
**Usable outcome (full Stage 6)**: a club can collect dues, run donation
campaigns, and run a member marketplace — all via payment links / owner-
managed listings with admin oversight, no third-party plugin needed.
Stage 6 is complete.

## Site Search ✅ (2026-07-14, same day as the doc audit)

**Why**: the original vision notes list `✓ search` as a Stage 1 core module,
same tier as auth/sessions/permissions — it never got built, and nothing in
this roadmap ever recorded it as deferred; it just silently fell out of the
plan. Caught during the 2026-07-14 doc audit. For a portal whose whole
content surface is forum posts, wiki pages, articles, downloads, classifieds,
and org announcements, having no way to search across any of it is a real
functional gap, not a cosmetic one — every reference system this project
models (e107, SMF, ocPortal) shipped one.
**What shipped**: `search` module — a single `/search` page doing a live
`UNION ALL` query across whichever content modules are currently enabled
(articles, forum topics + posts, wiki current-revision pages, downloads
files, classifieds listings, org_spaces announcements), plus a header
search-box block seeded the same way ticker's Stage 4b block was. **Landed
on `LIKE`, not `MATCH...AGAINST`, and no persistent index table** — a real
FULLTEXT index would have meant either `search`'s own migration altering
tables it doesn't own (crossing the "migrations belong to the owning
module" convention), or declaring `search` in every content module's
`requires`, which would force `ModuleManager::assertDependenciesEnabled()`
to require all of them enabled simultaneously just to turn search on —
breaking independent module toggling, a core design tenet. A synced index
table was also rejected: this codebase has no generic content-lifecycle
hooks yet (only `cron.daily` is ever fired), and building them across 6
services' write paths would have been a much bigger, riskier change than
search itself. Live `LIKE` queries are always current, need zero write-path
changes anywhere, and match the "compute, don't cache" posture already used
for likes/RSVP/progress bars. Fine at club-scale row counts; revisit only
if real data volume ever makes it slow. Ranking is a simple title-beats-body
heuristic (`CASE WHEN title LIKE ... THEN 2 ELSE 1 END`), tie-broken by
recency — no per-type tabs, no real pagination (fixed `LIMIT 30`), matching
the "don't build ahead of demand" discipline used throughout. New
`ModuleManager::isEnabled(string $moduleId): bool` (purely additive) lets
`SearchService` skip a UNION branch entirely when its source module is
disabled, so search degrades gracefully instead of requiring everything on.
**Real bug caught during verification, not code review**: the articles
branch's first draft filtered on `published_at <= NOW()` in addition to
`is_published = 1`, mirroring what scheduled-publishing *might* look like —
but this codebase has no scheduled-future-publish feature, and PHP's
`date()` vs. MySQL's `NOW()` weren't in the same timezone in this dev
environment, so a freshly-published article's `published_at` briefly read
as "in the future" and got silently excluded. Fixed by matching
`ArticleService::listPublished()`'s actual filter exactly (`deleted_at IS
NULL AND is_published = 1`, nothing else) instead of inventing a stricter
one search assumed it needed.
**Standing pitfall actively avoided this session**: every `LIKE` placeholder
across all 7 UNION branches uses its own uniquely-generated name (`p1`,
`p2`, ...) via a small `bindLike()` helper, never reusing one named
placeholder twice in the same query — this codebase's PDO layer has bitten
two prior sessions (ticker, dues/donations) exactly this way.
**Verified**: content across all 6 source types (article, forum topic,
forum reply, wiki page, downloads file, classifieds listing) sharing one
distinctive test term all appeared on one `/search` results page with
correct titles/links/snippets; a draft (unpublished) article and a
soft-deleted article were both correctly excluded; an announcement on an
**inactive** org was excluded while the same term on an active org's
announcement appeared with a correct `/organizations/{slug}` link (proving
the `is_active`-only filter is correct for the one content table with no
`deleted_at` column); disabling `classifieds` made its matching listing
silently disappear from results with no error, and re-enabling brought it
back — proving the `isEnabled()`-gated branch logic, not a lucky query; a
literal `100%` search matched only the item actually containing that text
(wildcard-escaping proven, not just assumed) while a `%`/`_`-laden garbage
query matched nothing; a 1-character query was rejected before ever
touching the database; a term appearing in one article's title and another
article's body-only ranked the title match first; the header search box
block rendered on the home page and `/search` itself via the seeded
placement, and disabling the `search` module removed the block and 404'd
`/search` with no error anywhere, fully restored on re-enable. Zero server
errors across the full verification pass.
**Deliberately out of scope for v1** (see the plan's Decisions): gallery
photo captions and video titles/descriptions aren't indexed yet — not
confirmed there's enough searchable text there to be worth a branch; a
natural v1.1 addition, not a hidden gap. No admin settings screen (which
content is searchable is simply "whichever modules are enabled").

## Notifications ✅ (2026-07-14 — first item off the Vision Parity Backlog)

**Why**: the single biggest unblocker on the backlog — mentions, reply
alerts, approvals, and event reminders all presuppose a notification
system, and every reference system had one. Built with the full producer
set (user's explicit choice over a minimal proof-of-concept).
**What shipped**: `notifications` module — in-app notification center with
a header bell block (unread badge), a `/notifications` page (newest-first,
unread highlighted, per-item mark-read + mark-all-read, PRG + CSRF), and
seven producers wired: forum replies (topic author), comments (content
owner), membership approval (the newly created user), org announcements
(whole roster, poster excluded), board-moderator grants, dues
confirmations, donation confirmations.
**Delivery design — the event bus's second real consumer**: producers call
a new `App::notify(array $event)` (fires the `notify` hook via the existing
`HookRegistry` and logs any listener failures — `fire()` was already
variadic since Stage 1, zero registry changes needed). The notifications
module's `Module.php` registers the listener; when the module is disabled
the listener simply never exists and every producer's call is a no-op —
zero `requires` edges, zero `isEnabled()` checks in producers, proven live
(a forum reply with notifications disabled succeeded and inserted nothing).
Direct service calls were rejected because `ModuleManager::boot()` only
loads enabled modules' classes — a direct `new NotificationService` in
forum would fatal when notifications is off.
**Skip rules centralized in the listener**, never duplicated in producers:
null/zero recipients dropped (nullable `user_id` on dues payments,
donations, video/gallery uploaders), self-notifications dropped
(recipient === actor), duplicate recipients deduped. Read state is a single
`read_at DATETIME NULL` (`IS NULL` = unread — one column, no drift).
Message + URL are denormalized at creation time; deleted content just 404s
its link, same as the reference systems. Comments reuse the form's
already-sanitized `redirect_to` as the notification URL — zero per-type URL
building; owner resolution is a small per-type map in `CommentsController`
(raw table read, deliberately not service instantiation, since a disabled
content module's service class isn't loaded but its table always exists).
**Verified end-to-end (all curl-driven, browser for visuals)**: reply by
member → admin's bell showed 1 with correct message/link; admin replying to
own topic → no self-row; comment on admin's article → notified with the
article URL; comment on a wiki page → no row (deliberate — communal
content, no owner column); comment on a NULL-uploader photo → no row, no
error; approving a membership application → the newly created user logged
in and saw the welcome notification (proves the previously-discarded
`approve()` return value is now captured); org announcement → exactly the
roster-minus-poster notified; board-moderator add → target notified with
board name/link; dues + donation confirmations → payer notified with plan/
campaign names resolved; a NULLed-`user_id` payment confirmed cleanly with
zero rows (null-skip proven); cross-user mark-read attempt left the row
untouched (ownership scoping); missing CSRF → 400; mark-all zeroed the
badge; module disable/re-enable left all rows intact; a deliberately
throwing listener was logged by `App::notify()` while the next listener
still ran (error isolation proven via standalone script).
**Deliberate v1 cuts**: no email delivery (no SMTP infra; newsletter
removed by design), no realtime push (Stage 9 pairs it with WebSockets),
no per-user notification preferences, no @mentions yet (separate
forum-parity item — now unblocked by this system), calendar event
reminders deferred (needs a "reminder already sent" dedup design on top of
`cron.daily` — RSVP table columns confirmed sufficient), membership
*rejections* not notifiable in-app (no user row ever exists for them),
wiki-page comments don't notify (no owner), donations recorded *directly*
by an admin (cash/check entries) don't notify even when tied to a username
— only the intent→confirm flow does.

## Activity Feed ✅ (2026-07-16 — second foundational item off the Vision Parity Backlog)

**Why**: the roadmap's own sequencing note named this the top-priority
remaining foundational item after Notifications shipped — "recent activity
across the club" is the aggregation layer several parity features (and every
reference system's front page) presuppose.
**What shipped**: `activity` module — a public `/activity` page (nav-linked
via `module.json`, same as every other module) showing the most recent 40
items across ten activity types: new members, forum topics, published
articles, wiki pages, calendar events, shared files, videos, photo albums,
classifieds listings, and org announcements.
**Same architecture as Site Search, deliberately**: one `UNION ALL` query in
`ActivityService`, each branch gated by `ModuleManager::isEnabled()` — no
persistent activity table (an event-sourced table would have started empty
and never shown pre-existing history; live queries are complete from day
one and always current), no `requires` edge on any content module, no
write-path changes anywhere. Disabling a content module makes its activity
vanish gracefully; re-enabling restores it. The module itself has zero
migrations — no tables, no seeded blocks — which incidentally proved the
module system handles a migration-less module cleanly.
**Branch subtleties worth remembering**: recurring calendar events
materialize as up to 26 rows sharing a `series_id` (Stage 4a), so the feed
shows only the first occurrence per series — one activity item, not 26;
a wiki page's creator is its earliest revision's author (pages have no
author column); a downloads file's sharer is version 1's uploader; a
gallery album's creator is its earliest surviving photo's uploader (albums
are never created empty per Stage 5c — if that resolves NULL, "Unknown").
Actor usernames are resolved controller-side via `AuthService::findById()`
(soft-delete-aware, "Unknown" fallback, deduped per request) — the exact
pattern org_spaces settled on after its raw-join-ignores-`deleted_at` bug,
not a raw users join in the branch SQL.
**Verified end-to-end**: nine of ten types rendered from real pre-existing
data on first load, correctly interleaved and date-grouped; the 4-occurrence
"Weekly Meetup" series appeared exactly once, linking the first occurrence;
a draft article and a soft-deleted article were both excluded; the one
"Unknown" actor traced back to the deliberately NULL-uploader photo from
the notifications test session (correct fallback, proven from the DB, not
assumed); disabling `classifieds` removed exactly its items from the feed
and re-enabling restored them; disabling `activity` 404'd `/activity` and
removed the nav link, both restored on re-enable; every distinct link the
feed emitted (forum topics, wiki pages, videos, albums, downloads, org
profiles, calendar events, articles, classifieds) returned 200 — zero
broken links, zero server errors across the pass.
**Deliberate v1 cuts** (same "don't build ahead of demand" discipline as
search): forum *replies* aren't itemized (topics only — a busy thread would
drown the feed), wiki *edits* aren't itemized (page creations only), photos
appended to an existing album aren't separate items (album creation is the
user-action granularity), externally-aggregated RSS items aren't included
(not member activity), no pagination (fixed LIMIT 40), no per-type filter
tabs, no home-page block. Any of these is a natural v1.1 addition if real
usage asks for it.

## Moderation / Reporting Queue ✅ (2026-07-16, same session as Activity Feed)

**Why**: the last backlog item other items *consume* — forum Reports was its
designated first consumer, and gallery photos / classifieds listings are the
named future flaggers. Built together with that first consumer, the same way
the scoped-permission engine shipped proven on forum board moderators rather
than as speculation.
**What shipped**: `moderation` module — members flag content via a report
form, reports land in one admin queue (`/admin/moderation`, via `admin_nav`),
moderators resolve or dismiss with an optional note, and the reporter gets an
in-app notification either way (the notify system's 8th producer). One
polymorphic `strat_moderation_reports` table
(`reportable_type`/`reportable_id`, the `comments` shape) with
`status` open/resolved/dismissed. Capabilities follow the standard
light/heavy split: `moderation.report` / `moderation.manage`.
**Key design choices**:
- **Content title/URL are denormalized at report time and resolved
  server-side** from a per-type allow-list in `ModerationService` (raw table
  reads, same reasoning as CommentsController's owner map) — a report row is
  something an admin will *click*, so a client-supplied URL was never
  acceptable (same posture as notifications' producer-built URLs). The queue
  displays reports with zero per-type joins, and a report on since-deleted
  content just 404s its link.
- **The resolver map is the extension point**: adding gallery photos or
  classifieds listings to the queue later = one map entry + one report link
  in that module's template. No schema or queue changes.
- **Forum integration is an `isEnabled()` gate, not a `requires` edge**
  (notifications philosophy): with moderation disabled, `/reports` routes
  don't exist and the Report links vanish from topic pages; forum works
  untouched. The gate matters because capability grants survive module
  toggles — `can('moderation.report')` alone would render dead links.
- **Dedup follows membership's rule**: a second *open* report by the same
  reporter on the same content is silently absorbed (no duplicate row);
  re-reporting after a resolve/dismiss is allowed.
- **Close is race-safe**: the open→closed transition happens in the
  UPDATE's WHERE clause, so two moderators racing on the same report can't
  both win — and only the winner triggers the reporter's notification
  (proven: a double-close produced exactly one notification).
- **Resolution is bookkeeping, not action**: moderators act on content
  through the tools that already exist (forum's delete/lock etc.), then
  mark the report handled. The queue never mutates content itself.
**Verified end-to-end (curl-driven, browser for visuals)**: guest → login
redirect on both report routes, zero Report links rendered; member without
`moderation.report` → hard 403 on GET and POST, no links; after granting the
capability to the `member` role → links appeared on every post, the form
showed the server-resolved title, and a submit created exactly one open row
with correct denormalized title/URL; a double-submit was absorbed (still one
row); missing CSRF → 400; empty reason → bounced back to the form; unknown
type, nonexistent post id, and a soft-deleted post all → 404, no row; a
`<script>` payload in the reason rendered fully escaped on the queue (XSS
check); member got 403 on `/admin/moderation` while admin saw the report
with reporter name and content link; resolve set
status/note/resolved_by/resolved_at and notified the reporter (message +
URL confirmed in `/notifications`); re-report after close was allowed, then
dismissed, producing the second notification type; double-closing an
already-closed report changed nothing and sent no duplicate notification;
closing a nonexistent report → 404; disabling `moderation` removed the
Report links, 404'd both public routes and the admin queue, and dropped the
admin-nav entry — topic pages kept working untouched — and re-enabling
restored everything with both report rows intact.
**Deliberate v1 cuts**: moderators are *not* notified of new reports — that
needs a "which users hold capability X" reverse lookup that
`PermissionEngine` doesn't have yet (site-wide + scoped roles make it
nontrivial); the queue is reachable via admin nav, and the open count shows
there. Design the reverse lookup when a second consumer needs it (dashboard
widgets in Stage 10 want the same query). Only `forum_post` is reportable in
v1 — topics are reported through their posts; gallery/classifieds get map
entries when their report links are added. No reporter-facing "my reports"
page (the notification covers the loop).

## Forum Parity Batch: Mentions, Likes, Signatures ✅ (2026-07-16, same session as the Moderation Queue)

**Why**: three small, independent forum-parity items batched into one pass —
each landed complete and verified on its own, so the session could stop
after any of them with nothing half-wired. @mentions was the item explicitly
waiting on the notifications system (now its 9th producer).
**@Mentions**: `MentionService` (forum module, not core — promote-on-2nd-
consumer rule; comments/wiki would be the promotion trigger) extracts
`@username` tokens from topic and reply bodies, resolves them against real
users (case-insensitive via the column collation; a trailing sentence
period is retried without it; unknown names resolve to nothing), caps at 10
distinct users per post so an @-everyone spam post can't fan out unbounded,
and notifies via `App::notify` (`forum.mention`). A reply's mention of the
topic author is deliberately skipped — they already got the `forum.reply`
notification for the same post, and one post shouldn't produce two rows for
one recipient. No special rendering of mentions in post bodies (no public
profile pages to link to yet).
**Post likes**: `strat_forum_post_likes` join table with computed
`COUNT(*)` — the exact `gallery_likes` shape, same "a like can be taken
back, so only a computed count is correct" reasoning. Any logged-in user
can like (no capability, gallery precedent), toggle semantics, one query
per topic page for all counts plus one for the viewer's own likes (new
`bindIdList()` helper generates uniquely-named IN() placeholders — the
standing PDO no-reused-placeholders pitfall, avoided again). No
notification on like (deliberate — noise).
**Signatures**: `strat_users.signature` VARCHAR(500) NULL (users migration
003), edited on `/profile`, rendered under each forum post through the same
escape-then-rewrite `BBCodeParser` path as post bodies (member-authored
content, same trust boundary). Author rows are cached per request in the
topic action, so a 50-post thread by 3 authors does 3 user lookups, not 50.
**Verified end-to-end**: a reply mentioning `@modtest_admin`, `@nosuchuser`,
`@admin.` (trailing period, and also the topic author), and the poster
themselves produced exactly one mention notification — the real user
notified, the unknown name ignored, the topic author correctly deduped
against their reply notification, the self-mention dropped; guest saw no
Like buttons and got a login redirect on a direct POST; two users' likes
computed a visible count of 2 with the liker's button flipping to Unlike;
unliking brought it back down (row deleted, not decremented); missing CSRF
→ 400, nonexistent and soft-deleted posts → 404; a signature with `[b]`
BBCode and a raw `<script>` tag rendered bold text and fully-escaped markup
under exactly the author's own posts, with sig-less authors showing no
signature block at all.
**Deliberate cuts**: no like-notifications, no per-post like-count denorm
column, no "who liked this" list, no mention autocomplete UI, no mention
highlighting in rendered bodies. Sub-boards and polls remain the two open
forum-parity items (both are real builds, not batch material).

## Bookmarks / Favorites ✅ (2026-07-16, same session as the forum parity batch)

**Why**: the backlog's own note — "natural fit once a shared 'favoritable'
polymorphic pattern exists, same shape as `comments`' commentable_type/
commentable_id" — and moderation's reporting queue had just proven that
exact resolve-a-(type,id)-pair shape with `ContentResolver`'s predecessor,
inline in `ModerationService`.
**What shipped**: `bookmarks` module — a Bookmark toggle button on articles,
wiki pages, and forum topics (v1's three types), and a `/bookmarks` "My
Bookmarks" page listing everything a user has saved, newest first.
**The real architectural move**: promoted moderation's inline title/URL
resolver into `core/services/ContentResolver.php` — a shared, typed
(type, id) → {title, url} resolver, the same "promote on 2nd/3rd consumer"
discipline that already promoted `Slug`, `BBCodeParser`,
`FileUploadValidator`, and `ImageThumbnailer`. `ModerationService` was
refactored to delegate to it (behavior-identical — re-verified after the
change, not just assumed). Adding a 4th/5th bookmarkable type (downloads,
classifieds — both natural next additions) now means one `ContentResolver`
case (if not already there) plus one type in `BookmarkService`'s allow-list
plus one button in that content type's template — no schema changes, no
new resolver logic.
**Deliberately diverges from moderation's denormalization choice**: reports
snapshot title/URL at report time (an audit record of what was flagged);
bookmarks resolve live at listing time (a saved pointer should always show
the CURRENT title) and silently drop any bookmark whose content no longer
resolves — deleted/unpublished content just vanishes from the list, the row
itself untouched in the DB, reappearing automatically if the content is
restored. Same toggle-table shape as `gallery_likes`/`forum_post_likes`
(a `UNIQUE(type, id, user_id)` join table), same "no capability required
beyond being logged in" posture as likes — bookmarking is private and
creates no public content, so there's no moderation surface to gate.
**Verified end-to-end**: guest saw no bookmark buttons and got a login
redirect on both routes; a member's toggle POST created exactly one row and
flipped the button's label, a second toggle removed it; missing CSRF → 400;
an unknown bookmarkable type and a nonexistent article id both → 404
*before* writing a row; a draft (unpublished) article was correctly
rejected — `ContentResolver`'s article case reuses `ArticleService::
listPublished()`'s exact filter, so an unpublished article can't be
bookmarked any more than it can be viewed; a `redirect_to=//evil.example.com`
open-redirect attempt was correctly collapsed to `/`; bookmarking one
article, one wiki page, and one forum topic all appeared on `/bookmarks`
with correct type labels, titles, and links, newest-first; soft-deleting
the bookmarked article made it silently vanish from the list (200, not an
error) while its bookmark row survived untouched in the DB, and restoring
the article brought it back into the list automatically; disabling
`bookmarks` removed the nav link, the buttons on all three content types,
and 404'd `/bookmarks` itself, while the content pages kept working
untouched — re-enabling restored everything with all rows intact; the
moderation queue's report-target resolution was re-verified unchanged after
the `ContentResolver` refactor (same title, same 404-on-deleted behavior).
**Deliberate v1 cuts**: downloads and classifieds listings aren't
bookmarkable yet (`ContentResolver` doesn't have their cases) — natural
v1.1 additions, not gaps, same "promote/extend when a real need shows up"
discipline as everywhere else in this backlog. No bookmark folders/tags
(global tagging is its own separate backlog item), no bookmark counts shown
on content pages (unlike likes, a private list doesn't need a public
counter), no bulk-remove on the list page (a bookmark can be removed by
re-visiting the content and toggling it off).

## Web-Based Installer ✅ (SHIPPED 2026-07-16, same session it was scoped in)

**Why**: confirmed 2026-07-16 — Stratum is replacing the currently-running
sites of 8 real clubs/groups (the user's own included), each presently on
e107, Composr (ocPortal), or SMF. The commitment made to them is explicit:
"exactly what they're used to, only updated and current and ready to work
out of the box." After those 8 are onboarded, the project goes public —
meaning the install experience isn't just for known contacts forever, it's
the front door for strangers too.

**Each club deploys their own install** (settled 2026-07-16 — not a shared
instance across all 8 clubs). That does NOT make `org_spaces` low-priority,
though — corrected same day: `org_spaces` was purpose-built for one
specific real club among the 8, which itself has 18 chapters around the
US. That single club's single install is exactly the multi-chapter use
case `org_spaces` targets — see the "Organization Spaces parity" note
below, which is now a real, confirmed launch requirement for that club,
not a hypothetical "if multi-chapter clubs ask for it" maybe. Hosting is
unknown per club — "shared hosting / self-hosted, I have no idea" — and
critically, **the user will not be installing it for them**.
That means the install path has to work for a non-technical admin on a
host that may not even offer shell access, the exact problem e107/SMF/
ocPortal (and WordPress, outside this project's reference set) all solved
the same way: a browser-based install wizard, not a CLI script.

**`bin/install.php` does not satisfy this requirement** — it's a CLI
script requiring SSH access, prompts interactively via `fgets(STDIN)`, and
`core/bootstrap.php` connects to the database unconditionally on every
request using whatever's already in `.env`. On a fresh shared-hosting
upload with no `.env` yet, the site simply fatals — there's no in-browser
"let's set up your database" step to catch that before it happens. It
stays as the fast path for the user's own redeploys; it doesn't replace
what club admins need.

**Also found during the 2026-07-16 investigation**: `composer.json`
declares only `"php": ">=8.2"`, not the extensions the app actually
requires (`gd`, `exif`, `fileinfo`, `mbstring`, `pdo_mysql`). Today, a
missing extension on an unknown host fails as a raw, unhandled PHP fatal
on whatever page first touches it (e.g., gallery upload breaking with a
confusing error) instead of a clear message during setup. The installer's
requirements-check step is where this gets caught properly; whether
`composer.json` itself should also be corrected is a decision for whoever
picks this up.

**What shipped**: `public/install.php` — a standalone five-step wizard
(requirements → DB connect → migrations → admin account → done), all in
one file rather than split across several, since every step needs the same
state-detection logic anyway. Deliberately does NOT go through
`core/bootstrap.php` — `Config`'s constructor throws immediately if `.env`
doesn't exist, and `Database`'s constructor connects to MySQL immediately
too, both fatal before the wizard could ever render a first screen. State
(which step to show) is re-derived from disk/DB on every single request —
never trusted from a hidden form field or session — so the wizard is
safely resumable: closing the tab mid-setup, a host timeout, or reloading
never corrupts anything, it just picks up wherever reality says it is.
Self-locks via `storage/install.lock` on completion, checked at the very
top of the script before anything else runs — confirmed a POST attempting
to overwrite `.env` post-lock is silently refused, not just the GET views.
**Shared the migration logic with the CLI, not duplicated**: extracted the
Kahn's-algorithm module-ordering logic that used to live only inside
`bin/install.php`'s local `runModuleMigrations()` function into a new
`MigrationRunner::runAll()` method — both the CLI and web installer now
call the exact same implementation. `bin/install.php` re-verified
byte-for-byte identical output after the refactor (re-ran against the live
site: same "already up to date" for every module, same admin-exists skip).
**Two real, previously-undiscovered gaps found and fixed in the same
pass, not just the installer itself**:
1. **No `.htaccess` existed anywhere** — this app only ever worked via the
   PHP built-in dev server's implicit auto-routing. On a real Apache
   shared host, every pretty URL (`/forum/topics/5`, literally everything
   except `index.php` itself) would have 404'd before ever reaching the
   router. This would have made the entire app non-functional on real
   shared hosting regardless of how good the installer was. Fixed with
   `public/.htaccess` (standard front-controller rewrite: real files serve
   directly, everything else routes through `index.php`) plus a root
   `.htaccess` denying direct access to `.env` as defense-in-depth for
   hosts that can't have their document root pointed at `public/` (a real
   constraint on some budget shared hosts) and end up serving the project
   root directly.
2. **MySQL foreign key constraint names are schema-scoped, not
   table-scoped** — several migrations (starting with core's own
   `fk_block_placements_region`) hardcode a literal constraint name rather
   than deriving it from the table prefix. This only breaks if two
   Stratum installs (or two runs of the same one) ever share the exact
   same physical database with different table prefixes — never the case
   for any of the 8 clubs (each gets a dedicated database) — but would
   bite a same-database staging/test copy. Documented here as a known,
   narrow-scope limitation rather than fixed; fixing it means touching
   every migration file with an FK constraint across ~20 modules, which is
   out of scope for the installer itself and not worth the risk of
   regressing an already-fully-verified schema without being asked.

**Sequencing**: this is a go-live blocker, not general backlog — it needs
to land before any of the 8 clubs can self-deploy, which puts it ahead of
Stages 7–9 (advertising, customization, realtime — all enhancement work
irrelevant to getting the 8 clubs running) despite its position in this
file. Stage 10's existing "one-click installer polish" bullet assumed an
installer already existed to polish; it doesn't yet — that bullet is
superseded by this entry, not a separate later pass.

**Verified end-to-end against a real, isolated environment — not just code
review**: spun up a genuinely separate Apache 2.4 + PHP 8.2 container
(mounting a fresh copy of the codebase, no `.env`, no lock file) plus a
completely separate, empty MySQL 8.0 server, specifically to avoid any
risk to the live working dev site/database this whole session's work
lives in. Confirmed real Apache — not the PHP dev server's auto-routing —
correctly rewrites `/forum/topics/5` through `index.php` via the new
`.htaccess` (proved by reaching a PHP-level response, not an Apache 404).
Requirements step correctly failed on real host state (missing `gd`
extension, non-writable `storage/`) before those were fixed, and passed
cleanly after. DB step: a wrong password produced a real, readable MySQL
error with the form re-shown and values preserved, and confirmed `.env`
was NOT written on failure; correct credentials wrote `.env` and advanced.
**Caught and fixed a real bug during this pass**: the "Continue" action
from the requirements step relied on a `?proceed=1` query string surviving
into the DB form's POST — true per HTML spec for an action-less form, but
fragile in practice (a stale bookmark or any hardcoded `action="install.php"`
elsewhere would silently strand a passing install at the requirements
screen forever). Fixed by also accepting the DB form's own POST body (its
`database` field) as independent proof of intent to advance, not relying
solely on the query string. Migrations ran to completion against the
fresh database — 54 tables, exactly matching the real site's live table
count. Admin-account step rejected a too-short password and a mismatched
confirmation with the same messages `bin/install.php` uses, then
successfully created a real account with the `admin` role on a valid
submission. Completion wrote the lock file and displayed the finish
screen; re-visiting `/install.php` afterward — both a GET and a hostile
POST attempting to overwrite the DB config — was correctly refused with
zero effect on `.env`, not just a UI-level block. The account the
installer created logged in for real and reached `/admin`'s dashboard,
proving the whole chain end-to-end rather than just its individual steps.
All test containers, throwaway tables, and the isolated codebase copy were
torn down afterward; confirmed byte-for-byte (`.env` checksum, table
counts, live dev server still responding) that none of this touched the
real working site.

**Explicitly NOT in scope for the 8-club launch (confirmed 2026-07-16)**:
the vision notes describe a much richer installer — community-type
presets ("Gaming / Church / School / Club / Nonprofit / Emergency
Services / HAM Radio / Car Club"), each pre-loading a theme, default
modules, menus, blocks, and demo content. Confirmed with the user this is
a **future public-release** feature, not needed here — the 8 real clubs
are migrating real existing content, not starting from a themed demo. The
installer scoped above (requirements check → DB connect → migrations →
admin account → self-lock) is deliberately the minimal version; don't
expand it to cover presets/demo-content without being asked.

## Update Mechanism ✅ (SHIPPED 2026-07-17, same tier of scrutiny as the threat model demanded)

**Why**: raised 2026-07-16, same conversation as the installer, same root
cause — 8 real self-hosted club installs, unknown hosting per club, no
guaranteed shell access, the user not doing this by hand for them. The
difference is what's at stake: the installer runs against a blank slate,
so a botched install just gets redone. An updater runs against a **live
site with real club data already in it** — members, forum posts, dues
records, uploaded files. A bug in the updater doesn't waste a setup
attempt, it can damage or destroy a real club's live data. This is the
single highest-stakes piece of infrastructure on this whole roadmap and
deserves to be treated that way, not built as a quick afterthought once
the installer ships.

**The ask, concretely**: the user builds an update package (a zip), and
each club's admin uploads it through their own site's admin panel to
apply it — no SSH, no git pull, no manual file replacement over FTP.
Mirrors how WordPress/e107/SMF-era CMSs handled this before centralized
plugin-style auto-updates existed.

**What needs to be built**:
1. An admin-only upload endpoint (new narrow capability, e.g.
   `system.update` — not just `admin.access`, since this is more
   dangerous than anything else gated by that capability today) accepting
   a zip upload.
2. **Cryptographic authenticity verification before touching anything —
   this is the part that cannot be skipped or shortcut.** "Upload a zip,
   extract it, and let the app run the PHP inside" is structurally
   identical to a webshell upload attack. Without a way to prove the zip
   genuinely came from the developer (not just from whoever currently
   holds the update capability — an attacker who compromises one admin
   account, or a zip that gets tampered with in transit/email/download),
   this feature turns every one of the 8 sites into a one-click remote
   code execution vector. The right shape: Stratum ships with a public
   key baked into core; update packages are signed with the matching
   private key (only the user holds); the endpoint verifies the signature
   and refuses to extract anything if it doesn't match. A plain checksum
   is not sufficient on its own (anyone can recompute a checksum for a
   tampered file) — it has to be a signature.
3. A version identifier Stratum tracks (a `core_settings` row or a
   `VERSION` file) so the updater can compare the installed version
   against the package, refuse to apply a mismatched, older, or
   already-applied update, and show "you're on X, this updates you to Y"
   before the admin confirms.
4. A safe file-apply step that touches only application code
   (`core/`, `public/`, `themes/default/`, etc.) and **never**
   `storage/` (uploads, logs, cron lock), `.env`, or any other
   per-install data/config — same "never delete/overwrite user content"
   discipline as everywhere else in this app (soft-deletes, versioned
   uploads, append-only wiki history). A "write the new files to a
   staging directory, then atomically swap it in" pattern is safer than
   overwriting files in place — if the swap fails partway, the old code
   is still what's live, not a half-updated mix of two versions.
5. Runs pending migrations through the existing `MigrationRunner` — the
   exact same mechanism `bin/install.php` and the future web installer
   both use, so schema changes on an update are just "new migration files
   the runner hasn't seen yet," no separate update-specific migration
   logic to get wrong.
6. Clear success/failure reporting, and ideally a rollback path if a
   migration fails partway through an update — at minimum, the admin
   needs to know unambiguously whether the update fully succeeded, partly
   succeeded, or fully failed, not be left guessing whether their site is
   in a consistent state.

**Relationship to the Web-Based Installer**: shares real code — the
file-permission/requirements-style checks, likely some UI patterns — but
is not the same feature and is meaningfully higher-risk given it runs
against live data. Don't scope them as one combined build; the installer
can reasonably ship first since it's needed for day-one launch, with the
updater following soon after (realistically before the first post-launch
bug fix needs to go out to a live club).

**What shipped**: Ed25519 signing via libsodium (not RSA/openssl — sodium
has been bundled into PHP core by default since 7.2, a safer universal bet
than openssl for unknown shared hosts, and its fixed-size keys/signatures
avoid RSA padding-scheme footguns). `bin/generate-update-keypair.php`
(one-time/rotation keypair generation — refuses to write the private key
inside the project tree under any circumstance, refuses to silently
overwrite an existing public key without `--force`, since that would
invalidate every previously-signed package and every already-deployed
site's ability to verify new ones). `core/services/UpdatePackageVerifier`
(signature check → manifest parse → path allow-list validation → per-file
sha256 verification, in that fixed order, fail-closed at every step — see
the class's own docblock for the full threat-model reasoning).
`core/services/UpdateApplier` (backup → per-file atomic swap → migrations
via the same `MigrationRunner::runAll()` the installer uses, since the
ordering is forced: new migration files aren't even on disk until after
the swap → VERSION bump), with automatic file-rollback-from-backup if the
swap or the migrations fail partway, and an honest, undisguised message
about the one genuinely hard case (files rolled back, but MySQL DDL isn't
transactional, so a mid-migration failure may have partially applied
schema changes — the admin is told to contact support before retrying,
not given false confidence that everything reverted cleanly).
`core/admin/controllers/SystemUpdateController` at `/admin/system/update`,
gated by a new narrow `system.update` capability (core migration 004) —
deliberately not reusing `admin.access`, since this is more dangerous than
anything else that capability currently gates. `bin/build-update-package.php`
is the paired developer-side tool (never deployed) — takes a source
checkout, an optional baseline checkout, and the private key, and produces
the signed zip.
**Real design gap found and fixed during testing, not before**: the first
built test package was 11.8MB — mostly large, rarely-changing binary brand
assets (error-page illustrations, favicon) that a naive "ship everything
under the allowed prefixes every time" packaging approach re-included on
every single update, comfortably over the 2-8MB `upload_max_filesize`/
`post_max_size` many shared hosts default to. Fixed by adding an optional
baseline-diff mode to the packaging tool: given a checkout of the
previously-shipped version, any file byte-identical to its baseline
counterpart is left out of the package entirely — `UpdateApplier` was
already correct for this with zero changes needed, since it only ever
touches whatever's in the manifest. The same real test scenario (2
genuinely-changed files out of 340 total) dropped the package from 11.8MB
to 5.7KB. The admin-facing page also now shows the server's actual upload
limit, and a failed upload's error message mentions it, since PHP empties
`$_FILES` entirely (no error code) when `post_max_size` is exceeded — that
failure mode is otherwise indistinguishable from "no file was chosen."
**Verified end-to-end against a fully isolated, genuinely "already-live"
site — not a fresh install, not just code review**: built via Docker,
same discipline as the installer's verification pass (separate MySQL
server, separate PHP process, real `bin/install.php` run first so the
test site has real schema + a real admin account, exactly like a live
club site would). A real signed v1.1.0 package — one changed file plus a
brand-new migration — applied cleanly through the actual browser-facing
form: file content changed on disk, the new migration's table+row were
created via the real `MigrationRunner`, `VERSION` bumped, a pre-update
backup of the changed file existed with the correct old content, staging
was cleaned up afterward. Chained a second real update (1.1.0 → 1.2.0) to
confirm the mechanism works repeatedly, not just once.
**Every attack scenario in the threat model was tried for real, not
reasoned about abstractly** — all correctly rejected with zero effect on
the live site: re-applying an already-applied version; a file tampered
*after* signing (per-file hash mismatch); a signature byte flipped;
a **complete, validly-generated package signed with a different, genuine
keypair** — the actual real-world attack this whole feature exists to
stop — rejected identically to random corruption, not distinguishable in
any way that would help an attacker; a legitimately-signed manifest
(signed with the real key) hand-crafted to target `storage/` — rejected
by the path allow-list *despite* having a valid signature, proving that
check is real defense-in-depth and not just theater; a legitimately-signed
manifest targeting `.env` directly — same result, `.env` never touched,
confirmed byte-for-byte; a package whose `min_current_version` exceeded
the installed version — rejected before any file was even inspected.
Access control: a guest got redirected to login; a real plain-member
account (not admin) got a hard 403 on both the page and a direct POST;
an authenticated admin missing the CSRF token got 400. All test
infrastructure (Docker containers, isolated site copy, background PHP
process) was torn down afterward; confirmed the real project's `VERSION`
(still 1.0.0), `core/services/helpers.php`, and migrations directory were
completely untouched by any of this — none of the isolated testing ever
touched the live dev environment or its database.
**Deliberately not built**: a background/async apply flow (club-scale
codebases apply in well under a second synchronously — no job queue
infrastructure needed for this); a UI-driven package *builder* (packages
are built by the developer via CLI, signed offline, never by an admin
through a browser — that boundary is the whole point); automatic update
checking/notification (the admin manually uploads a package they were
given, matching the "not centralized plugin-store auto-updates" scope
this was always described as in the roadmap's original framing);
rollback of a *successful* update after the fact (once migrations commit,
there's no "undo" story attempted — only mid-failure rollback is handled).

## Admin Dashboard Redesign ✅ (SHIPPED 2026-07-17, same session as the Update Mechanism)

**Why**: the admin panel had no chrome of its own — every admin controller
called the same `App::renderPage()` as the public site and got wrapped in
the public theme's header/nav/footer, with the dashboard itself just a flat
bullet list ("N modules installed... Manage modules · Site settings").
Confirmed direction from the user: a "modernized e107" admin — panel-grid
density over KPI-hero-card dashboards, a dedicated top-bar + sectioned
sidebar distinct from the public site, panels backed by real service data,
not invented analytics.

**What shipped**: `core/admin/templates/admin-layout.php` — dedicated admin
chrome (top bar, grouped sidebar, bottom-pinned user-identity card with a
logout form) rendered by a new `TemplateEngine::renderAdminLayout()` method
that bypasses the theme override chain entirely (the admin shell isn't
themeable per-club content, same reasoning `renderLayout()` already applied
to the public layout). The sidebar groups the module-driven admin nav into
Content / Community / Commerce / Site Tools / System sections via a
route-prefix lookup table local to the template — kept presentational-only
rather than promoted to a `module.json` field, since this is the first
consumer of "group my nav item" (promote-on-2nd/3rd-consumer discipline).
`App::renderPage()` now auto-branches between the new admin chrome and the
existing public layout by checking `str_starts_with($request->path(),
'/admin')` — a deliberate choice to touch zero of the ~22 existing admin
controllers; every one of them already calls this exact method, so the new
chrome applies everywhere under `/admin` automatically. `DashboardController`
rewritten to gather real data — `ActivityService::recent()` (actor names
resolved the same controller-side way `ActivityController` already does,
since `ActivityService` deliberately leaves `actor_id` unjoined), open
moderation-report count, `TrashService` item count, `PresenceService`
online-member/guest counts, live PHP/MySQL version strings, module
enabled/total counts — into a `dashboard.php` panel grid (Recent Activity,
Quick Actions, System Status, Needs Attention, Who's Online), each panel
using the `.admin-panel-grid`/`.admin-panel` CSS classes defined in the new
layout. "Needs Attention" and "Who's Online" panels only render for admins
who actually hold the relevant capability / when the relevant module is
enabled, matching the same graceful-degradation pattern used everywhere
else in the app.
**Verified against the live dev server, not just code review**: logged in
as `modtest_admin` and confirmed `/admin` renders the full panel grid with
real data (actual recent activity rows with resolved usernames, a real
open-report count, a real trash count, real Who's Online numbers, real PHP
8.5.4 / MySQL 8.4.10 version strings, 24/24 modules) with zero PHP warnings
in the server log. Spot-checked `/admin/settings`, `/admin/modules`,
`/admin/forum`, and `/admin/trash` to confirm existing admin controllers
render correctly wrapped in the new chrome with zero controller changes,
including correct active-nav-item highlighting. Confirmed capability
gating still works correctly post-change: logged in as `modtest_member` (a
real non-admin account) and got a genuine `403 Forbidden` on `/admin`
(not a login redirect, which would have meant a session problem rather
than a real capability check).
**Bug found and fixed during this pass**: the sidebar's initial
module-grouping logic extracted a module id from the first path segment of
its admin route (`/admin/{id}/...`) and looked that id up in a group table
— which broke for `rss_aggregator`, whose admin route is `/admin/rss`, not
`/admin/rss_aggregator`. It happened to self-correct by coincidence (the
lookup miss fell through to the same "Site Tools" fallback group RSS was
meant to land in anyway), but that was luck, not correctness. Fixed by
matching each nav item's route against a literal route-prefix table
instead of an assumed module-id segment — confirmed live that RSS Feeds
now lands under Site Tools by an actual matching rule, not a fallback.
**Deliberately not built**: an admin scratchpad/notes panel (already
tracked separately in the Admin system parity backlog below, tagged by the
user as a lower-urgency V2 item); a maintenance-mode toggle, system health
page, log viewer, backup manager, permissions audit view, or module
dependency viewer (all still open items in that same backlog section —
this pass was chrome + a real dashboard, not the full admin parity list).

## Built-in SEO ✅ (SHIPPED 2026-07-17, same session as the Admin Dashboard Redesign)

**Why**: confirmed real want 2026-07-16, top-line item in the original
vision notes — meta tags, sitemap, canonical URLs, and OG tags for
shareable content. No SEO tooling of any kind existed before this pass.

**What shipped**: `App::renderPage()` gained an optional third `$seo`
array param (title/description/canonical/ogType/ogImage) — additive, so
every one of the ~50 existing call sites keeps rendering exactly as before
with sane site-wide defaults, and only the genuinely shareable
content-detail controllers bother passing per-page overrides. Two new
site-wide settings on `/admin/settings` (Site description, Default social
share image) feed those defaults when a page doesn't set its own.
`themes/default/templates/layout.php` now emits `<title>` (page title +
site name, or just the site name when a page doesn't set one — identical
output to before this pass for any untouched page), `<meta
name="description">`, `<link rel="canonical">`, full OG tags
(`og:type`/`og:site_name`/`og:title`/`og:url`/`og:description`/`og:image`),
and Twitter Card tags that upgrade from `summary` to `summary_large_image`
automatically whenever an image is present. `Request::baseUrl()` is new
(promoted out of `RssAggregator\RssController`, which had its own private
copy of the exact same scheme+host logic — canonical URLs and the sitemap
below became the 2nd and 3rd consumers, so it was promoted to core rather
than copy-pasted a third time; `RssController` now calls the promoted
version too). Per-page title/description/OG overrides were added to the
nine controllers serving genuinely shareable public content: articles
(uses the real `excerpt` column), wiki, pages, forum topics, classifieds
listings, downloads, videos, gallery albums, gallery photos, and calendar
events. Wiki/pages/forum/downloads/classifieds/calendar have no dedicated
excerpt column, so a new `SeoService::excerpt()` (strips BBCode markup,
collapses whitespace, truncates on a word boundary) builds one from the
real body/description text — this is a close sibling of
`SearchService::makeSnippet()`, deliberately not merged into it, since
`makeSnippet()`'s term-centering behavior is a different job description
excerpting doesn't need. Classifieds/gallery pass their own thumbnail
route as `ogImage`; `App::renderPage()` resolves any non-absolute `ogImage`
against `Request::baseUrl()` automatically, so controllers just pass a
plain route path, not a hand-built absolute URL.
`core/services/SitemapService.php` follows the exact "live UNION-ALL
across whichever content modules are enabled" shape `SearchService` and
`ActivityService` already established (no persistent index, no
content-lifecycle hooks to keep one in sync) — covers articles (matching
`ArticleService::PUBLISHED_CONDITION` exactly, so a scheduled article
appears in the sitemap the instant it's live), pages, wiki pages,
downloads, videos, gallery albums (not individual photos — a
club-scale-appropriate cut, noted below), classifieds listings, forum
topics, and calendar events, plus each enabled module's own index page and
the homepage. `/sitemap.xml` and `/robots.txt` are registered directly in
`public/index.php` next to the existing `/` fallback route — core,
always-on infrastructure, same "not a toggleable module" reasoning the
admin panel itself already gets — `robots.txt` points crawlers at the
sitemap.
**Verified against the live dev server, not just code review**: confirmed
the homepage renders correct defaults (site name as title, no description
tag when none is set, correct OG/Twitter fallback) before any settings
existed; saved a real site description and a real default OG image
through `/admin/settings` and confirmed both a plain `mysql` query against
`strat_core_settings` and the rendered homepage's actual meta tags picked
them up (title, description, `og:image`, and `twitter:card` upgrading to
`summary_large_image`); loaded a real article and confirmed a full
per-page override — title suffixed with the site name, description pulled
from the real `excerpt` column, canonical URL, `og:type=article`; same
check repeated for a real wiki page and a real forum topic, confirming
`SeoService::excerpt()` correctly strips BBCode and truncates real body
text into a clean description; loaded a real classifieds listing with an
uploaded thumbnail and confirmed `og:image` resolved to a genuine absolute
URL (`request->baseUrl()` correctly prepended to the relative thumbnail
route) that itself served a real `image/jpeg` response, not a broken link;
fetched `/sitemap.xml` and confirmed well-formed XML containing every
currently-seeded article, page, wiki page, download, video, gallery album,
classifieds listing, forum topic, and calendar event with correct URLs and
`lastmod` dates, plus every enabled module's index page and the homepage;
fetched `/robots.txt` and confirmed it correctly points at the sitemap
with the right `Content-Type: text/plain`, and confirmed `/sitemap.xml`
itself serves `Content-Type: application/xml`, not the RSS module's
`application/rss+xml` (an early draft reused `Response::xml()`, which is
hardcoded to the RSS content type — caught before shipping and switched to
`Response::streamFile()`, an existing generic method, rather than adding a
new one-off `Response` method for a single content type).
**Deliberately not built**: individual gallery photos in the sitemap
(albums are the natural shareable/indexable entry point — a photo-per-URL
sitemap entry would balloon well past what's useful at club scale);
`noindex`/robots-meta support (no page in this app is reachable-but-should-
be-hidden — private content like org_spaces is already access-controlled
server-side via capability checks, so a crawler without a valid session
gets a 403/redirect regardless of any SEO tag, making `noindex` redundant
rather than a real gap); structured data / JSON-LD (`schema.org` markup)
— not mentioned in the original notes, a plausible future addition once a
club's launch surfaces a concrete want for rich search snippets.

## Trash Bin: Remaining Type Coverage ✅ (SHIPPED 2026-07-17, same session as Built-in SEO)

**Why**: the original Trash/Recycle Bin entry shipped 11 site-wide content
types and explicitly scoped out two things as a deliberate v1 cut: the
polymorphic `comments` table and the six org_spaces private-content
tables. Its own docblock predicted exactly this: "same table shape, same
pattern, deliberately left as a mechanical 'add a type' extension."
Picked up next per the user's explicit queue.

**What shipped**: `TrashService::TYPES` grew from 11 to 18 entries — one
`comment` type plus six `org_*` types (`org_forum_topic`,
`org_forum_post`, `org_calendar_event`, `org_file`, `org_gallery_album`,
`org_gallery_photo`), all gated the same `ModuleManager::isEnabled()` way
every existing type already was. The six org_spaces types reused the
existing `simpleRows()`/`childRows()` shapes almost exactly, via two new
sibling helpers (`orgOwnedRows()`, `orgChildRows()`) that add one extra
join every org_spaces content row needs and none of the site-wide types
do: joining `org_spaces_orgs` for the chapter's slug, since every
org_spaces public URL is `/organizations/{slug}/...`, not just `/...`.
`org_file` has no per-file show page in this app (only an index and a
download action), so its trash-list URL correctly points at the chapter's
file list rather than a nonexistent per-file page. Comments needed a
different shape entirely — one `comments` table serves five unrelated
parent tables via `commentable_type`/`commentable_id` (article, wiki_page,
video, gallery_photo, calendar_event), so a single fixed join couldn't
work here. `commentRows()` instead follows the exact "UNION-ALL, one
branch per content type, each independently gated on its own owning
module" shape `SearchService` and `ActivityService` already established —
applied to trash for the first time, not new architecture. Gallery photos
have no title of their own (just a nullable caption), so their comment
branch names itself after the always-present photo id ("Comment on photo
#N") rather than reaching for a sometimes-empty caption.
**Verified against real, live data on the dev server — not synthetic rows
crafted to fit the code, not just code review**: soft-deleted one real row
of all seven new types (an org forum topic, an org forum post, an org
calendar event, an org file, an org gallery album, an org gallery photo,
and five real comments — one against each of the five real commentable
content types already present in the dev data) via direct SQL, then
loaded `/admin/trash` as `modtest_admin` and confirmed every single one
rendered with a correct, real, human-readable title and a correct URL —
including the org slug resolving correctly in every org_spaces URL
(`/organizations/riverside-chapter/forum/topics/1`, not a broken or
missing slug) and the file type correctly falling back to the chapter's
file-list URL. Two pre-existing org forum posts (from earlier,
already-deleted test data) showed up as two independent, correctly
distinct rows, confirming multiple soft-deleted rows of the org types
list correctly rather than colliding. Confirmed the restore path for real
by actually clicking Restore on a real org calendar event and a real
comment, then re-querying MySQL directly and confirming `deleted_at`
genuinely cleared on both rows — not just a redirect that looked
successful. Confirmed zero regressions: every one of the original 11
site-wide types (article, gallery album, gallery photo, video, download,
forum topic) still rendered correctly in the same list alongside the new
entries. All test soft-deletes were cleaned up afterward, restoring the
dev database to its exact prior state (aside from the one calendar event
and one comment intentionally left restored by the real Restore-button
test, which is the correct end state for that test, not a leftover).
**Deliberately still excluded**: `users` (unchanged from v1 — a
soft-deleted account has different restore semantics than content and
still deserves its own decision, not a bolt-on here); `org_spaces_
announcements` (this table has no `deleted_at` column at all — its
visibility is governed entirely by the parent org's `is_active` flag, so
there is nothing here for a trash bin to show or restore).

## Forum Parity: Sub-boards & Polls ✅ (SHIPPED 2026-07-17, same session as Trash Bin coverage)

**Why**: the last two open items in the Forum parity backlog, tracked
since Stage 3b shipped forum as flat-boards-only with no poll support.
Picked up next per the user's explicit queue — the final item in it.

**Sub-boards — what shipped**: a single nullable self-referencing
`parent_id` on `forum_boards` (migration 003), `ON DELETE CASCADE` so
deleting a parent board takes its sub-boards with it rather than
orphaning them, matching the existing category->board cascade. Supports
arbitrary nesting depth for free — no separate "level" column to keep in
sync — though the shipped templates only render what an admin actually
builds; depth is a presentation concern, not a schema one.
`ForumService::nestBoards()` is pure array grouping (no extra query,
since `parent_id` already comes back on every row via `listBoards()`'s
`b.*`) that turns the existing flat board list into a tree, via the same
recursive-closure pattern (not a global `function`, which would fatal
with "Cannot redeclare" the moment a second page render hit the same PHP
process) also used in the public forum index template to walk and render
that tree with `&#8627;`-indented rows. The board show page gains a
"Sub-boards" section listing direct children above the topic list.
Creating a topic inside a sub-board needed zero new code — `board_id`
already pointed at whatever board the user was viewing, sub-board or not,
the exact same way it always worked for top-level boards. The admin
board-management screen gained a "Parent board (optional)" picker and a
"Parent" column; a sub-board's own `category_id` doesn't need to match
its parent's — the public index nests the full tree once, then filters
top-level results per category, so a sub-board always renders under its
parent wherever that parent's category lands it.
**Polls — what shipped**: three new tables (migration 004) —
`forum_polls` (one per topic, `UNIQUE` on `topic_id`), `forum_poll_options`,
`forum_poll_votes` (`UNIQUE` on `poll_id`+`user_id`, not
`poll_id`+`option_id`+`user_id` — single-choice voting, deliberately
simpler than multi-select, which was never asked for). A poll is created
only alongside a brand-new topic, via an optional "Add a poll" `<details>`
section on the existing new-topic form (fixed 6 option inputs, not a
dynamic add/remove-option list — this app's forms are server-rendered
with minimal JS everywhere else, and 6 is already more than most club
poll questions need); a blank poll question means everything below it is
silently ignored, not an error, since leftover empty option fields from a
form nobody meant to use as a poll is a normal submission, not a mistake.
No "add a poll to an existing topic later" flow — a deliberate v1 cut,
same narrower-than-everything first pass this app takes everywhere.
Voting needs no new capability — any logged-in member can vote, same
"any logged-in user can act, no dedicated grant" posture post likes
already established. Casting a new vote overwrites the previous choice
(an `UPDATE`, not a second `INSERT`) rather than blocking revotes —
simpler to reason about than reconciling a changed answer, and friendlier
UX than a one-shot-forever ballot. An optional `closes_at` uses the same
"compare against MySQL's own `NOW()`, never a PHP-computed timestamp"
house rule scheduled publishing and presence already established, so a
future misconfigured `APP_TIMEZONE` can never close a poll early or late
relative to what the database itself considers "now." Results (vote
counts, percentage bars) show once a member has voted or the poll has
closed; a not-yet-voted member sees a plain radio-button ballot instead —
standard "don't show results before you vote" forum-poll posture, and a
`<details>` "Change your vote" section stays available underneath the
results for anyone who already voted on an open poll.
**Verified against the live dev server, not just code review**: created
a real sub-board ("Chapter Announcements" under "Announcements") through
the actual admin UI, confirmed it rendered correctly nested with an
`↳` indent on the public forum index and listed under "Sub-boards" on its
parent's board page, with zero server errors on every page touched.
Created a real topic with a real 3-option poll through the actual
new-topic form (not seeded directly in the database) and confirmed the
full voting lifecycle for real: an unvoted ballot showed plain radio
buttons; voting as `modtest_admin` immediately showed the checkmarked
option and correct 100%/1-vote results; using the "Change your vote"
flow to switch to a different option was confirmed via a direct MySQL
query to update the existing vote row in place rather than insert a
second one (still exactly one row in `forum_poll_votes` afterward, now
pointing at the new option); logging in as a genuinely different account
(`modtest_member`) showed that member their own correct not-yet-voted
ballot, independent of the admin's already-cast vote; that member's vote
for a different option produced a correct 50/50, 2-votes-total tally
visible to both accounts. All test rows (the sub-board, the topic, the
poll, its options, and both votes) were deleted afterward, confirmed via
direct query, restoring the dev database to its exact prior state.
**Deliberately not built**: multi-select polls (single-choice only, not
asked for); adding a poll to a topic after it's already been created;
poll results hidden entirely until close (this app shows results
immediately after voting, not only once a poll closes — matches typical
forum-poll UX, and the closed/open state is still visibly labeled either
way); sub-board topic/post counts aggregated up into their parent's count
(each board, parent or child, shows only its own directly-posted
totals — matches this app's existing "compute don't aggregate" posture
and avoids a recursive-sum query for a number nobody asked for).

## Content Ratings ✅ (SHIPPED 2026-07-17, first item of the new 5-hour block)

**Why**: "ratings" is a top-line item in the original vision notes, called
out separately for both articles and downloads (Articles & content
workflow parity, Media & commerce parity) — the last open item in the
former, one of several in the latter.

**What shipped**: a new `ratings` module — `strat_ratings`
(`ratable_type`/`ratable_id`/`user_id`/`score`, `UNIQUE` on all three),
the same polymorphic shape `comments` already established. Unlike most
polymorphic systems in this app, this one was built as a shared module
from day one rather than promoted after a second consumer showed up —
articles and downloads were both confirmed real wants for ratings at the
same time, so it already qualified. `RatingService::rate()` follows the
same "re-rating overwrites, doesn't duplicate" posture
`ForumService::vote()` established for poll ballots — an `UPDATE` on the
existing row, not a second `INSERT`, enforced structurally by the
`UNIQUE(ratable_type, ratable_id, user_id)` key. Gated behind a new
`ratings.create` capability (mirroring `comments.create`'s posture, not
"any logged-in user, no capability" the way likes work — rating is a more
considered action than a like, worth being club-configurable who can do
it). `RatingsController::rate()` mirrors `CommentsController::create()`
almost exactly: a generic redirect-based POST endpoint, an explicit
`ALLOWED_TYPES` allow-list (`article`, `download`) so the client can never
name an arbitrary type, the same `safeRedirectTarget()` local-path-only
guard. Wired into `ArticleController`/`DownloadsController`'s show
actions and their templates: a 1-5 star click-to-rate control (plain
`<button name="score" value="N">` — no JS required, matching this app's
server-rendered-forms-first posture) that shows the site's average +
count to everyone, and only renders the interactive stars for users who
both hold the capability and are logged in — a guest or a member without
the grant sees the summary only, never a form that would just redirect
them away. `isEnabled('ratings')`-gated like every other optional-module
integration in this app — disabling the module hides the whole widget on
both content types, not just one.
**Verified against the live dev server, not just code review**: rated a
real article as `modtest_member` through the actual UI and confirmed
"4.0 / 5 (1 rating)" with 4 filled stars rendered immediately, confirmed
via direct MySQL query that exactly one row existed with the right score;
re-rated the same article to a different score and confirmed via query
that the existing row updated in place rather than a second row
appearing; independently rated a real download file and confirmed both
ratings coexisted correctly, scoped to their own `ratable_type` (an
article rating and a download rating for the same user, same session,
never collided or overwrote each other); logged out and confirmed a
guest sees the average/count summary but no star-click controls at all;
sent a raw unauthenticated POST directly to `/ratings` and confirmed a
redirect (not a crash, not a silent no-op) — the same login-redirect
guard every other write action in this app uses. Confirmed the module
self-registered correctly on first real request after being added — `bin/
install.php` only runs migrations directly and doesn't touch
`ModuleManager`, so the new capability/`core_modules` row didn't exist
until the dev server was actually hit once; worth remembering for any
future new-module addition verified via CLI-only testing. All test rating
rows deleted afterward; the `ratings.create` grant on the `member` role
was deliberately left in place rather than reverted, matching this
project's established pattern of accumulated test-session grants on that
role (same as `comments.create`, `forum.create_topic`, etc. already were).
**Deliberately not built**: rating breakdowns (e.g. a 5/4/3/2/1-star
distribution bar chart — nobody asked for more than an average + count);
list-page average display (article/download index pages don't show star
averages inline, only the individual show pages do — a natural, cheap
follow-on if wanted, not built speculatively); half-star precision (whole
1-5 integers only, matching how the vision notes describe it plainly as
"ratings" with no finer-grained spec).

## Member Notes / Staff Notes ✅ (SHIPPED 2026-07-17, second item of the new 5-hour block)

**Why**: confirmed real want in the Member system parity backlog — admin-
visible notes on a member's account (distinct from the separately-tracked
"admin scratchpad" backlog item, which is a general staff-to-staff space
not tied to any one member).

**What shipped**: a new `member_notes` table (core migration 006, not a
toggleable module — it's an extension of user management, which itself
isn't module-gated) — append-only by design, no edit, only add or delete;
a wrong note gets deleted and replaced with a corrected one rather than
silently rewritten, since a note is a point-in-time staff observation,
not a document with a meaningful revision history. Gated by the existing
`users.manage` capability rather than a new one — one more capability
just for notes would be granularity nobody asked for, the same "one
queue, one capability" reasoning trash/moderation already established.
This required building something that didn't exist yet: a per-member
admin detail page (`/admin/users/{id}`) — the admin user screen before
this was a single flat list with inline role checkboxes and no detail
view to link to. The new page shows the member's basic info, the same
role-editing control the list page already had (moved here, not
duplicated logic — `UsersController::show()` reuses the exact same
`PermissionEngine` calls `index()` already made), and the notes thread.
The users list page's username is now a link into this new detail page.
**Verified against the live dev server, not just code review**: added a
real note to a real member through the actual UI and confirmed it
rendered immediately with the correct author name and timestamp,
confirmed via direct MySQL query the row existed with the right
`user_id`/`author_id`/`body`; deleted it through the UI and confirmed via
query it was actually gone, not just hidden; confirmed a genuinely
non-admin account (`modtest_member`, lacking `users.manage`) got a real
`403 Forbidden` hitting `/admin/users/6` directly — not a login redirect,
proving the capability check is real, not just the session being stale.
**Deliberately not built**: note editing (add/delete only, per the
append-only reasoning above); a notes-only view across all members (this
is per-member context, not a moderation-style queue); rich text or
attachments on notes (plain text only, matching the "quick internal
observation" scope this was asked for, not a document system).

## Profile Banner Image ✅ (SHIPPED 2026-07-17, third item of the new 5-hour block)

**Why**: confirmed real want in the Member system parity backlog,
explicitly noted as distinct from the existing `avatar_url` — a wide
header image on a member's profile, not the small avatar shown next to
their username.

**What shipped**: a new `banner_url` column on `users` (migration 004,
users module), following the exact same shape `avatar_url` already
uses — a plain pasted image URL, not a file-upload subsystem (this app
has no per-user upload storage-quota/moderation story yet, and nothing
asked for one here). `AuthService::updateProfile()` and
`ProfileController::update()` both gained the one new field alongside
the three that already existed; the profile page renders the banner
(when set) as a wide, height-capped image above the member's basic info,
above the same edit form the avatar/about-me/signature fields already
live in.
**Verified against the live dev server, not just code review**: saved a
real banner URL through the actual profile form as `modtest_member`,
confirmed via direct MySQL query the column held the correct value,
confirmed the `<img>` element rendered with the correct `src` on page
reload; cleared it back to empty and confirmed the column round-tripped
to `NULL` (matching how `avatar_url`/`about_me`/`signature` already
handle an empty string), restoring the test account to its prior state.
**Deliberately not built**: file upload (URL-paste only, matching
`avatar_url`'s existing precedent exactly — introducing an upload flow
for banners while avatars still don't have one would be an inconsistent
half-step, not a real one); banner cropping/positioning controls (the
image is simply shown at full width, height-capped and cropped via CSS
`overflow:hidden` — no per-user pan/zoom offset stored).

## Link Directory ✅ (SHIPPED 2026-07-17, fourth item of the new 5-hour block)

**Why**: a top-line item in the original vision notes, peer to forum/
downloads/calendar in the original feature list, that never became a
tracked module — the last open item in the Organization tools parity
section.

**What shipped**: a new `links` module — `link_categories` + `links`
(category/content shape, same as `downloads`/`classifieds`), member-
submittable (gated by a new `links.create` capability, mirroring
`downloads.upload`'s posture — this is user-generated content, not
admin-only curation) with admin category management and deletion gated
by `links.manage`. Every public link listing points at an internal
`/links/{id}/visit` redirect rather than the external URL directly — the
same "track then redirect" shape `downloads`' download action already
uses for `download_count`, applied here to `click_count`. Submitted URLs
are validated server-side to be well-formed `http`/`https` links before
being stored (`FILTER_VALIDATE_URL` plus an explicit scheme check) — not
a defense against a determined bad actor with `links.create` (nothing
stops a malicious link's *destination* from being bad, same trust model
this app already applies to BBCode `[url]` tags and comment bodies), just
a basic "don't store garbage" guard. The admin links screen shows each
link's category, submitter, and click count, with a delete action per
row. Registered in the sidebar's Content group in
`admin-layout.php` alongside Downloads — a curated content list, not a
Site Tools utility. Since `links` soft-deletes exactly like every other
content module, it was also added to `TrashService::TYPES` as part of
this same pass — a link has no per-item show page (only the directory
index and the click-tracking redirect), so its trash entry's "once
restored" URL points at `/links`, the same "link to the index" fallback
`org_file`'s trash entry already established.
**Verified against the live dev server, not just code review**: created
a real category and submitted a real link through the actual member-
facing form as `modtest_member` (a non-admin account), confirmed via
direct MySQL query the row saved with the correct `submitted_by`;
visited the tracked redirect twice — once via the browser, once via a
raw `curl` request — and confirmed both a real `302` to the exact
submitted URL (`curl`'s `%{redirect_url}` showed `https://weather.gov/`,
not a placeholder) and `click_count` incrementing to 2 in the database,
not just in a rendered page; confirmed the admin links screen showed the
correct category name, the correct submitter username (resolved from a
real `AuthService::findById()` lookup, not the raw id), and the correct
click count; deleted it through the real admin UI and confirmed via
query it was soft-deleted, not hard-deleted; confirmed the deleted link
then correctly appeared in `/admin/trash` with the right label and URL,
and that clicking Restore there genuinely cleared `deleted_at`. All test
data (the link, the category) deleted afterward.
**Deliberately not built**: link rating/voting (not asked for in the
vision notes for this feature, unlike articles/downloads); a "suggest an
edit" flow for existing links (submit-new only — editing an existing
link is an admin-only action today, same as most other content types'
"only the author or an admin" pattern, not built as a separate feature
here); category assignment enforcement beyond a required dropdown (no
category auto-suggestion or tagging).

## Addons & Themes: User-Uploadable Plugin System ✅ (SHIPPED 2026-07-17, fifth item of the new 5-hour block, same day it was proposed)

**Why**: raised the same day after the newsletter pitch came back "only 3
of 8 clubs want it" — rather than keep hand-building every one-off club
request as a core module every install carries, the user's call (accepted
by the groups) was to make features like that an **official plugin**
instead. This is the plugin/theme system that idea depends on — the
newsletter itself is still unbuilt and unscoped, but the infrastructure
to eventually ship it (and anything else club-specific) as an optional
install now exists.

**Status update, 2026-07-18: all 8 of 8 clubs have now signed off** on
the on-site multi-page newsletter design (Issue → ordered pages,
table-of-contents + Next/Previous nav, no email — see the 2026-07-17
design notes preserved in project memory) — full unanimous approval, up
from an initial 7/8 earlier the same day. Some clubs are calling it a
"mini club magazine" rather than a newsletter; the existing Issue design
serves both framings without any change, it's a naming/marketing
difference per club, not a design fork. Went in as an **addon**, not
core, per the plan above. ✅ **Built and shipped 2026-07-19** — see
"Newsletter / Mini-Mag Addon" below for the full writeup. Confirmed
during blocks/widgets planning: an addon can register its own block via
`registerBlocks(BlockRegistry $blocks)` in its `Module.php`, identically
to how `ads`/`sponsors`/`ticker` do it — `ModuleManager::boot()` doesn't
distinguish an addon from a built-in module for this purpose. The
"Current/Latest Issue" block shipped with the addon itself, exactly as
planned.

**The core design decision, and why it's safe despite sounding
alarming**: in this codebase, both modules and themes already execute
arbitrary PHP with zero sandboxing — `ModuleManager::boot()` does
`require`/`require_once` on every file in a module's `services/`/
`controllers/` directories, and `TemplateEngine` does a plain `include`
on every template file. So "let an admin upload their own addon" is
**the same trust level as backend/FTP access already implicitly grants**
on any e107/SMF/WordPress install — not a new class of risk this app is
introducing, just the same one every comparable CMS plugin ecosystem
already operates under. Critically, this is also **not** the same threat
model as the signed Update Mechanism: that one had to be airtight because
a single bad developer-signed package could compromise all 8 clubs at
once. An addon/theme a club's own admin uploads can only ever affect
*that admin's own site* — each of the 8 clubs is a fully separate
install. No signature verification was built here, deliberately — it
would add ceremony without adding real protection against the actual
risk (a club admin choosing to trust a bad file), the same reason
WordPress/SMF plugin installs don't require one either.

**What shipped**:
- `SafeZipExtractor` (new, shared by both) — zip-slip-safe extraction:
  every entry name in the zip is validated (`..`, absolute paths, and
  null bytes all rejected) *before* anything is extracted, fail-closed
  the same way `UpdatePackageVerifier` already established for the
  signed update mechanism, just without a signature or a manifest-
  declared per-file hash list (there's no developer to sign against
  here — every file physically in the zip gets extracted once every
  entry name has passed validation).
- `AddonPackageInstaller` — an addon zip is a `module.json` at the root
  plus whichever of `services/`/`controllers/`/`migrations/`/`templates/`/
  `Module.php`/`routes.php` it needs — **exactly** the shape a built-in
  module already has. Installed into `storage/addons/{id}/`, where `{id}`
  comes from the validated manifest (never the uploaded filename), after
  confirming it doesn't collide with any existing built-in or custom
  module id.
- `ThemePackageInstaller` — same shape for `theme.json` + `templates/
  layout.php` (required — checked at upload time so a broken theme can't
  even be installed, rather than 500ing the first time someone activates
  it), installed into `storage/themes/{id}/`. Deliberately **not** under
  the app's own `themes/` directory — the signed Update Mechanism's
  `ALLOWED_PREFIXES` already includes `themes/`, so a future official
  update package could in principle touch anything under it; a club's own
  uploaded theme has to live somewhere no official update can ever reach.
- `ModuleManager` now scans a second, optional custom-addons directory
  alongside the built-in `core/modules/` — every already-working piece of
  module machinery (enable/disable, dependency checks, capability
  auto-provisioning, `boot()`) treats a custom addon exactly like a
  built-in module, since `list()`/`boot()`/etc. never cared which
  directory a module's manifest was found in to begin with. The only
  change needed was a `custom: bool` flag threaded through so the admin
  UI knows what's safe to offer a delete button for.
- `TemplateEngine` gained the same two-directory awareness on the theme
  side (an optional custom themes directory, checked first when
  resolving the *active* theme only — a child theme's declared `parent`
  still resolves against the built-in themes only, matching the existing
  "child themes extend a shipped one" mental model already noted under
  Stage 8) **and**, separately, a real bug caught before it shipped:
  `resolve()`'s fallback path for a module's own default templates only
  ever checked `core/modules/`, so an uploaded addon's own templates
  would never have been found without this fix — caught by reasoning
  through the render path before testing, not discovered live.
  `active_theme` is now a real `core_settings` row (read once at boot in
  `public/index.php`, same pattern `site_name` already uses) instead of
  the hardcoded `'default'` literal it was before this pass — theme
  switching genuinely does something now.
- `ThemeManager` (new) — the theme equivalent of `ModuleManager`'s
  discovery, but simpler: no enable/disable/dependency graph, just "which
  installed theme is active," backed by that one settings row.
- New admin capability `themes.manage` (own migration, auto-granted to
  admin/founder, same pattern `trash.manage`/`system.update` already
  set) — kept deliberately separate from `modules.manage`, since a club
  may reasonably want a designer/webmaster role that can manage
  visual presentation without also holding the power to enable/disable
  functional modules. Addon upload/delete reuses the existing
  `modules.manage` instead of a new capability — an installed addon *is*
  a module, the same concern that screen already gates.
- `/admin/modules` gained an Addons section (upload form, a Remove
  button shown only for `custom` modules, a "download the starter
  addon" link) folded into the existing screen rather than a separate
  page, since `ModuleManager::list()` already returns custom addons
  alongside built-in modules for free once the second directory was
  wired in. `/admin/themes` (new page) lists built-in + custom themes,
  Activate/Remove per row, its own upload form and starter-theme
  download link.
- `StarterPackageBuilder` (new, shared) builds each starter zip on
  demand from `core/starters/addon/` and `core/starters/theme/` — real,
  working skeletons (a functioning "My Addon" page with an example
  service; a "My Theme" layout structurally close to the default theme
  so the diff is obvious), not just placeholder text — rather than a
  pre-built binary that could silently drift out of sync with the source
  the moment either starter directory gets edited later.
**Verified against the live dev server with real zips, real uploads,
and a real deliberately-malicious payload — not just code review**:
downloaded the real starter addon zip via an authenticated `curl`
session, uploaded it straight back unmodified, and confirmed the full
pipeline for real — the module auto-registered on the next request
(`bin/install.php` alone doesn't trigger `ModuleManager`'s discovery,
only a real HTTP request does — worth remembering for any future
module-adjacent CLI-only testing), showed up in `/admin/modules` as
"Addon (custom)", its nav item appeared in the site header automatically,
and its own page at `/my-addon` rendered its real template content
end-to-end through the exact same `App::renderPage()`/SEO pipeline every
other page uses — proving the `TemplateEngine` custom-modules-directory
fix actually works, not just that it compiles. Deleted it and confirmed
the directory, the `core_modules` row, and the route (`/my-addon` → 404)
were all genuinely gone. Repeated the same real-upload discipline for
the starter theme: uploaded it, activated it, and confirmed via a raw
`curl` fetch of the homepage that the actual rendered HTML changed
(header color and footer text both switched to the custom theme's,
not just a "seems to work" glance) with the admin panel's own chrome
confirmed completely unaffected (still its own blue accent, since
`renderAdminLayout()` never goes through the theme system at all);
switched back to the default theme and deleted the custom one,
confirmed via query the `active_theme` setting and the filesystem both
ended up clean. **Every attack/misuse scenario in the threat model above
was tried for real, not reasoned about abstractly**: uploading the same
addon id twice was refused with a clear message and zero side effects
on the second attempt; a hand-crafted malicious zip with a
`../../../../../../tmp/...php` entry name was rejected before a single
byte was extracted anywhere — confirmed by checking the target path
genuinely never got written, not just trusting the error message; a
non-admin account correctly got a real `403 Forbidden` on `/admin/themes`
and the theme starter download (the brand-new `themes.manage` capability
had never been granted to that test account, unlike the pre-existing
`modules.manage` grant accumulated from earlier sessions' testing, which
correctly still worked). A full-project `php -l` sweep across every file
in `core/`, `public/`, and `bin/` came back clean before and after this
entire pass, and a broad smoke test across ten-plus public routes plus
several admin pages confirmed zero regressions from the two core-file
changes (`ModuleManager`, `TemplateEngine`) this required.
**Deliberately not built**: any kind of addon marketplace/discovery/
listing service (this is self-service upload only — an admin gets a file
from wherever they got it and uploads it themselves, same as
WordPress/SMF's manual-plugin-install path, not an in-app store); addon/
theme *updating* in place (re-uploading a fixed version currently means
delete-then-upload-again, a real but deliberately narrower v1 workflow —
matches this app's usual "narrower first pass" discipline); PHP sandboxing
or capability-scoping for what an installed addon's code can actually do
once running (explicitly out of scope per the trust-model reasoning
above — this would be a fundamentally different, much larger feature,
not a gap in this one); child themes extending a *custom* parent theme
(only built-in parents are resolved, a deliberate, documented constraint
in `TemplateEngine`, not an oversight).

## Friends & Following ✅ (SHIPPED 2026-07-17, working top-down through the remaining Vision Parity Backlog)

**Why**: the top-listed item in Member system parity. The original vision
notes name these as two genuinely separate capabilities, not one feature —
"Friends" and "Following" appear as distinct line items throughout, and
"Friend request" is called out as its own named notification type,
implying a request/accept model; "Following" appears in the profile-
features list alongside reputation/online-presence with no request step
described anywhere, read here as the more common one-directional,
unconfirmed "follow this member" pattern (not content/thread-watching,
which bookmarks already covers).

**A real, load-bearing gap found before writing any relationship code**:
neither feature can exist without a way to *view* another member's
profile, and no such page existed — `/profile` only ever showed/edited
your **own** account (`ProfileController::show()` has no username
parameter at all). Building that page first was a prerequisite, not
scope creep: `/members/{username}` (new, `MemberProfileController`) is a
read-only view of any member — avatar, banner, about me, rank, join
date — that both Friends and Following hang their buttons and counts off
of. `/profile` gained a "View public profile" link pointing at your own
copy of this same page.

**What shipped**:
- Two tables (`friend_requests`, `member_follows`), users module
  migration 005. `friend_requests` only ever stores `pending` or
  `accepted` — there's no stored `declined`; declining and unfriending
  are the same operation (delete the row), so a declined request doesn't
  permanently block someone from asking again later the way a lingering
  `declined` row would via the unique-sender-recipient constraint.
  `member_follows` is the same toggle shape `forum_post_likes`/
  `gallery_likes` already established.
- `FriendService::sendRequest()` — sending a request when the other
  person already has a pending request out to you is treated as an
  instant mutual accept (the same "you both already said yes"
  resolution most friend-request systems apply) rather than leaving two
  crossed pending rows sitting there. Returns a result specific enough
  (`'pending'` / `'auto_accepted'` / `'already_friends'` / `'already_
  pending'` / `'self'`) for the controller to know precisely when a
  notification is actually warranted — deliberately distinct from a
  first draft that conflated "just auto-accepted" with "already were
  friends," which would have sent a spurious "your request was
  accepted" notification to someone you'd been friends with for weeks;
  caught and fixed before it ever reached the live server.
  `FollowService::toggle()` mirrors `ForumService::vote()`'s existing
  low-ceremony posture — no capability needed, any logged-in member can
  follow, matching post/photo likes.
- Two new notification types, `friend.request` and `friend.accepted`
  (the latter fires both on a normal accept and on the auto-accept path,
  correctly aimed at whichever side didn't just click the button).
- `/friends` (new, `FriendsController`) — the "my relationships"
  dashboard: incoming requests (Accept/Decline inline), outgoing pending
  requests, and the current friends list. Both `/friends` and the theme
  layout's nav gained links; `/members/{username}` shows live friend/
  follower/following counts and the correct action button for whichever
  relationship state actually applies (Add Friend / Request Sent /
  Accept·Decline / Friends·Remove, and separately Follow/Unfollow).
**A second real bug, also caught before shipping — a PDO error, not a
logic error**: `SQLSTATE[HY093]: Invalid parameter number`, thrown live
by `FriendService::friendCount()` on the very first real page load.
Several new queries reused one named placeholder for a value that
appears twice in the same SQL (e.g. `WHERE sender_id = :user_id OR
recipient_id = :user_id`) — this codebase's PDO layer rejects that
outright, the exact reason `SearchService::bindLike()` and
`ForumService::bindIdList()` already exist as dedicated per-occurrence
placeholder generators. Every affected method (`friendCount()`,
`removeFriend()`, `relationshipStatus()`, `listFriends()`) was audited
and fixed to give each SQL occurrence of a value its own uniquely-named
placeholder, then every other new query in both services was re-checked
by hand for the same mistake before moving on.
**A third gap, security-shaped rather than a crash**: the profile
template never renders an Add Friend button on your own profile, but
that alone doesn't stop a hand-crafted direct POST to `/members/
{your-own-username}/friend/request` — `FriendService::sendRequest()` had
no guard against `senderId === recipientId` at all, unlike
`FollowService::toggle()`, which already had the equivalent self-follow
guard from the start. Added the same defense-in-depth check (a `'self'`
result, silently ignored by the controller) and confirmed live via an
actual crafted request that it's now a safe no-op, not just reasoned
through.
**Verified against the live dev server with two real accounts acting on
each other, not one account testing against itself**: `modtest_member`
sent `modtest_admin` a real friend request and followed them through the
actual public pages — confirmed via direct query and a real notification
row, not just a 302. Logged in as `modtest_admin` separately, saw the
real pending request on `/friends`, accepted it, and confirmed via query
the row flipped to `accepted` and the correct `friend.accepted`
notification landed on the *original sender's* account. Reset state and
deliberately re-triggered the auto-accept path in the opposite order
(admin requests member first, then member requests admin) — confirmed
exactly one row existed afterward (not two crossed pending rows) and
that the correctly-aimed acceptance notification went to whichever side
had sent first. Confirmed guests can view a public profile (200) but get
redirected to login on `/friends` and on every write action. All test
relationship rows deleted afterward, confirmed via query.
**Deliberately not built**: a public member directory/browsable list (no
`/members` index page — reaching a profile today means already knowing
the username, e.g. from `/friends` or a future author-name link; a
directory is a natural, separable follow-on); retrofitting existing
author-name mentions across the app (forum posts, comments, article
bylines, etc.) into links to the new profile page — real, valuable, and
explicitly out of scope for this pass, since it touches many templates
across many modules rather than being part of the relationship feature
itself; feed personalization (Activity Feed showing only people you
follow — the feed stays one global stream, same as it's always been;
following doesn't currently change what anyone sees anywhere outside
the profile page and the `/friends` dashboard); a "you have a new
follower" notification (not a named notification type in the vision
notes, unlike "Friend request," and Twitter-style follow systems don't
universally send one either).

## Achievement Badges ✅ (SHIPPED 2026-07-17, second item working down Member system parity)

**Why**: named as its own item in the original vision notes, distinct
from `ranks` (a single points-driven tier a member holds at a time,
already built) — a member can hold many badges at once, and the notes
describe them as a decorative recognition touch ("Member cards.
Achievement badges. All these little touches matter.") rather than a
mechanical points system.

**The scoping call, made explicitly rather than silently**: "achievements"
could plausibly mean either admin-awarded recognition or auto-triggered
rules ("award on your 10th post," "award on your 1-year anniversary").
Building auto-triggering would mean a real rules engine wired into
post-counts/event-attendance/tenure across many modules — a
substantially larger, separate feature. v1 ships **admin-awarded badges
only**, the same "narrower first pass, real decision not a silent drop"
discipline the neighboring `reputation` backlog item explicitly calls
for. Auto-triggered achievements are a deliberate, named cut below, not
an oversight.

**What shipped**: two tables (`badges`, `member_badges`), users module
migration 006 — `member_badges` is many-to-many with a nullable
`awarded_by` (which admin granted it, for accountability, matching the
`author_id`/`awarded_by`-style attribution already used throughout this
app). `BadgeService` — define a badge (name, description, an
`icon_url` following the exact same "pasted URL, no upload subsystem"
precedent `avatar_url`/`banner_url`/`og_default_image` already
established), award it to a member, revoke it, list a member's current
badges. Badge *definitions* live at `/admin/badges` (new
`BadgesController`); *awarding* a badge to a specific member happens
from the member detail page (`/admin/users/{id}`, the same page Member
Notes already lives on) rather than a separate "search for a user" flow
— a natural convergence with infrastructure that already existed. Both
reuse the existing `users.manage` capability rather than a new one —
badge management is a member-system concern already gated by that
capability, the same "one queue, one capability" reasoning applied
throughout this app; a dedicated capability would be granularity nobody
asked for. Badges show publicly on `/members/{username}` as a row of
pill-shaped chips (icon + name, description on hover) alongside the
friend/follower counts Friends & Following already added there, and on
the admin member-detail page with a Revoke action and an "award a badge
they don't already have" dropdown (the dropdown excludes badges already
held, computed once and reused rather than querying twice).
**Verified against the live dev server with a real award, not just a
form that renders**: created a real "Founding Member" badge through the
actual admin form, confirmed via query it saved correctly; awarded it to
a real member from their detail page and confirmed via query the row
recorded the correct `awarded_by` (the admin account that granted it,
not the recipient); confirmed it appeared immediately in both places it
should — the admin detail page with a working Revoke button, and the
public profile page as a real chip with the description in its tooltip
title, not a placeholder; revoked it and confirmed via query it was
genuinely gone from both places; confirmed a real non-admin account got
a hard 403 on both viewing and attempting to POST to `/admin/badges`
directly. All test data (the badge, the award) removed afterward. Full
project lint sweep and a broad route smoke test both clean.
**Deliberately not built**: auto-triggered/rule-based achievements (see
the scoping call above — a real, separate, larger feature, not folded
into this pass); badge icon upload (URL-paste only, matching every
other image-field precedent in this app exactly, not an inconsistent
one-off upload flow); a dedicated `badges.manage` capability (reuses
`users.manage`, per the "one queue, one capability" reasoning above);
retrofitting `strat_ranks.icon` (the roadmap's own note ties badges to
that already-unused column, but no rank-management admin UI exists at
all today — building one is a separate, unscoped feature this pass
didn't open up).

## Reputation ✅ (SHIPPED 2026-07-17, third item working down Member system parity — the one backlog item explicitly flagged as needing a real decision)

**Why**: unlike every other item in this section, the backlog entry for
`reputation` explicitly called for "an explicit decision when this is
picked up (build a real point/reputation system, or deliberately skip
it) rather than being silently dropped again." Asked the user directly
rather than picking silently — the answer was build it for real.

**A real system was already half-built and entirely unused**: `users.
points` and `ranks.min_points` have existed in the schema since Stage 2
(core migration 002), but nothing in the app had ever incremented a
point or promoted a rank — every account was permanently stuck at
"New Member" forever, and only that one rank was even seeded. Building
this meant two things: seeding a real rank ladder to promote *into*
(core migration 008 — Active Member/25, Veteran/100, Community Pillar/300,
deliberately simple thresholds, not tuned against real club data yet),
and a new `ReputationService` (core, not the users module, since it's
called from several unrelated content modules — forum, wiki, gallery —
the same reasoning `ContentResolver`/`TrashService` already live in
core) that awards points and auto-promotes in one call. Promotion is
naturally monotonic — nothing in this app currently deducts a point, so
`award()` simply checks whether the new total qualifies for a higher
rank than the member currently holds and promotes them in place, the
same "compute, don't invent a separate state machine" discipline this
app applies everywhere. A promotion fires a real `rank.promoted`
notification.
**The scoping call on what earns points**: forum topic (+2), forum
reply (+1), a post receiving a like (+1 to the post's *author*, never
the liker, and never on unliking — only a genuine new like counts), a
new wiki page (+2), a wiki edit (+1), a new gallery album (+1).
Deliberately excludes articles (admin-curated content in this app, not
member-generated) and comments (rewarding every comment would incentivize
low-effort comment spam, the opposite of what a reputation system
should reward). Self-likes are explicitly guarded against — liking your
own post awards nothing, closing an obvious gaming vector before it
ever shipped, not after someone found it.
**Verified against the live dev server with real cumulative activity,
not synthetic point-setting**: posted one real forum topic (+2, correctly
still "New Member," below the 25-point threshold) then 23 real replies
one at a time through the actual reply form, watching the running total
cross the threshold exactly at 25 points — confirmed via query the
account genuinely auto-promoted to "Active Member" at that exact moment,
and that exactly one `rank.promoted` notification exists, not one per
point-award call (the "already at this rank, do nothing" guard tested
for real, not just reasoned through). Liked the member's post as a
different real account and confirmed +1 landed on the post's *author*,
not the liker; unliked it and confirmed no point was deducted (there is
no deduction logic — this only proves the toggle-off path doesn't
accidentally award a second point); logged in as the post's own author
and liked their own post, confirming zero points awarded — the
self-like guard actually holds under a real request, not just in the
conditional's logic. Created a real wiki page and confirmed the +2
award landed correctly. Confirmed both `/profile` (own account) and
`/members/{username}` (public view) display the live rank name and
point total correctly. All test forum/wiki content deleted afterward,
and both test accounts' `points`/`rank_id` explicitly reset back to
their original values — a live points/reputation system is exactly the
kind of mutation that's easy to leave as accidental leftover test state
on a shared dev account, so this got the same explicit cleanup query as
every other stateful test in this app.
**Deliberately not built**: point deduction/decay (no action currently
removes points — a moderator "reputation penalty" action is a plausible
future addition, not built here); a visible point-history log (why
someone has the points they have isn't queryable anywhere beyond the
current total — the same "no generic content-lifecycle log" gap
`ActivityService`'s own docblock already names for this whole app, not
a new one); reputation leaderboards; gallery photo likes awarding
reputation (`GalleryService` has its own separate `toggleLike()`,
untouched by this pass — a natural, cheap follow-on if wanted, matching
the exact same shape forum post likes just proved out).

## Account Export / Deletion Workflow ✅ (SHIPPED 2026-07-17, fourth item working down Member system parity)

**Why**: the standard GDPR-style pair every real member system needs —
a way to get your own data out, and a way to close your own account —
neither of which existed. Both self-service (`/profile`) and an
admin-initiated equivalent (`/admin/users/{id}`) were built together
since they share the same underlying `AuthService` primitives and the
admin side is the natural moderation counterpart to the self-service
one.

**Export is a live-computed JSON download, not a cached snapshot** —
same "compute, don't invent a separate state machine" discipline this
app applies everywhere else. New `AccountExportService` (core, not the
users module, since like `TrashService`/`SearchService`/`ActivityService`
it reaches across every enabled content module for one purpose) returns
the member's own account fields plus a manifest (title, URL,
timestamp) of everything they've authored: forum topics, forum posts
(via a join back to the parent topic for the title/URL, the same
join-for-title pattern `TrashService` already established for exactly
this shape), wiki pages they've contributed a revision to, calendar
events, classifieds listings, and gallery photos — each gated behind
`ModuleManager::isEnabled()` so a disabled module's content is silently
omitted, not errored on. **Deliberately excludes `gallery_albums` and
`downloads_files`** — verified by reading both schemas that neither has
a clean single-column author attribution (an album has no author
column at all; a download file only tracks uploader via its child
`downloads_versions.uploader_id`), and guessing at authorship via a
fragile join was worse than leaving them out. Served as a real file
download (`Content-Disposition: attachment`) via a new
`Response::file()` helper, named `{username}-stratum-export.json`.

**Deletion is soft-delete only — the same universal discipline this
app uses everywhere, never a new mechanism.** `AuthService::
softDeleteAccount()` just sets `deleted_at`; authored content is
deliberately left untouched, not cascaded or anonymized, because every
author-name lookup in this app already falls back to "Unknown"
gracefully when `findById()` can't find a non-deleted user (an
existing pattern from `MemberNoteService::authorName()` and others,
not new code written for this feature). `Auth::user()` re-resolves the
account fresh from the DB on every request rather than trusting a
cached session value, so an active session is locked out on its very
next request with zero explicit session-kill step required — confirmed
live, not just reasoned through (see verification below).
**A new, narrow guard**: `AuthService::isLastAdmin()` refuses to let
the site's last remaining admin/founder delete themselves (or be
deleted), preventing a genuinely bricking mistake — an empty admin
panel with no way back in short of a database console. Modeled on the
same spirit `ModuleManager::NON_DISABLEABLE` already protects for the
`users` module itself. Self-service deletion additionally requires
re-entering your password (`/profile/delete`, via the existing
`findByCredentials()` check) — the one action on the profile page that
deserves that extra confirmation weight, since it's destructive and
every other profile action only needs a valid session + CSRF token.
Admin-initiated deletion (`/admin/users/{id}/delete`) skips the
password re-entry since it's already gated by `users.manage` + CSRF,
and gained a companion `restoreAccount()` / "Deleted accounts" table on
`/admin/users` (mirroring `/admin/trash`'s restore pattern) since an
admin fat-fingering a delete needs an undo path a member's own
self-service flow doesn't need to offer.

**A real bug, caught live, not by review**: `softDeleteAccount()`
originally used `SET deleted_at = :now, updated_at = :now` with a
single `:now` parameter bound once — this codebase's PDO layer
(non-emulated prepared statements) rejects a named placeholder that
appears twice in one SQL string even when the intended value is
identical both times, and this exact bug class had already bitten
`FriendService` four times earlier in this session. It surfaced as a
genuine HTTP 500 (`SQLSTATE[HY093]: Invalid parameter number`) on a
real `POST /profile/delete` request against a throwaway test account,
confirmed via a follow-up query that `deleted_at` correctly stayed
`NULL` (failed closed, not silently corrupted). Fixed by giving each
occurrence its own placeholder name (`:deleted_at`, `:updated_at`),
both still sourced from one locally-computed `$now`. Audited the
entire rest of `AuthService.php` line-by-line afterward and confirmed
no other instance of the pattern exists in that file.

**Verified end-to-end against the live dev server, using dedicated
throwaway accounts rather than risking the reusable `modtest_admin`/
`modtest_member` fixtures**: a plain throwaway member's self-service
delete correctly returned a 302 (not the earlier 500) with `deleted_at`
genuinely set; the same still-logged-in session's next request to
`/profile` correctly bounced to `/login`; a fresh login attempt with
that account's real credentials correctly got a 401 "Invalid" rather
than any success. A throwaway admin account (created via the real
admin UI, alongside the two genuine pre-existing admins) was
admin-deleted successfully (3 admins present, guard correctly allowed
it), then restored via the new "Deleted accounts" table and confirmed
to reappear in the normal member list with `deleted_at` cleared. The
last-admin guard itself was tested for real, not just reasoned
through: temporarily stripped the `admin` role from both genuine admin
accounts (capturing their exact original role sets first so they could
be restored precisely), leaving the throwaway account as the site's
sole admin — confirmed the real admin account's session immediately
lost `/admin/users` access on its very next request (permissions are
checked live, matching `Auth::user()`'s own re-resolution behavior),
logged in as the now-sole-admin throwaway account and confirmed
`POST /admin/users/{id}/delete` against itself was correctly refused
with "Cannot delete the site's last admin" and `deleted_at` stayed
`NULL`, then used that same session to restore both genuine admins'
original roles exactly and confirmed normal admin access returned.
All throwaway accounts hard-deleted via direct SQL afterward and all
cookie jars removed, leaving the dev DB and real fixtures exactly as
they were before testing began. `php -l` clean across all 9 touched
files; a final smoke pass over `/`, `/profile`, `/admin/users`, and
`/admin/users/{id}` all returned 200.

**Deliberately not built**: hard-delete / right-to-erasure (this app's
universal soft-delete discipline applies here same as everywhere else
— a true data-purge path is a distinct, much heavier feature, not an
extension of this one); export formats beyond JSON (no CSV/HTML
requested); anonymizing or reassigning a deleted member's content to a
"deleted user" placeholder account (the existing "Unknown" fallback
already covers the display case with zero new code, and this app
never does that kind of ownership-reassignment migration for any other
soft-deleted entity either); cascading deletion of a member's content
alongside their account deletion (their posts/pages/etc. are content
the club owns, not the individual — same reasoning already established
for why deleting a user doesn't delete their forum history today).

## Account Merge ✅ (SHIPPED 2026-07-17, fifth and final item working down Member system parity — that section is now 100% done)

**Why**: confirmed real want from `these are features clubs.txt` — clubs
migrating off e107/SMF/ocPortal routinely carry duplicate member
registrations (a member forgets they already signed up, or signs up
again with a different email), and closing the duplicate without losing
its content/history needed a real feature, not a manual DB fix each time
it comes up.

**A merge, not a delete-with-extra-steps**: new `AccountMergeService`
(core, same reasoning as `TrashService`/`AccountExportService` — it
reaches into every content module, not just users) reassigns *every*
piece of content and relationship state the "merge away" account
(source) ever created to the "keep" account (target), then soft-deletes
source via the exact same `AuthService::softDeleteAccount()` call
`deleteAccount()` already uses. **The scope turned out far larger than
export/deletion's** — a dedicated research pass across every migration
in the app found 39 table+column pairs referencing a user (author,
uploader, rater, recipient, reporter, etc.), split into two handling
strategies:
- **31 are a blind `UPDATE ... WHERE column = source`** — no UNIQUE
  constraint involves the user column, so source's rows just move
  (articles, forum topics/posts, wiki/article revisions, calendar
  events, classifieds, gallery photos, videos, comments, downloads,
  links, all six org_spaces content tables, member notes on both its
  subject and author sides, notifications on both recipient and actor
  sides, moderation reports on both reporter and resolver sides,
  donations/dues on both payer and recorded-by sides, membership
  application reviewer, ticker/org-announcement author, badge
  awarded-by, presence).
- **9 tables carry a UNIQUE constraint on the user column** (roles,
  badges-held, ratings, bookmarks, org membership, gallery/post likes,
  calendar RSVPs, poll votes) — a blind reassign would throw a
  duplicate-key error the moment both accounts hold the same role,
  rated the same item, or belong to the same org. Resolved per-row in
  PHP rather than clever self-join SQL (deliberately — this codebase's
  MySQL rejects an UPDATE referencing its own target table in a
  correlated subquery unless wrapped in an extra derived-table layer,
  and hand-verifying that trick across 9 tables was a worse bet than a
  straightforward fetch-compare-write loop): move source's row if
  target has no matching one, otherwise drop source's and let target's
  existing row win.
- **Friend requests and follows got their own handling**, not the
  generic dedupe path, because both are *directional* (two separate
  user columns, either of which might be source) — a blind reassign
  could produce a self-referential row (target friending/following
  itself) or collide with an existing target↔counterpart relationship
  in a way the generic single-column dedupe helper doesn't model. Both
  drop the self-referential or colliding row and keep the surviving one
  exactly like the generic dedupe path does.
- **Points are summed via the existing `ReputationService::award()`
  call**, not reimplemented — this re-checks rank promotion (and fires
  its notification) through the same code path every other point-award
  already goes through, rather than duplicating that logic here.
- **The whole operation runs inside one transaction** (`Database::pdo()`
  exposed the raw PDO handle for this — no prior code in this app used
  a transaction, this is the first). A merge touching ~40 tables that
  fails halfway through would leave a worse mess than the duplicate-
  account problem it exists to fix; a real error anywhere rolls back
  every reassignment already made in that call, not just the ones after
  the failure point.

**Deliberately excluded from the table inventory**: `pages`,
`wiki_pages`, `downloads_files`, `gallery_albums`, and
`org_spaces_gallery_albums` have no author/uploader column of their own
(the same finding `AccountExportService` already made) — reassigning
their child rows (`wiki_revisions.author_id`,
`downloads_versions.uploader_id`, `gallery_photos.uploader_id`) is
correct and sufficient, nothing on the parent table needs touching.

**Guarded by `users.manage`** (new `/admin/users/merge` form, linked
from the `/admin/users` index) — refuses a self-merge (source == target)
and refuses merging away the site's last remaining admin/founder, the
exact same `AuthService::isLastAdmin()` guard `deleteAccount()` already
uses, since a merge ends in source being soft-deleted just like a
regular deletion.

**Verified end-to-end against the live dev server with genuinely
colliding data, not just clean-path content** — this is the part that
actually proves the dedupe logic works, not just that it doesn't throw:
created two throwaway accounts and deliberately gave both the same
role, the same badge, both following the same third account, and both
holding a pending friend request to the same third account, plus one
each of a *non*-colliding follow and friend request and a real forum
topic (worth checking the simple-reassign path too, and to confirm
reputation points landed). After merging, confirmed by direct query:
the colliding role/badge/follow/friend-request each collapsed to
exactly one surviving row (target's), the non-colliding follow and
friend request moved cleanly to target, the forum topic's `author_id`
now points to target, and target's points correctly equal the sum of
both accounts' prior totals. Confirmed source came back `deleted_at`-set
and could no longer log in (401), target logged in normally afterward,
and the reassigned forum topic was still live and viewable under
target's name. Self-merge attempt correctly refused with a 302 + error
message. The last-admin guard reuses `isLastAdmin()`, already proven
correct via a live test in the Account Export/Deletion feature above —
not re-run destructively against real admin fixtures a second time for
this feature, since it's the identical check applied to the identical
column. `php -l` clean across all 5 touched files; all throwaway test
data (two accounts, one badge, one forum topic, all relationship rows)
hard-deleted afterward via direct SQL, confirmed via a final smoke pass
across `/admin/users`, `/forum/boards/off-topic`, and `/admin/badges`
all returning 200.

**Deliberately not built**: an undo/preview step showing exactly what
will move before committing (the confirm() dialog plus the transaction's
all-or-nothing guarantee were judged sufficient for an admin-only,
CSRF-gated action — a dry-run preview is a plausible future addition,
not built here); merging more than two accounts in one operation (call
it twice); any special handling for conflicting profile fields (avatar,
about_me, signature) — target's existing profile fields are left
untouched, source's are simply discarded along with the rest of its
now-soft-deleted row, matching how this app already treats a deleted
account's own fields as inaccessible everywhere else.

## Generic Surveys / Forms ✅ (SHIPPED 2026-07-17, first item working down Organization tools parity)

**Why**: the vision notes' "forms: volunteer, registration, surveys,
applications, custom fields" bucket under Stage 4 — `membership` already
covers custom sign-up fields and applications specifically, but a
standalone, admin-built, reusable form any logged-in member can fill
out (a club picnic headcount, an event volunteer sign-up, an ad hoc
survey) had no home anywhere in the app.

**A new `forms` module** (`core/modules/forms/`), built as a normal
built-in module rather than folded into an existing one — matches
`links`' precedent as the template this pass followed almost exactly
(migration → service → two controllers → routes → templates →
module.json), confirming that template scales cleanly to a fourth
content type this session (comments, ratings, bookmarks, links, now
forms all follow the identical shape). Four tables: `forms` (soft-
deletable, `draft`/`published`/`closed` status), `form_fields` (four
types: text, textarea, select-one-choice, checkbox-multi-choice, with
`position` ordering), `form_submissions` (`UNIQUE(form_id, user_id)` —
one submission per member per form), `form_submission_answers` (one row
per field, except checkbox which gets one row per selected option —
deliberately not a JSON-array column just for that one type, since it
makes tallying results a plain `GROUP BY value`).
**Fields are built one at a time via their own "add field" mini-form**,
not a dynamic JS field-builder — this app's admin UI is server-rendered
with no framework, and the same "add-one-thing-at-a-time" shape forum
polls' options and this session's own account-merge dedupe logic
already established was the natural fit here too, not a corner cut.
Choice-type options are stored as newline-separated plain text (one
option per line in a textarea), not JSON — simplest thing that actually
works for that admin flow.
**Results view**: raw per-submission answers in a table, plus an
automatic tally (`COUNT(*) GROUP BY value`) for every select/checkbox
field — no separate "generate report" step, computed live the same way
this app computes everything else that doesn't need caching at this
scale.
**Wired into the existing trash bin as a 20th type** (`TrashService::
TYPES['form']`) — mechanical, not new architecture, exactly the
extension its own docblock already predicted.

**Verified end-to-end against the live dev server, not just the create
path**: built a real 4-field survey covering all four field types
through the actual admin UI, published it, confirmed it appeared on the
public `/forms` index and rendered every field type correctly (text
input, textarea, populated `<select>`, three checkboxes). Submitted as
`modtest_member` with real answers including two checked boxes,
confirmed via direct query the checkbox produced two separate answer
rows (not a single delimited string). Reloading the form page correctly
showed "already submitted" instead of the form; a direct re-POST
attempt correctly stayed a no-op (still exactly one submission row) —
proving the `UNIQUE(form_id, user_id)` constraint holds under a real
double-submit, not just in a code read. Admin results page showed the
correct tally (Yes — 1, Salad — 1, Drinks — 1) and the raw submission
row with the member's real username and answers. Guard rails confirmed
live: a plain member hitting `/admin/forms` got 403, a guest hitting a
form page got redirected to `/login`, closing the form correctly pulled
it from the public index and 404'd direct access. Trash bin integration
confirmed live too: soft-deleting the form via `/admin/forms/{id}/delete`
surfaced it correctly in `/admin/trash`, and restoring it cleared
`deleted_at` while correctly preserving its `closed` status rather than
resetting it to `draft`. All test data (the form, its fields,
submission, and answers) hard-deleted afterward via direct SQL; `php -l`
clean across all 12 touched/new files; a final smoke pass across
`/forms`, `/admin/forms`, `/admin/trash`, and `/` all returned 200.

**Deliberately not built**: conditional/branching logic (show field B
only if field A = X); file-upload fields (this app's existing upload
subsystems — gallery, downloads — are a separate, heavier concern than
a generic form field type); anonymous/guest submissions (every
submission ties to a logged-in member, matching how most member-facing
create-flows in this app already work; `membership`'s own application
flow already covers the guest-facing signup case separately); editable
submissions (a member can't revise their answer after submitting — the
UNIQUE constraint that blocks a duplicate submission also blocks a
resubmission; a deliberate v1 simplicity cut, not an oversight);
exporting results as CSV (the admin results table is the only export
path today, matching how this app's other admin list views work).

## Calendar: Maps & Attendance Tracking ✅ (SHIPPED 2026-07-17, closing out Organization tools parity)

**Why**: the last two open items in Organization tools parity — "event
locations aren't linked to any map view" and attendance tracking
explicitly called out as distinct from RSVP in the vision notes ("RSVP
is who says they're coming, attendance is who actually showed up").

**Maps — purely presentational, zero schema change and no API key
required**: `calendar_events.location` was already a free-text field
with nowhere to click through to. The event page now shows a "View on
map" link (Google Maps search URL) plus an embedded `<iframe>` preview
using Maps' key-less `output=embed` search endpoint — deliberately not
the paid/keyed Maps Embed API, since "unknown shared hosting, no
guaranteed API keys" is this whole project's hosting premise (see
`stratum-production-context` memory). Both just URL-encode the existing
location string; no geocoding, no new column, no new capability.

**Attendance — a new `calendar_attendance` table, deliberately separate
from `calendar_rsvps`** rather than an `attended` column bolted onto
the RSVP table: someone can attend without ever RSVPing (a walk-in),
and the two concepts have different owners (a member sets their own
RSVP; an organizer sets attendance for someone else, including people
who never RSVP'd at all). Check-in takes a **username**, not a
user_id-select limited to the existing Going list — the entire point of
tracking this separately from RSVP is covering walk-ins, so limiting
the UI to already-RSVP'd members would have defeated the feature.
`checkIn()` is idempotent (a duplicate check-in silently no-ops rather
than erroring, backed by `UNIQUE(event_id, user_id)`), and reuses the
existing `calendar.manage` capability rather than inventing a new one
— same "reuse an existing capability for a closely-related admin
action" pattern this session's badges feature already established.

**Verified live against the dev server**: created a real event with a
real address, confirmed both the map link and the iframe embed rendered
with the correctly URL-encoded location. Checked in a real member as a
walk-in (never RSVP'd) via username, confirmed the row landed with the
correct `checked_in_by` admin id. Re-submitted the identical check-in a
second time and confirmed via direct query it stayed exactly one row,
not two — the idempotency guard holding under a real duplicate request,
not just reasoned through. Removed the check-in and confirmed the row
was gone. Confirmed a plain member (no `calendar.manage`) got a 403
attempting to check someone in. All test data cleaned up afterward;
`php -l` clean across all 5 touched files; final smoke pass across
`/calendar`, `/admin/calendar`, and `/` all returned 200.

**Deliberately not built**: self-check-in (a member marking their own
attendance, e.g. via a QR code or geofence at the venue) — v1 is
organizer-driven only, matching a plain in-person roll call; a
geocoded/pinned map (address text is passed straight through to Maps'
own search, no lat/lng stored or validated — good enough for "show me
where this is," not precise enough for structured location data or a
multi-event map overview, which would be a separate, larger feature);
attendance-based reporting or CSV export (the per-event list is the
only view today, same scope cut Surveys/Forms made for the same
reason).

## Downloads: Mirrors & Virus Scanning ✅ (SHIPPED 2026-07-17, first item working down Media & commerce parity)

**Why**: two long-open items on the downloads module's own backlog line.

**Mirrors** — a new `downloads_mirrors` table (file_id, label, url):
admin-curated external alternate locations for a file (a club's own
Google Drive backup of something large, a secondary host), shown
alongside the primary local download link on the file's page. Purely
additive — the local file stays the canonical download, mirrors are
optional extras.

**Virus scanning — the harder design question was what happens when no
scanner exists**, not the scanning itself. This app can't assume
ClamAV is installed on an unknown shared host (the entire premise
behind every hosting-related decision this session), so a new
`ClamAvScanner` (core, not downloads-specific — reusable by any future
upload path) shells out to a system `clamscan` binary *if one is
actually present and callable* (checks `exec`/`shell_exec` aren't
disabled via `disable_functions`, then `command -v clamscan`), and
returns a **third state, `unavailable`, distinct from both `clean` and
`infected`** — new `downloads_versions.scan_status` column, defaulting
to `pending` until `storeVersion()` runs the scan synchronously right
after the file lands on disk. `unavailable` fails open (file stays
downloadable, matching this module's pre-existing behavior exactly);
only a genuine `infected` result — a real scanner, actually run, actually
flagging something — blocks the download with a 403. Getting this
distinction backwards either way would have been wrong: treating
"unavailable" as "infected" would break downloads entirely on most
shared hosts; treating it as "clean" would be false confidence about a
file nobody actually scanned.

**Verified live**: confirmed this dev machine has no `clamscan`
installed (the realistic case for most target hosting) and a real
upload correctly landed at `scan_status = 'unavailable'`, stayed fully
downloadable, and showed an admin-only "scan unavailable" notice (not
shown to plain members, who have no action to take on it). Manually
flipped a version's `scan_status` to `infected` in the database to
exercise the blocking path without needing an actual malicious test
file: confirmed both direct-download routes returned a 403 with a clear
message, and the file page itself showed the block state instead of a
download link. Added a real mirror, confirmed it rendered and opened
correctly, confirmed a non-admin got a 403 attempting to add one via a
crafted direct POST, removed it. All test data cleaned up (DB rows and
the physical uploaded test file) afterward; `php -l` clean across all 6
touched files; final smoke pass on `/downloads`, `/admin/downloads`,
and `/` all 200.

**Deliberately not built**: scanning already-stored versions retroactively
(only new uploads get scanned going forward — a bulk-rescan admin action
is a plausible follow-on, not built here); any scanner besides ClamAV
(no external API-based scanner, for the same "no guaranteed API keys on
unknown shared hosting" reasoning that shaped Maps and the Update
Mechanism); quarantine/deletion of infected files (they stay on disk,
just permanently blocked from being served — an admin can still see and
manually remove them via the existing file-delete action).

## Video Playlists ✅ (SHIPPED 2026-07-17, second item working down Media & commerce parity)

**Why**: already flagged in the roadmap as a deliberate Stage 5b cut —
an admin-curated ordered collection of existing videos, the same
"container + ordered membership" shape gallery albums already
established, just for videos instead of photos.

**Two new tables** (`video_playlists`, `video_playlist_items` with a
`position` column and `UNIQUE(playlist_id, video_id)` to prevent adding
the same video twice). **Reordering is a simple up/down swap between
adjacent items**, not drag-and-drop — this app's admin UI has no
client-side JS framework, and the same "no dynamic JS, add/reorder one
step at a time" discipline this session's Surveys/Forms fields and
forum polls' options already established applied here too. Reuses the
existing `video.manage` capability rather than a new one.

**Verified live, including an accidental but useful edge case**: built
a real playlist with three videos and reordered them via the actual
up/down buttons, confirming the swap operates correctly on the full
underlying position list. While setting up the test, one of the video
ids used turned out to already be soft-deleted from earlier session
testing (2026-07-14, unrelated to this feature) — `listPlaylistVideos()`'s
join correctly excluded it from the rendered list entirely rather than
erroring or showing a broken row, confirming the "silently degrade on
an orphaned/deleted joined row" pattern `TrashService`'s own child-row
joins already established holds here too, for free. Confirmed a
duplicate-add attempt was silently rejected (no error, no duplicate
row — the `UNIQUE` constraint doing its job). Confirmed a non-admin got
a 403 attempting to create a playlist. Added the type to `TrashService`
as its 21st entry and confirmed a deleted playlist surfaced there
correctly. All test data cleaned up; `php -l` clean across all 9
touched files; final smoke pass on `/videos`, `/videos/playlists`,
`/admin/video`, and `/` all 200.

**Deliberately not built**: nested/hierarchical playlists; auto-populated
playlists (e.g. "everything in this category," computed rather than
curated) — v1 is entirely admin-curated, one video at a time, matching
how every other "curated collection" feature in this app works;
playlist thumbnails distinct from the first video's own thumbnail.

## Site Search v1.1: Gallery & Video Content ✅ (SHIPPED 2026-07-17, third item working down Media & commerce parity)

**Why**: noted as a known v1 gap in the original Site Search entry —
gallery photo captions and video titles/descriptions were the two
content types SearchService's UNION-ALL registry didn't cover yet.

**Two new branches**, following the exact established pattern (one
`isEnabled()`-gated branch per content type, each occurrence of the
search term gets its own bound placeholder — `bindLike()` already
existed specifically to prevent the recurring PDO duplicate-placeholder
bug this session hit repeatedly elsewhere): gallery photos search by
caption only (photos have no title of their own — same
`COALESCE(caption, 'Photo #id')` fallback `AccountExportService`
already established for display purposes, reused here for the search
result's title); videos search by title and description, both hosted
(YouTube/Vimeo) and locally-uploaded videos alike, since the branch
only reads `videos` table columns, not the file itself.

**Verified live against real content**: set a unique test caption on a
real gallery photo and a unique test description on a real video,
searched for each exact string, and confirmed both surfaced correctly
labeled ("Gallery Photo" / "Video") with the right snippet — not
inferred from reading the query, an actual search request against the
live dev server. Reverted both test values afterward. `php -l` clean;
smoke pass on `/search` and `/` both 200.

## RSS Auto-Articles ✅ (SHIPPED 2026-07-17, fourth item working down Media & commerce parity — closing everything in this section except the paywall item)

**Why**: `rss_aggregator` (Stage 4c) could aggregate, display, and
export feeds, but never auto-published incoming items as real site
articles — a club wanting a syndicated feed to just publish itself with
zero manual copy-pasting had no path to that.

**The real design problem wasn't the publishing call, it was finding an
author**: `articles.author_id` is `NOT NULL`, but `RssFetcher::
fetchAndStore()` already runs from two different call sites —
an admin's "Refresh now" button (has a logged-in admin) **and**
`rss_aggregator`'s own pre-existing `cron.daily` listener (fetches
every enabled source once a day, no admin session in scope at all).
Resolving the author live would have silently broken on the cron path.
Fixed by capturing the author **once, at the moment an admin turns
auto-publish on for a source** — new `rss_sources.article_author_id`,
set by `RssSourceService::setAutoPublish()` to the admin's own id, read
back later regardless of which path triggered the actual fetch.
**Every auto-published article opens with a clear, linked attribution
line** ("Originally published by *(source name)*", linking to the
original item URL) before the feed's own description — this is
syndicated content, not this site's own writing, and republishing it
silently with no attribution would be the wrong default. New
`rss_items.article_id` tracks which article (if any) a given item
produced, both preventing double-publish on a re-fetch (the existing
per-item guid dedup already short-circuits before the publish call even
runs) and giving the admin list a way to trace an item to its article.

**Verified against a real, live, external feed, not a fabricated one**:
created a throwaway RSS source pointed at a real public feed (BBC News),
turned auto-publish on, and triggered a real fetch — 45 real items came
back and all 45 correctly became real, immediately-published articles
with the right author id and a working attribution link, confirmed by
loading one of the actual generated article pages and seeing the
attribution render. Re-triggered the exact same fetch and confirmed
zero new articles were created the second time (the guid dedup holding
under a genuine duplicate request, not just reasoned through). Turned
auto-publish back off and confirmed `article_author_id` cleared. All 45
test articles (plus their revisions), the RSS items, and the throwaway
source were hard-deleted afterward via direct SQL — this is real
external content, not synthetic test data, so cleanup mattered more
here than for most other features this session. `php -l` clean across
all 6 touched files; final smoke pass on `/admin/rss`, `/feeds`,
`/articles`, and `/` all 200.

**Deliberately not built**: per-source category assignment for
auto-published articles (they land uncategorized; an admin can
categorize afterward — a category picker on the auto-publish toggle is
a plausible follow-on); editing an auto-published article's syndicated
body before it goes live (it publishes immediately and as-is, matching
"hands-off" being the entire point of turning this on); un-publishing
or deleting the source article when the original RSS item is later
removed from the upstream feed (no such "item removed" signal exists in
RSS 2.0 to detect in the first place).

## Maintenance Mode ✅ (SHIPPED 2026-07-17, first item working down Admin system parity)

**Why**: the last standard CMS admin feature this app didn't have —
a way to take the public site offline for everyone except staff while
doing risky work (a migration, a theme change, a real upgrade), without
losing the ability to log in and fix things.

**Checked in `public/index.php`, not a route or hook** — the same
"needs to run before literally anything else" reasoning Who's Online
presence tracking already established for this exact file. Two new
free-form `core_settings` keys (`maintenance_mode`, `maintenance_message`),
no migration needed since settings are a plain key-value table.
**`/login` and every `/admin/*` route stay exempt** so a staff member
can still authenticate and turn it back off — the entire failure mode
this needs to avoid is an admin locking themselves out. **Anyone with
`admin.access` bypasses it entirely**, not the narrower `users.manage`
this session reused for several other features — `admin.access` is the
actual blanket "can get into the admin panel at all" capability
`App::renderPage()` already exposes as `isAdmin`, the semantically
correct one here since it works right even for a club's custom
lower-privilege admin role that doesn't happen to also hold
`users.manage`. New `Response::maintenance(siteName, message)` builds
the 503 page from plain string params, no DB access inside `Response`
itself — same stateless posture `renderErrorPage()` already has.
**Deliberately text-only, no Stratum branding** — unlike the 403/404/500
pages (which are Stratum-the-product's own illustrated art), this page
renders under the *club's own* site name, so mixing in Stratum's logo
would be wrong the same way it would be in the normal page header.

**Verified live against the full behavior matrix, not just the on/off
switch**: guest hitting the homepage while enabled correctly got a 503
with the real custom message rendered; a guest hitting `/login` stayed
a normal 200 (exemption holding); a signed-in admin (`admin.access`)
browsing the homepage correctly bypassed it entirely (200, normal page);
the same admin's own `/admin/settings` page stayed reachable regardless;
a plain logged-in member with no admin capability still correctly got
blocked (503) — confirming the bypass is capability-based, not just
"is logged in." Unchecking the toggle (via a real form submit that
omits the checkbox entirely, the actual browser behavior for an
unchecked box) correctly persisted `maintenance_mode = 0` and restored
normal 200 access for both the guest and the member session
immediately, no caching/staleness. `php -l` clean across all 4 touched
files; final smoke pass across `/`, `/forum`, `/admin`,
`/admin/settings`, and `/login` all correct.

**Deliberately not built**: an IP allowlist (only the capability-based
staff bypass exists — no "let these specific IPs through too" option);
a scheduled/timed maintenance window (it's a manual on/off toggle only,
no "auto-enable at 2am, auto-disable at 4am" scheduling); a maintenance
page matching the site's own theme/branding beyond its name (kept
deliberately minimal and theme-independent, so it still renders even if
whatever's being fixed is the theme itself).

## System Health Page ✅ (SHIPPED 2026-07-17, second item working down Admin system parity)

**Why**: the dashboard's own "System Status" panel only ever showed two
bare version strings (PHP/MySQL) — real diagnostic value (is the DB
actually reachable, is anything unwritable, has cron even run once)
had nowhere to live.

**New `SystemHealthService`, a live counterpart to the web installer's
own `checkRequirements()`, not a shared call into it** — `install.php`
is deliberately standalone with no app bootstrap (its own docblock
explains why), so this duplicates that same short requirements list
(PHP 8.2+, five required extensions, four writable storage dirs) rather
than forcing a shared dependency between a pre-bootstrap script and a
fully-booted admin page. Adds what the installer's one-time check
can't: a live DB connectivity probe, real-time disk free space, the
actual PHP upload/execution ini limits in effect, cron's last real run
time (read back from the same `core_logs` table `Logger` already
dual-writes to — see the Log Viewer entry below, built right after this
one specifically because this page's own "N errors in the last 24h"
line needed somewhere to link to), and a 24-hour error count.

**One small in-session correction worth remembering**: the template's
byte-formatting helper was first written as a bare top-level
`function formatBytes()`, then converted to a closure before shipping
— this codebase already hit and explicitly avoided a "function
redeclared" fatal once before (forum's board-nesting helper, per that
feature's own writeup), and no other admin template declares a raw
function, so this stayed consistent with that established avoidance
rather than reintroducing the same risk.

**Verified live**: all 11 checks passed on the real dev environment
(PHP 8.5.4, all 5 extensions, all 4 writable dirs, DB connection);
confirmed real, non-fabricated values throughout — actual disk free
space, the real `upload_max_filesize`/`memory_limit` ini values in
effect, a genuine cron last-run timestamp read back from an actual
`bin/cron.php` run earlier this session, and (initially) 4 real error
log entries surfaced correctly with a working link through to the new
Log Viewer. Gated on `admin.access` (not the narrower `users.manage`),
matching the dashboard's own gate since this is materially the same
"basic admin panel access" tier of information. `php -l` clean across
all 5 touched files.

## Log Viewer ✅ (SHIPPED 2026-07-17, third item working down Admin system parity)

**Why**: `storage/logs/app.log` existed but had no admin UI over it —
and System Health's new "N errors in the last 24h" line needed
somewhere real to link to, built immediately after for that reason.

**A genuinely easy build because the hard part already existed**:
`Logger::log()` has dual-written every log entry to both the flat file
and a real `core_logs` table (level/message/context/created_at,
already indexed on `(level, created_at)`) since Stage 1 — this feature
is a UI over data that was already being captured correctly, the exact
same "confirmed premise" shape the Trash Bin build had. New
`LogService` reads the DB table rather than parsing the flat file,
since it's already structured: level filtering and pagination
(`?level=error|info`, `?page=N`, 50 per page) are plain SQL, not a
hand-rolled log-file parser. A "Clear all logs" action, gated the same
`admin.access` as viewing, since log noise accumulates indefinitely
otherwise with nothing else in this app that prunes it.

**Verified against real, non-synthetic log data**: the dev database
already had 16 genuine entries accumulated over this session's own
debugging (the PDO duplicate-placeholder bug fixes, a deliberate
notify-hook test failure from earlier work) — the level filter
correctly narrowed 16 total down to exactly the 7 real errors among
them, each with its actual logged message intact. Confirmed a plain
member (no `admin.access`) got a 403, not just an assumption from
reading the guard call. Triggered "Clear all logs" for real and
confirmed via direct query the table was genuinely empty afterward
(0 rows), and that System Health's own error count and "View log" link
correctly updated to reflect zero errors on the very next load — the
two new features' data dependency actually holding end-to-end, not
just each tested in isolation. `php -l` clean across all 5 touched
files; final smoke pass across `/admin/system/health`,
`/admin/system/logs`, `/admin/system/update`, `/admin`, and
`/admin/settings` all 200.

**Deliberately not built (both pages)**: log export/download; a
date-range filter beyond level (all-time or nothing); real-time
tailing/auto-refresh; alerting/notifications on new errors (an admin
has to actually visit the page to see them — a plausible future
addition, e.g. surfacing a dashboard badge when `recentErrorCount() >
0`, not built here).

## Update Checker ✅ (SHIPPED 2026-07-17, fourth item working down Admin system parity)

**Why**: `/admin/system/update` could already apply a signed package, but
nothing told an admin one existed to apply in the first place.

**Deliberately doesn't phone home to any Stratum-run service** — no such
public update server exists, and building one speculatively here would
be real hosting infrastructure, not a CMS feature. Updates are already
curated and signed by hand per club (`UpdatePackageVerifier`/
`UpdateApplier`), so this stays consistent with that model: a new
`UpdateChecker` fetches an **admin-supplied** manifest URL (a small JSON
file — `{"version":"x.y.z","notes":"...","download_url":"..."}` — hosted
anywhere the person distributing updates chooses to put it) and compares
its `version` field against the local `VERSION` file via
`version_compare()`. Same safe-fetch discipline (http/https only,
timeout, no redirect loops) `RssFetcher::fetchUrl()` already established
for "this app fetches an admin-configured external URL," duplicated
rather than shared since the two features don't otherwise overlap. The
manifest URL itself is saved as a `core_settings` row so it only needs
entering once.

**A real, non-obvious dev-environment gotcha hit and worked around during
testing, worth remembering**: pointing the checker at the same PHP
built-in dev server that was handling the test request (`http://
127.0.0.1:8791/...`) hung until curl's own timeout — the single-
threaded `php -S` dev server has no free worker to answer a request
that arrives while it's still busy handling the request that triggered
it, a self-request deadlock specific to that dev server, not a bug in
`UpdateChecker` (production, and any request to a genuinely different
host, doesn't have this problem). Verified instead against real,
separate external targets: a genuine external host for the raw fetch
mechanics, a deliberately-wrong URL scheme, and an unresolvable
hostname, each producing the correct distinct error message — then
spun up a second, independent throwaway PHP server on a different port
serving a real test manifest to verify the actual success path (update-
available, already-up-to-date, and current-newer-than-manifest, all
three `version_compare()` outcomes) end-to-end through the real admin
UI, not just unit-style. Confirmed the manifest URL setting persisted
and pre-filled correctly on reload. All test infrastructure (the
throwaway server, its process, the settings row) cleaned up afterward.
`php -l` clean across all 4 touched files.

**Deliberately not built**: automatic/scheduled checking (an admin has
to click "Check for updates" — no `cron.daily` polling, since that
would mean silently making outbound requests to an admin-configured URL
on a schedule with no one watching, a bigger blast-radius default than
this feature needs); one-click "download and stage" from the check
result (the download link just opens the URL — actually applying an
update still goes through the existing signed-package upload flow
immediately below on the same page, unchanged).

## Backup Manager ✅ (SHIPPED 2026-07-17, fifth item working down Admin system parity)

**Why**: no way to get a copy of a club's data out of the app existed
anywhere — a real gap given this app's whole premise is real clubs
trusting it with real member data.

**Pure-PHP database dump, deliberately not shelling out to
`mysqldump`** — same "can't assume a binary exists on unknown shared
hosting" reasoning `ClamAvScanner` already established for virus
scanning, applied to backups too. New `BackupService` walks
`SHOW TABLES`, and for each one writes its real `SHOW CREATE TABLE`
statement plus batched `INSERT` statements (500 rows per statement,
values escaped via `PDO::quote()` — genuine SQL escaping, not
`addslashes()`), **streamed directly to a file handle rather than built
as one in-memory string first** — a naive "build the whole dump as a
string, then write it" approach risks `memory_limit` on a real club's
data in a way streaming doesn't. Gzip-compressed when the `zlib`
extension is available (checked live, not assumed), plain `.sql`
otherwise — same "detect, don't assume" posture as the scanner's
`clamscan`-availability check. **A new, narrow `system.backup`
capability** rather than reusing `admin.access` — a full dump contains
every member's password hash and PII in one file, meaningfully more
sensitive than anything else `admin.access` gates, the exact same
reasoning migration 004 already used to justify `system.update` getting
its own capability instead of reusing the blanket one.

**Filenames are the only thing user input ever touches** (download/
delete take a filename from the URL) — validated against a strict
`stratum-backup-YYYY-MM-DD-HHMMSS.sql(.gz)?` regex before ever touching
the filesystem, refusing to resolve anything that doesn't match rather
than attempting to sanitize a path — the same "validate, don't sanitize"
discipline the Addons zip-slip protection already established this
session.

**v1 is database-only, not a full-site archive** — `storage/uploads/`
can run into gigabytes for an active club (gallery/downloads content),
and streaming a ZIP of that alongside a SQL dump through one web
request is a meaningfully bigger feature than this pass scopes, not an
oversight; the backups page says so explicitly and points admins at
their host's own file-backup tools for uploads.

**Verified with the strongest test this session has run against any
generated artifact: an actual restore, not just "the file looks
right."** Created a real backup against the live dev database (0.26s,
33KB gzipped from a 162KB dump) and confirmed the `.gz` was genuinely
valid via `file`/`zcat`. Restoring into a true separate scratch
database wasn't possible (the app's DB user has no grant to create
other databases in this environment), so the dump was instead restored
into the **same** database under renamed table and constraint names
(`sed`-rewriting `strat_` → `stratum_test_` and constraint name
prefixes) — surfacing a real, already-documented pre-existing app
limitation along the way (MySQL foreign-key constraint names are
schema-scoped, not table-scoped, so two same-shaped table sets can't
coexist in one DB without a rename — the exact issue the Web-Based
Installer's own writeup already flagged and deliberately left unfixed
as narrow/deferred; this is a restored-copy-in-the-same-DB artifact of
the verification method, not a real-world restore scenario, where the
target database is always empty). The renamed restore succeeded outright
(exit code 0, all 81 tables created), and **every single row count
matched exactly** between live and restored tables (users 7/7, articles
8/8, forum topics 5/5) plus the full 81/81 table count — genuine proof
the dump is complete and correct, not just well-formatted. All 81 test
tables dropped afterward. Also confirmed: the download response is
byte-identical to the file on disk (MD5 match); a path-traversal attempt
and a nonexistent filename both correctly 404 rather than resolving
outside the backups directory; a plain member got a 403; deleting a
backup actually removed the file from disk, confirmed directly, not
just via the redirect. `php -l` clean across all 6 touched files; final
smoke pass across all system admin pages returned 200.

**Deliberately not built**: restore-from-upload (re-importing a `.sql`
file through the admin UI) — genuinely dangerous to ship quickly (it
would mean letting an admin overwrite all live data from an arbitrary
uploaded file), deserves its own explicit go/no-go decision the same
way the commerce/paywall item does, not bundled into this pass;
scheduled/automatic backups (create-now only, no `cron.daily` hook —
a plausible small follow-on, deliberately not assumed); off-site/remote
backup storage (S3, etc. — backups live on the same server they're
backing up, a real single-point-of-failure limitation worth surfacing
to the user, not hidden).

## Permissions Audit View ✅ (SHIPPED 2026-07-17, sixth item working down Admin system parity)

**Why**: `/admin/roles`'s existing role × capability matrix only ever
answers "what can this role do" — it never shows which real members
hold a role, and it deliberately excludes every auto-provisioned scoped
role (per-board moderators, per-chapter officers) from view entirely,
by design, since Stage 2 (migration 003's own docblock explains why:
those roles need to stay out of the *site-wide* matrix). That second
gap means scoped role assignments had **no admin UI anywhere** — the
only way to see who moderates a specific forum board or officers a
specific org_spaces chapter was a direct database query.

**No new service needed — every query this required already existed**
on `PermissionEngine` (`listRoles(siteWideOnly: bool)`, `usersInRole()`),
confirming the same "the hard part already exists, this is just a
missing UI layer" shape several other features this session already
had (Trash Bin, Log Viewer). New read-only `RolesController::audit()`
action: site-wide roles resolved to real usernames via
`usersInRole()` + `AuthService::findById()`, and scoped roles isolated
by diffing the unfiltered role list against the site-wide one (an id
present in `listRoles(false)` but absent from `listRoles(true)` is, by
definition, scoped). Linked from the main Roles & Permissions page
rather than added as a new top-level System nav entry — this is a
detail view of that page's own data, not a standalone system-level
screen the way Health/Logs/Backups are.

**Verified against real, already-existing role assignments, not
fixtures created for this test**: the site-wide `admin` role correctly
showed `admin, modtest_admin` (two real accounts) while `founder`
correctly showed the "No members" empty state; the scoped-roles section
surfaced the exact three auto-provisioned roles already sitting in the
dev database from earlier session work (`Officers — Riverside Chapter
(#1)`, `Moderators — Announcements (#1)`, `Moderators — Off Topic
(#2)`), with `Officers — Riverside Chapter (#1)` correctly resolving to
its real two members (`memberuser, modtest_admin`) — the first time
this data has ever been visible anywhere in the admin UI. Confirmed a
plain member got a 403. `php -l` clean across all 4 touched files.

**Deliberately not built**: a reverse "which roles grant capability X"
view distinct from the existing matrix (the matrix already answers this
by column, just needs scanning — a dedicated per-capability drill-down
would be a small, genuinely optional follow-on, not built here);
editing role membership from this page (it's read-only by design — role
assignment already happens from each member's own `/admin/users/{id}`
page or the roles matrix itself, this page doesn't duplicate that).

## Module Dependency Viewer ✅ (SHIPPED 2026-07-17, seventh item working down Admin system parity — every item in this section except the explicitly-deferred admin scratchpad is now shipped)

**Why**: `ModuleManager` has enforced module dependencies since early in
this app's life (`assertDependenciesEnabled()`/`assertNoEnabledDependents()`
refuse to enable a module without its deps, or disable one something
else still needs) — the *enforcement* existed, but an admin could only
ever discover the dependency graph by trying to toggle something and
reading the rejection, never by looking at it up front.

**One new method, `ModuleManager::dependencyGraph()`, visualizes data
those two existing guard methods already read** — same "the hard part
already exists, this is a missing UI layer" shape as several other
items this pass. Computes both directions: what each module `requires`
(direct from `module.json`) and, the direction nothing anywhere
currently exposed, what **requires it** (computed by scanning every
other module's own `requires` list for a match). **One correctness fix
made while building this, not after**: the natural `$state[$depId] ??
true` fallback for "is this dependency enabled" would have silently
reported a `requires` entry pointing at a module that was never
actually installed (an addon declaring a dependency nobody uploaded) as
"enabled" — caught before shipping and changed to check
`isset($this->modules[$depId])` first, so a genuinely-missing
dependency shows as unavailable rather than a false "all good."

**Verified against the real, live module set — a genuine, non-trivial
dependency chain, not a fabricated example**: the graph correctly showed
`Articles` requiring `Comments` and itself being required by `RSS
Aggregator`; `Comments` requiring nothing but being required by four
other modules (Articles, Calendar, Gallery, Video); `RSS Aggregator`
requiring `Articles` with nothing depending on it — all matching this
session's own earlier work on those exact modules. Tested the
"(disabled)" flagging for real: toggled the `forms` module off through
the real endpoint, confirmed its own row showed a red "(disabled)" tag
and every module that lists it under "required by" correctly showed the
same flag next to its name — then re-enabled it. **A real mistake caught
during this exact test, worth remembering**: the toggle endpoint reads
an explicit `enabled` POST field rather than flipping current state —
a raw test `curl` that omitted it silently kept calling `setEnabled($id,
false)` on every attempt (since a missing field reads as `false`),
which looked like a stuck/broken re-enable until the actual form
template was checked and the correct `enabled=1` field added. Not an
app bug — a reminder that this endpoint's contract is "set to
this explicit value," not "toggle," when scripting against it directly
instead of through the real form. `php -l` clean across all 5 touched
files; final smoke pass across `/admin/modules`,
`/admin/modules/dependencies`, and a real public route from the
re-enabled module all 200.

**Deliberately not built**: a visual graph/diagram (the table format
already answers "requires" and "required by" per module in one glance
at club-scale module counts — a real node-and-edge diagram would be
a presentational upgrade, not a new capability); transitive dependency
resolution in the display (each module shows its *direct* requires/
required-by only, not the full transitive closure — matches what
`ModuleManager`'s own enforcement checks anyway, which is also
direct-only).

## Global Tagging ✅ (SHIPPED 2026-07-18, first item of the final Vision Parity Backlog stretch)

**Why**: a cross-module tag system for content discovery, listed since
the backlog's original establishment — the one remaining Foundational/
shared platform item never picked up.

**New `tags` module** (`tags`, `taggables` tables — the standard
normalized shape: one `tags` row per unique name, many `taggables` join
rows pointing at it, not a denormalized comma-string column anywhere).
**v1 scope is articles, wiki pages, and forum topics — deliberately the
exact same three types Bookmarks/Favorites already used for its own v1**,
both because it's a proven, sensible starting set and because
`ContentResolver` (the shared type→title/URL resolver Bookmarks and
Moderation already depend on) already covers exactly those types plus
forum posts — reusing it rather than inventing a parallel type registry
for tags specifically.

**A real scoping question surfaced by research before any code was
written, not discovered midway through**: the brief was "tags settable
by whoever can already edit the content," but a check of all three
modules' actual capability model found this doesn't mean the same thing
per type — **articles have no member-facing edit surface at all**
(`articles.manage` is admin-only, no author-ownership check anywhere in
this app), **wiki is collaborative** (`wiki.edit` holders can edit any
page, not just their own), and **forum topics have no edit path
whatsoever** (nothing about a topic's content, tags included, can be
changed after creation — matches the title and body, which are equally
uneditable). Rather than force a uniform model, each type's tagging
follows its own real capability gate as-is: articles get a tags field on
the existing admin form only, wiki gets one on both create and edit
(gated by `wiki.edit`), forum topics get one only on the new-topic form
(gated by `forum.create_topic`) since there's nowhere else for it to go.

**Tag names are stored lowercase-normalized** (`"PHP"` and `"php"`
collapse to one tag) — a deliberate anti-fragmentation choice, not left
to admin diligence to avoid near-duplicate tags splitting discovery
across two entries for the same concept. `setTags()` is a full
replace-the-set call (parse comma-separated input, create any tag that
doesn't exist yet, add/remove `taggables` rows to match) rather than an
additive API — matches the plain "edit this list" UX every wired-in form
uses. `isEnabled('tags')`-gated throughout (not a `requires` edge), same
posture Bookmarks/Ratings integration already established — disabling
the module removes the field from every form and the chips from every
show page, with zero errors anywhere else.

**Verified live across all three content types, not just one as a
stand-in for the others**: created a real article with tags
`"Testing, PHP, testing, Announcement"` and confirmed via direct query
it correctly collapsed to exactly 3 stored tags (`testing`, `php`,
`announcement`) — the duplicate and the case difference both handled
correctly, not just assumed from reading the normalize() call. Edited
that article's tags to `"php, newtag"` and confirmed the diff was
correct: `testing`/`announcement` associations removed, `php` kept,
`newtag` added — and confirmed the now-unused `testing`/`announcement`
tag *definitions* deliberately survive in the `tags` table (shared
vocabulary, not deleted just because one piece of content stopped using
them, the same "categories don't vanish when unused" precedent already
established elsewhere in this app). Created a real wiki page and a real
forum topic with tags each, confirmed chips rendered and linked
correctly on every show page, confirmed the wiki edit form pre-filled
existing tags correctly. Confirmed `/tags` (browse) and `/tags/{slug}`
(discovery) both resolve real content via `ContentResolver`, correctly
labeled by type. Disabled the module live and confirmed the create
form's tags field vanished, existing show pages kept rendering with zero
tag chips and zero errors, and `/tags` correctly 404'd — then
re-enabled it and confirmed normal operation resumed immediately.
Confirmed a plain member without `articles.manage` got a 403 on the
article create form while `/tags` itself stayed fully public (200) —
tag *browsing* has no capability gate, only tag *setting* does, per
type. All test content and tags hard-deleted afterward. `php -l` clean
across all 19 touched/new files; a full project-wide lint sweep (every
`.php` file outside `vendor/`/`storage/`) came back clean; final smoke
pass across `/`, `/articles`, `/wiki`, `/forum`, `/tags`,
`/admin/articles/create`, and `/admin` all 200.

**Deliberately not built**: extending tagging to other content types
(downloads, classifieds, gallery, calendar, etc.) — the door is open the
exact same way Bookmarks left it open ("downloads/classifieds join by
adding a ContentResolver case + allow-list entry + button each" — this
feature's own extension path is identical, just add the type to
`ContentResolver` and wire the field into that type's own
create/edit+show templates); tag management/merging admin UI (an admin
can't rename or merge two near-duplicate tags that slipped past the
lowercase-normalization, e.g. "javascript" vs "js" — a real gap, low
urgency at club-scale tag volumes); tag autocomplete/suggestion while
typing (plain comma-separated text input, no JS-driven tag picker,
matching this app's server-rendered, minimal-JS posture everywhere
else).

## Admin Action Audit Log ✅ (SHIPPED 2026-07-18, second item of the final Vision Parity Backlog stretch)

**Why**: "admin action history — who changed what, when" — a distinct
concern from the Log Viewer shipped 2026-07-17, which reads `core_logs`
(app/error logging, `Logger::log()`'s dual-write sink). This is a new
table, `audit_log`, purpose-built for admin accountability, not an
extension of that one.

**Written from exactly one place — `public/index.php`, right after
`$router->dispatch()` — rather than calls scattered across roughly 30
admin controllers.** Every admin mutation, present and future, gets
captured automatically with zero risk of a new controller action
forgetting to log itself; a brand-new admin feature shipped next month
is audited for free the moment it exists, no additional wiring
required. The condition is narrow and precise: mutating HTTP method
(POST/PUT/PATCH/DELETE), path starts with `/admin/`, the request
actually succeeded (`Response::status() < 400` — a new getter added to
`Response` for exactly this check, previously private with no
accessor), and someone is actually logged in. A rejected CSRF check or
a failed capability guard (400/403) does **not** get logged — those
aren't "an admin changed something," they're a blocked attempt, a
different concern this feature doesn't try to also be.

**`username` is captured at write time, not joined from `users` on
read** — the same "denormalize so history stays readable after the
account is gone" reasoning already used for RSS item titles and
TrashService labels, so a later-deleted or merged admin's history
doesn't turn into a wall of "Unknown."

**Deliberately no "clear log" action, unlike the Log Viewer's**: an
admin accountability trail that admins can erase defeats its own
purpose — app/error logs are operational noise worth pruning, an audit
trail of who-did-what is exactly the kind of thing that should stay
put. **Gated on `roles.manage`, not the broader `admin.access`** —
viewing what *other* admins have been doing is oversight, the same
"trusted enough to see the full picture" tier the Permissions Audit
View already established, not general admin-panel access.

**Verified live against real actions, not fabricated log rows**:
triggered a real Site Settings save, a real badge creation, and a real
module toggle (off then on) through the actual admin UI, and confirmed
all four landed in `audit_log` with the correct admin username, method,
path, and timestamp — read directly from the database, not just
trusted from the 302 responses. Confirmed a deliberately-wrong CSRF
token on a real settings POST correctly did **not** produce a row (400,
count stayed the same) — the "only log real successes" condition
holding under an actual rejected request, not just reasoned through.
Confirmed a plain GET to an admin page produced no row either. Confirmed
scope stayed correctly narrow: a real public (non-admin) mutation — a
member creating a real forum topic — did **not** appear in the admin
audit log, since it never touched an `/admin/` path; this is genuinely
a *different* log from a site-wide activity feed, and the test proved
it doesn't leak into being one. Confirmed a plain member without
`roles.manage` got a 403 viewing the log page. All test-generated rows
(and the test badge/forum topic used to produce them) removed
afterward via direct SQL, since no in-app clear action exists by
design. `php -l` clean across all 8 touched/new files; a full
project-wide lint sweep came back clean; final smoke pass across every
admin system page plus the public site all 200.

**Deliberately not built**: field-level change detail (the log records
*that* `POST /admin/articles/62/edit` happened and by whom, not that
the title changed from X to Y — a genuine richer-audit feature, real
scope beyond "who touched what page," not attempted here); IP address/
user-agent capture (no additional request metadata beyond admin
identity, method, path, and timestamp); log export/download (matches
the same cut the Log Viewer already made, for the same reason);
retention/archival policy (rows accumulate indefinitely — at club-scale
admin-action volume this is a non-issue for a long time, revisit only
if it ever isn't).

## Admin Scratchpad ✅ (SHIPPED 2026-07-18, third item of the final Vision Parity Backlog stretch)

**Why**: a shared internal notes area for site admins/moderators —
reminders, handoff notes, ongoing-issue tracking between staff.
Distinct from `member_notes` (notes *on a specific member's account*),
this is a general admin-facing space attached to nothing in particular.
Originally tagged "V2 / public release, maybe" (2026-07-16) — confirmed
as actually wanted now at the 2026-07-17 night-before planning
check-in, not deferred any further.

**New `admin_notes` table, same append-only add-or-delete-no-edit shape
`MemberNoteService` already established** — a note is a point-in-time
scratch entry ("called about the AGM room booking, confirm by Friday"),
not a document needing revision history; if it's wrong, delete and
re-add rather than silently rewrite what staff said. **Lives entirely
on the dashboard itself as one more panel — no dedicated controller, no
new page, per the roadmap's own explicit "not a separate screen"
call**: `DashboardController` gained `addNote()`/`deleteNote()`
actions alongside its existing `index()`, both redirecting straight
back to `/admin`. The panel shows the 10 most recent notes with author
name (resolved via `AuthService::findById()`, same "Unknown" fallback
pattern used everywhere in this app) and timestamp, plus an inline
add-note textarea — matching the panel-grid layout every other
dashboard panel already uses.

**Verified live, including a genuinely useful cross-feature check**:
added a real note through the actual dashboard form and confirmed it
persisted with the correct author id; confirmed it rendered on the
dashboard with the right resolved username; deleted it and confirmed
removal. Added 12 notes in a row and confirmed the database correctly
held all 12 while the dashboard panel correctly capped its display at
10 — the "recent N" limit holding under real volume, not just an
untested `LIMIT` clause. Confirmed a plain member without `admin.access`
got a 403 attempting to reach the dashboard at all (the entire
scratchpad panel is behind the same gate as the rest of `/admin`, no
separate capability). **Cross-checked against yesterday's Admin Action
Audit Log**: confirmed the `POST /admin/notes` calls used to test this
feature correctly showed up there too, with zero additional wiring
needed — direct proof the two features actually compose the way their
designs implied, not just an assumption. All test notes and the
audit-log rows they generated cleaned up afterward. `php -l` clean
across all 5 touched/new files; full project-wide lint sweep clean;
final smoke pass across the dashboard, settings, and audit log pages
all 200.

**Deliberately not built**: editing an existing note (matches
`member_notes`' own precedent — delete and re-add instead); per-author
visibility/private notes (every note is visible to every admin with
dashboard access, there's no "just for me" scratch space — the whole
point is shared handoff, not personal reminders); pinning/prioritizing
notes (plain reverse-chronological order, oldest notes simply age off
the 10-item display once newer ones push them out, matching how
"reminders" and "handoff notes" are inherently transient rather than
archival).

## Localization / i18n ✅ (SHIPPED 2026-07-18, fourth item of the final Vision Parity Backlog stretch)

**Why**: a translatable-strings framework, listed in the vision notes
as a Stage 1 core module, never built — nothing in the app was
translatable before this.

**Scope called out explicitly, not discovered as a limitation later**:
this ships the *framework* — language files, a lookup/fallback
mechanism, a site-wide language setting, and a real working
demonstration — not a translation of every string across all ~40
modules. That second thing is a multi-thousand-string mechanical pass
across nearly every template in the codebase, a fundamentally different
scope of work than building the infrastructure those translations would
plug into, and doing it in one pass would have meant either a shallow
pass that silently missed most of the app or an enormous, hard-to-verify
change touching nearly every file this session has built. The framework
is real and complete; string coverage is incremental, future work as
templates get touched — exactly the same "ship the mechanism, extend it
per-consumer later" shape Bookmarks/ContentResolver and now Tags already
established for a different kind of cross-cutting extensibility.

**Flat PHP array language files under a new `lang/` directory
(`lang/en.php`, `lang/es.php`), not a database-backed table** — no
per-string admin editing UI, no query per lookup, git-diffable, and
matches how the actual e107/SMF/ocPortal systems this app replaces
already do i18n (the concrete precedent this project has followed
throughout, not an arbitrary choice). **Site-wide only, not a per-user
preference** — the real use case (a club whose members are
predominantly Spanish-speaking) wants the whole site in one language,
public and admin alike, not each member picking their own; this
deliberately does *not* build per-user language switching. New
`Translator` class holds loaded strings as static state (not an
injected object) so the new global `t($key, $replacements)` helper —
added to `core/services/helpers.php` alongside the existing `e()`/
`route()`/`raw()` globals, same `function_exists()`-guarded pattern —
can be called directly from any template exactly like those already
are. **Two-level fallback, both proven live, not just designed**: a key
missing from the active language falls back to English (so a partially
translated language file degrades gracefully, key by key, not
file-by-file); a key missing from *both* falls back to showing the raw
key itself rather than blank text or a fatal error, so a typo'd or
not-yet-translated key is visibly obvious rather than silently wrong.
Each language file self-describes its own display name via a
`_language_name` key (`'Español'`, not `'es' => 'Spanish'` in a
separately-maintained PHP map that could drift out of sync with which
files actually exist) — the Settings page's language dropdown is built
entirely by scanning `lang/*.php`, so dropping in a new language file is
the *entire* integration step for adding a language, no code change
required.

**The login page is the one real, fully-translated demonstration
slice** — deliberately chosen as small, self-contained, and genuinely
live/user-facing rather than a throwaway demo page: heading, both field
labels, and the submit button, all four strings present in both
`lang/en.php` and a real `lang/es.php` Spanish translation (not a
placeholder file with fake strings — real Spanish, "Iniciar sesión,"
"Usuario o correo electrónico," "Contraseña").

**Verified live end-to-end, including edge cases beyond the happy
path**: confirmed the login page renders in English by default; flipped
`site_language` to `es` through the real admin Settings form and
confirmed the exact same page immediately rendered in real Spanish, no
restart or cache clear needed; flipped back to English and confirmed it
reverted immediately. Confirmed the rest of the site (`/`, `/forum`,
`/articles`) kept rendering normally throughout — untranslated pages
don't break, they just stay in English regardless of the active
language, exactly the intended degrade. Directly tested (via a small
isolated PHP script, not just reading the code) the two fallback layers
independently: a nonexistent key correctly returned the raw key string
rather than fataling or returning empty; a key present in English but
genuinely absent from a real second test language file correctly fell
back to the English value while a key that *was* translated in that
file correctly used the translation — proving the fallback is per-key,
not per-file. Also verified `{placeholder}` replacement substitutes
correctly. Confirmed a bogus, nonexistent language code submitted
through the real settings form was rejected server-side (validated
against the actual scanned `lang/*.php` files) and fell back to `en`
rather than saving a value that would silently break every subsequent
page load. `php -l` clean across all 8 touched/new files; full
project-wide lint sweep clean; final smoke pass across the public site
and admin settings all correct.

**Deliberately not built**: translating the rest of the app (every
other module's templates stay in hardcoded English — a large, ongoing,
incremental effort, not attempted here, see the scope note above);
per-user language preference (site-wide setting only); RTL layout
support (no right-to-left language shipped or tested — the framework
itself doesn't preclude adding one, but nothing here validates that
CSS/layout would actually work correctly for e.g. Arabic or Hebrew);
pluralization rules (`{placeholder}` substitution only, no "1 item / 2
items"-style plural-form handling — a real i18n feature, genuinely more
complex, not attempted in this pass); an in-admin translation-editing
UI (translators edit `lang/*.php` files directly via file access, not
through a web form — matches the "no per-string DB editing UI" scope
decision above).

## Cache Manager ✅ (SHIPPED 2026-07-18, fifth and final item of the initial next-session build list)

**Why**: a real page/query caching layer, long held under an explicit
"wait for real traffic data before building this" rule — overridden by
the user directly at the 2026-07-17 night-before planning check-in
("build it anyway tomorrow"), so this shipped speculatively rather than
being triggered by an actual performance problem. The off-by-default
posture below is exactly what makes that a safe thing to do: it exists
and works, but does nothing until a real install's admin deliberately
turns it on.

**Full-page HTML caching for guest (logged-out) GET requests only —
not query-result or object caching**, and file-based, not Redis/
Memcached, matching this app's "don't assume an external service exists
on unknown shared hosting" posture already established for
`ClamAvScanner`/`UpdateChecker`/the Cash App payment decision. Checked
in `public/index.php` **before `ModuleManager::boot()` runs** — a cache
hit skips essentially the entire request pipeline (module loading,
routing, controller logic, every DB content query, template rendering),
not just a faster version of the same work. Guest detection is a cheap
`isset($_SESSION['user_id'])` check rather than the full `Auth::check()`
(which re-verifies against the database on every call, per this app's
own established "never trust a cached auth state" discipline) — a
worthwhile DB round-trip to skip specifically on the cache-hit fast
path, since paying for a DB query just to decide whether to skip DB
queries would partly defeat the point.

**The one safety property this whole feature hinges on**: a cached page
is later served to a *different* visitor than whoever's request
generated it, so `PageCache::put()` unconditionally refuses to store
any HTML containing a CSRF token (`name="_csrf"`) — caching one and
handing it to every subsequent guest would either break their form
submissions with a stale token or, worse, hand out a token bound to one
specific session. This is a **runtime content check on the actual
response body**, not a route allow-list some future page could
accidentally bypass by not being on a maintained list — it self-defends
against any future page that happens to embed a form.

**Off by default, server-level config, not a per-request or DB
setting** — new `PAGE_CACHE_ENABLED`/`PAGE_CACHE_TTL_SECONDS` in
`.env`/`.env.example` (default `false`/`300`), deliberately not a
`core_settings` row: reading `.env` is already required for every
request regardless, whereas a DB-backed toggle would mean connecting to
MySQL just to decide whether to skip connecting to MySQL on a cache
hit — self-defeating for the one code path where skipping the DB
matters most. A logged-in session (any role, including admin) **never**
reads from or writes to the cache, full stop — every response it
sees is always freshly computed, regardless of what's cached for
guests. New `/admin/system/cache` page (stats: file count, total size,
current enabled/TTL state read from `.env`; a "Clear cache" action) —
gated `admin.access`, matching System Health's tier since this is
operational infra, not sensitive oversight.

**Verified live with the cache actually turned on, not just read from
code** — enabled it in the real dev `.env`, then: confirmed a first
guest request to `/articles` produced a cache MISS and wrote a real
file to `storage/cache/pages/`, and an immediate second request produced
a genuine `X-Stratum-Cache: HIT` served from that file. **Confirmed the
critical safety property directly**: requested `/login` (which embeds a
real CSRF token) twice — never produced a HIT, and no file was ever
written for it, the runtime content check holding under a real request,
not just reasoned through. **Confirmed cache isolation between guest and
authenticated traffic**: primed a real guest-cached copy of `/articles`,
then hit the exact same URL with a freshly-verified, genuinely
authenticated admin session (re-logged-in and confirmed active via a
real admin-only page first, after an earlier attempt was accidentally
run against an already-expired session and would have given a false
pass) — the authenticated request correctly never received the cache
header, proving a logged-in visitor can never be served a guest's cached
page. Tested TTL expiration for real with a shortened 2-second TTL: HIT,
HIT, then a genuine MISS after sleeping past expiry. Confirmed
non-HTML responses (`/sitemap.xml`, `application/xml` content-type)
never get cached even though the route is guest-GET-eligible — the
content-type guard holding on a real request. Confirmed the admin
`/admin/system/cache` page showed accurate live stats (3 real cached
files, correct TTL/status) and that "Clear cache" genuinely emptied the
directory (0 files after, verified on disk, not just via the redirect).
Confirmed a plain member got a 403 on the cache admin page. Restored
`.env` to `PAGE_CACHE_ENABLED=false` afterward — the safe, inert
default — and re-ran the full smoke pass to confirm the site behaves
identically with caching off. `php -l` clean across all 9 touched/new
files; full project-wide lint sweep clean.

**Deliberately not built**: query-result or object/fragment caching
(page-level only); automatic cache invalidation on content changes (a
cached guest page simply expires after its TTL rather than being
proactively purged the moment, say, an article is edited — bounded
staleness, not immediate consistency, the standard and accepted
tradeoff of time-based page caching); per-route TTL overrides (one
global TTL for every cacheable page); Presence tracking on a cache hit
(a cache hit exits before presence-tracking code ever runs, so a
guest's repeat cached page views don't re-touch their "last seen"
timestamp — guest presence counts become slightly less precise with
caching on, a real, explicitly-acknowledged tradeoff, not a silent
bug).

## Donation Goals ✅ (SHIPPED 2026-07-18, first Stage 7 deliverable)

**Why**: Stage 7's spec calls for "a visible target/progress framing on
top of Stage 6b's `donations` campaigns — not a new payment mechanism."

**A real discovery made before writing any code, not assumed**: this
turned out to already be fully built. `donation_campaigns.goal_amount`
has been a required column since the base `donations` module shipped,
and `DonationService::raisedAmount()` (live-computed `SUM` over
confirmed contributions, never cached) already backs a working progress
bar and percentage on both the public campaign listing and each
campaign's own page. Confirmed live rather than just read from code:
loaded an existing campaign's public page and watched the progress bar
width move from 0% to 60% to a goal-capped 100% as real contributions
were recorded through the actual admin UI.

**The one genuine gap, and the only thing actually built here**: nothing
fired an event when a campaign crossed its goal. New
`DonationService::hasReachedGoal()` plus a controller-side before/after
comparison (raised-before-this-contribution vs. raised-after) wired into
both places a contribution can be confirmed (`confirmContribution()` —
the pending-then-confirm flow, and `recordContribution()` — direct cash/
check entry) — comparing within one request is what keeps the
notification firing exactly once, at the actual crossing, not on every
subsequent contribution once a campaign is already past goal. Notifies
every admin holding `donations.manage` (a new small
`adminsWithCapability()` helper — capability → site-wide role grants →
`usersInRole()`, deduplicated), not just one hardcoded recipient, since
"the club" running the campaign is usually more than one person.

**Verified live with a real crossing, not a synthetic flag flip**:
created a real $100-goal test campaign, confirmed a $60 contribution
correctly produced zero goal-reached notifications, then a further $50
contribution (crossing 100%) correctly produced exactly two — one per
each of the two real admin accounts in the dev database, confirmed
individually by user id, not a duplicate to one account. A further $20
contribution after already being past goal correctly produced zero
additional notifications, proving the crossing-only logic holds under a
real repeat request. All test campaign/contribution/notification data
removed afterward. `php -l` clean across both touched files.

**Deliberately not built**: a persisted "goal reached" flag/timestamp on
the campaign itself (the crossing is detected live via the before/after
comparison each time a contribution is confirmed, not stored — matches
this app's "compute, don't cache" discipline used everywhere else);
auto-deactivating a campaign once its goal is reached (a plausible
follow-on, not assumed — some clubs may want to keep collecting past a
goal); an embeddable dashboard/sidebar block showing live progress
(`donations` module's `blocks: []` stays empty — the progress bar only
lives on the campaign's own pages today).

## Premium Memberships ✅ (SHIPPED 2026-07-18, second Stage 7 deliverable)

**Why**: "a paid membership tier gated the same way any other capability
is, layered onto Stage 6a's `dues` plans rather than a separate
system." Unlike Donation Goals, this really was virgin territory —
confirmed by research before writing any code: `dues` was pure payment-
history tracking, with zero access-control tie-in anywhere, no
`isCurrent()`-style method, and no expiry concept at all.

**Two small schema additions, not a new module** — `dues_plans` gained
`is_premium`/`grants_capability_key` (a plain string looked up via
`PermissionEngine::findCapabilityByKey()`, not a foreign key to
`capabilities.id`, since capabilities are addressed by key everywhere
else in this app); `dues_payments` gained `expires_at`, computed at
confirmation time from the plan's existing `period` field (`monthly` →
+30 days, `annual` → +365 days, anything else including `one_time` →
never expires). `DuesService::confirmPayment()` — the exact same method
every dues payment already goes through — now also grants a per-plan
scoped role via `PermissionEngine`, and a new
`revokeExpiredPremiumMemberships()` runs from `cron.daily` (dues'
`Module.php` gained its first hook listener) to take it away once
`expires_at` passes, using the same "compare against MySQL's own NOW(),
never a PHP-computed date" rule this app adopted after a real timezone
bug during scheduled article publishing.

**A real bug caught by live testing, not shipped and found later**:
the auto-provisioned per-plan role (`"Premium — Plan Name (#id)"`) is
correctly *scoped* (`scope_type='dues_plan'`) for the same bookkeeping
reason org_spaces' officer roles and forum's per-board moderator roles
already are — keeping it out of the main `/admin/roles` matrix. The
first version of this feature also made the *capability grant itself*
scoped to that same `dues_plan`/plan-id pair, which is wrong: most
capabilities in this app (`forms.manage`, `articles.manage`, etc.) are
checked via a plain scope-less `Auth::can($key)`, and
`PermissionEngine::userCan()`'s own documented behavior is that a
scoped grant only ever satisfies a check passing that *exact* scope —
a scoped grant here would silently never unlock anything. Caught this
directly: granted a real premium plan's capability to a real test
member, confirmed the role and grant existed correctly in the database,
then found the member still got a 403 on the capability's actual admin
page. Root-caused it to the scope mismatch, fixed `premiumRoleForPlan()`
to keep the *role* scoped (for matrix bookkeeping) while making the
*grant* site-wide (`grant($roleId, $capabilityId)` with no scope args),
and reverified the exact same test now succeeds. This distinction — a
role's own `scope_type`/`scope_id` columns vs. a `role_capabilities`
row's independent scope columns are two different things — is now
called out explicitly in the code, not just fixed silently.

**Verified live end-to-end, the full real lifecycle, not just the
grant step**: created a real monthly premium plan granting `forms.manage`
(deliberately picked because `member` doesn't hold it by default,
unlike e.g. `links.create` which does — confirmed via query first, so
the test would actually prove something). A real member confirmed
locked out of `/admin/forms` (403) before paying, recorded intent, an
admin confirmed the payment — `expires_at` landed exactly 30 days out,
matching the plan's `monthly` period — and the member's very next
request to `/admin/forms` succeeded (200), plus the public plan page
correctly showed "You're a premium member until {date}." Manually
back-dated the payment's `expires_at` to the past and confirmed access
was **not** yet revoked on a live page view (matches the deliberate
design: revocation is cron-driven, not checked live per-request, same
"cron.daily runs once a day" limitation this app already accepted for
scheduled publishing) — then ran the real `bin/cron.php` and confirmed
both that it completed with zero listener errors and that the member's
very next request correctly dropped back to 403. Finally tested renewal:
recorded and confirmed a second payment for the same lapsed plan and
confirmed access was automatically restored, no manual role fix needed.
All test plan/payments/roles/notifications removed afterward. `php -l`
clean across all 8 touched/new files; final smoke pass across dues and
donations pages all 200.

**Deliberately not built**: multiple capabilities per premium plan (one
`grants_capability_key` per plan — a club wanting a tier that unlocks
several things needs several plans, or picking one already-bundled
capability); a grace period before revocation (access drops the moment
`expires_at` passes and cron next runs — no "still works for 3 days
after expiry" buffer); email/external reminders before a membership
lapses (the existing `dues.confirmed` notification fires on payment,
nothing proactively warns before expiry); prorating or partial-period
handling (a renewal always adds a fresh full period from the
confirmation moment, not from the old expiry date — paying early
doesn't stack, it resets the clock).

## Banner Manager & Ad Tracker ✅ (SHIPPED 2026-07-18, third Stage 7 deliverable)

**Why**: Stage 7's spec calls for a banner manager (zones, scheduling,
rotation, stats) and an ad tracker (impressions/clicks/CTR/campaigns/
advertisers) — the biggest remaining Stage 7 deliverable, and the one
most clubs' existing e107/SMF sites actually monetize through today
(sidebar sponsor banners).

**Build**: new `ads` module. Three tables: `ad_advertisers` (name,
contact), `ad_campaigns` (belongs to an advertiser, `starts_at`/
`ends_at`/`is_active` — the schedule window), `ad_banners` (belongs to
a campaign, a `zone` string, image/link URLs, `is_active`, plus simple
`impression_count`/`click_count` counter columns — deliberately not a
time-series impressions/clicks log table, matching the
`download_count`/`view_count`/`click_count` precedent already
established by downloads/video/links; CTR is computed live from the
two counters, never stored, same "compute don't cache" discipline as
`DonationService::raisedAmount()`).

Zone rendering reuses the **existing generic `BlockRegistry`
block-placement system** rather than a hardcoded call in
`layout.php` — confirmed via the ticker/search/presence precedent
(and an explicit comment in search's own seed migration: "No admin UI
for block placements yet (Stage 8)") that this is the established
pattern for injecting non-content chrome into a named region. One
block type, `ads.banner`, is registered; migration 001 seeds one
placement per existing region (`header`/`sidebar_left`/
`sidebar_right`/`footer`) with `config_json` carrying which `zone` an
`AdBlock` should query — the zone vocabulary happens to match the
region-key vocabulary, but they're independently plumbed (the block
just reads `$config['zone']`). `AdService::activeBannerForZone()`
picks one random active banner (`ORDER BY RAND() LIMIT 1`) from an
active campaign whose schedule window currently includes MySQL's own
`NOW()` (never a PHP-computed date — the timezone-safety rule this
app adopted after the scheduled-publishing bug), incrementing
`impression_count` as a side effect of being selected for render.
Click-through is a public `/ads/banners/{id}/click` redirect route
that increments `click_count` then redirects to the banner's
`link_url` — the exact same "count then redirect" shape Link
Directory's `visit()` action already established. New admin-only
`ads.manage` capability (ad content is entirely admin/staff-curated,
unlike member-generated content types, so no separate "create"
capability was needed); one combined admin page manages advertisers,
campaigns, and banners, and shows impressions/clicks/CTR per banner.

**Verification**: real end-to-end pass against the live dev server —
created a real advertiser, a campaign with no end date, and a banner
in `sidebar_left`; confirmed it rendered on the public homepage inside
a `strat-ad-banner-sidebar_left` wrapper and that `impression_count`
incremented on that render. Hit the click-through route and confirmed
a 302 redirect to the banner's real `link_url` plus `click_count`
incrementing. Confirmed the admin stats table showed the correct CTR
(1 impression / 1 click = 100.00%). Tested scheduling by backdating
the campaign's `ends_at` into the past — the banner correctly
disappeared from the homepage — then clearing it back to null and
confirming the banner reappeared. Tested rotation by adding a second
banner to the same zone and hitting the homepage 10 times: a roughly
even 5/5 split between the two banners, confirming `ORDER BY RAND()`
rotation. Confirmed `ads.manage` was auto-seeded into `strat_capabilities`
(via `ModuleManager::syncCapabilities()` on first boot, not at
migration time) and that the "Advertising" admin nav link appears.
All test advertiser/campaign/banners removed afterward (FK cascade
from the advertiser row cleaned campaigns and banners in one delete).
`php -l` clean across all 9 new files plus a full project-wide sweep.

**Deliberately not built**: a full admin block-placement UI (still
Stage 8 scope — zones are migration-seeded, not admin-configurable,
matching the exact limitation ticker/search/presence already live
with); a time-series impressions/clicks log (counters only, CTR
computed live); per-banner weighting or frequency capping (rotation
is uniform random among active banners in a zone); geo/device
targeting; billing or invoicing for advertisers (this module tracks
performance, not money — matches the "no payment processing"
boundary every other revenue feature in this app has kept, including
premium memberships' external Cash App link).

## Affiliate Links ✅ (SHIPPED 2026-07-18, fourth Stage 7 deliverable)

**Why**: Stage 7's spec calls for affiliate links — a club's real-world
sponsor/partner relationships ("buy your gear from our partner, we get
a cut"), distinct from both Link Directory (member-submitted, general
purpose) and the banner/ad system (visual creative in a page zone).
Deliberately kept as its own small module rather than folded into
either — affiliate links are admin-curated text links, not member
content and not visual ad creative, so conflating them with either
existing system would have blurred a distinction that's real.

**Build**: new `affiliates` module, closely mirroring Link Directory's
own shape but simpler: one `affiliate_links` table (label, url,
description, `weight` for manual ordering, `is_active`, `click_count`,
soft-delete via `deleted_at` matching the app's default discipline).
No categories (unlike Link Directory) — affiliate lists are typically
short and curated, not browsable by topic. Admin-only creation (new
`affiliates.manage` capability, no public "submit" capability) since
these represent real business relationships, not member submissions.
Public `/affiliates` page ("Our Partners") lists active links; click
tracking is the exact same "count then redirect" shape as Link
Directory's `visit()` action, at `/affiliates/{id}/visit`.

**Verification**: live end-to-end against the dev server — confirmed
`affiliates.manage` auto-seeded on boot and the "Affiliate Links" admin
nav link appears; created a real link, confirmed it rendered on the
public `/affiliates` page with its description; clicked through and
confirmed both the redirect to the real destination URL and
`click_count` incrementing; deactivated the link and confirmed it
disappeared from the public page; reactivated and soft-deleted it,
confirming the admin delete action works. All test data removed
afterward. `php -l` clean across all 8 new files plus a full
project-wide sweep.

**Deliberately not built**: categories/grouping (Link Directory already
covers that shape if a club needs it); an edit action (create +
toggle-active + delete only, matching Link Directory's own
create/delete-only precedent — an admin who mistypes a URL deletes and
re-adds); commission/revenue amount tracking (this module tracks
clicks, not money, matching the same "no payment processing" boundary
every other Stage 7 revenue feature has kept).

## Sponsor Blocks ✅ (SHIPPED 2026-07-18, fifth and final Stage 7 deliverable)

**Why**: Stage 7's spec calls for sponsor blocks — closes out Stage 7.
Deliberately designed as the *opposite* shape from `ads.banner` rather
than reusing it: a club's season/event sponsors typically want
always-on logo acknowledgment (all of them, together, all the time),
not a rotating one-at-a-time ad slot with campaigns/scheduling/CTR.
Building a third near-identical "advertiser/campaign/banner" stack
would have been the over-engineered path; the actual gap was a
simpler, different widget.

**Build**: new `sponsors` module, intentionally flat — a single
`sponsors` table (name, logo_url, link_url, `weight` for manual
ordering, `is_active`, `click_count`, soft-delete), no advertiser/
campaign hierarchy, no scheduling window, no impression tracking (only
clicks — "was this sponsor's logo ever clicked," not "how many times
was it in view," since every active sponsor is always in view
together, unlike a rotated banner). Reuses the same `BlockRegistry`
zone idiom the `ads` module just established, but seeds **one**
placement only (footer) rather than one per region — sponsor
acknowledgment doesn't need per-zone admin choice the way a
paid-advertiser banner does; migration 001 seeds it directly, same
"no admin placement UI yet (Stage 8)" precedent. `SponsorBlock::render()`
renders every active sponsor's logo as a linked `<img>` in one strip,
not `activeBannerForZone()`'s single-random-pick — the core behavioral
difference from ads.banner, confirmed live (see below). New
`sponsors.manage` capability, admin-only. Click tracking is the same
"count then redirect" `/sponsors/{id}/click` shape as
links/affiliates/ads.

**Verification**: live end-to-end — confirmed `sponsors.manage`
auto-seeded and the "Sponsor Blocks" admin nav link appears; created
two real sponsors and confirmed **both** rendered together in the
footer strip on the public homepage (not one random pick — the
deliberate contrast with ads.banner); clicked through sponsor one and
confirmed the redirect plus `click_count` incrementing; deactivated
sponsor two and confirmed only sponsor one remained on the homepage,
sponsor two correctly dropped. All test data removed afterward. `php
-l` clean across all 9 new files plus a full project-wide sweep.

**Deliberately not built**: scheduling/campaigns (sponsors are
always-on until deactivated, no start/end window — a club running a
time-boxed event sponsor would just deactivate manually when the event
ends); impression tracking or CTR (clicks only — the "always visible
together" shape makes an impression count meaningless the way it isn't
for a rotated single banner); multiple placement zones (footer only,
matching the "one obvious place for sponsor logos" real-world pattern
clubs actually use, unlike ads which genuinely benefit from
per-zone admin choice).

**Stage 7 — Advertising & Revenue is now fully shipped**: banner
manager & ad tracker, affiliate links, sponsor blocks, donation goals,
and premium memberships are all done — see each entry above. Matches
the precedent Stage 6 set (multi-session stage, closed out with every
deliverable individually verified live before the stage header itself
was marked ✅).

## Stage 8, First Slice: Front Page & Block Library Core ✅ (SHIPPED 2026-07-18)

**Why**: the design pass earlier the same day (see "Block library & default
front page design" under Stage 8 below) settled the architecture; this is
the first real implementation slice — replacing the hardcoded homepage
stub with the block-composed front page, plus enough of the block library
to make it genuinely useful out of the box, rather than shipping empty
infrastructure.

**Build**: five new `page_scope = 'front_page_only'` regions
(`front_hero_main`, `front_hero_side`, `front_col_1/2/3`), seeded via core
migration 012 alongside a real default placement set so a fresh install's
homepage isn't blank. `public/index.php`'s `/` route now renders these
five regions into a hero+side row and a 3-column area instead of the old
`<h1>Welcome to Stratum</h1>` stub. Five real blocks shipped:

- **`articles.latest_content`** (the flagship parameterized block) —
  one class, two placements (`display: 'hero_slider'` for the hero,
  `display: 'compact_list'` for the side box), backed by a new
  `ArticleService::listPublishedByCategory()` method. Hero slider is a
  simple vanilla-JS rotator (prev/next + dots + auto-advance every 7s),
  same "no framework" pattern `TickerBlock`'s rotator already established.
  Compact list is a static, scrollable (`max-height` + `overflow-y:auto`)
  list — matches the "static list with a scrollbar if it overflows"
  decision from the design pass exactly.
- **`activity.feed`** and **`tags.cloud`** — zero new queries, both just
  wrap already-existing service methods (`ActivityService::recent()`,
  `TagService::popularTags()`) in a `Block`.
- **`custom.html`** and **`custom.text`** — the two escape hatches, in a
  new minimal `custom_blocks` module with no migration/service/table at
  all — content lives directly in the placement's `config_json`
  (`{"html": "..."}` / `{"text": "..."}`). `custom.text` renders through
  the existing `BBCodeParser` (escape-then-allow-list), not a new Markdown
  dependency — matches every other rich-text field in this app (articles,
  forum, wiki) rather than introducing a second markup dialect. `custom.html`
  is genuinely raw (`raw()` helper, no parsing) — safe in the same sense
  addon/theme uploads already are: whoever can set a placement's
  `config_json` is already at admin/DB trust level, since there's no admin
  placement UI yet (that gap is unchanged, still Stage 8-proper, not
  this slice).
- **`presence.whosonline`** — already existed (built earlier this
  project) but had never actually been placed anywhere; this slice gives
  it its first real placement (`front_col_2`).
- **New `featured_image_url` column on `articles`** (nullable, migration
  003) — the hero slider needed *something* to show as a background image
  and there was no image column on articles at all before this. Small,
  contained addition (one nullable column, one admin-form field), not a
  new subsystem — directly serves the block that needed it.

**Default seeded homepage**: `front_hero_main` → Latest Content (hero,
no category filter, limit 5), `front_hero_side` → Latest Content (compact,
limit 5), `front_col_1` → Activity Feed, `front_col_2` → Who's Online,
`front_col_3` → Tag Cloud. A club can immediately rearrange or swap these
once the admin placement UI exists (still unbuilt), but the out-of-the-box
result is already a real, populated front page, not an empty shell.

**Scoping policy applied**: all five new regions are `front_page_only`,
per the "general blocks don't clutter forum/article pages" decision from
the design pass — verified live (see below) that none of this leaks onto
`/articles` or any other route.

**Verification**: live end-to-end against the dev server. Hero slider
rendered 5 real published articles with working prev/next/dot navigation
markup; compact list rendered the same 5 in a scrollable static list.
Tagged a real article and confirmed the Tag Cloud block went from empty
(zero tags existed) to rendering real tag links sized by frequency. Set a
`featured_image_url` on a top-5 article and confirmed the hero slide's
`background-image` picked it up correctly (first attempt used an article
outside the top-5 window, which correctly did *not* show — confirmed the
recency ordering was right, not broken, before retrying with an in-window
article). Confirmed `presence.whosonline` and `activity.feed` both
rendered real, current data (online count, real join/publish/announcement
events with correct actor names). Used `preview_inspect` (not just a
screenshot) to confirm the hero/side grid is a genuine 2-column
`2fr`/`1fr` CSS grid and the lower area is a genuine 3-column grid, both
with real block content inside. Confirmed zero leakage onto `/articles` —
the only matches there were the shared `<style>` block's CSS rule
definitions (present on every page by necessity), not actual rendered
block markup. All test data (temporary tags, temporary featured-image
edits) cleaned up afterward. Full project-wide `php -l` sweep clean
across all 16 new/touched files.

**Deliberately not built in this slice** (remaining Stage 8 work, not
regressions): the other ~14 blocks from the confirmed v1 list (Recent
Forum Posts, Members Online beyond the reused Who's Online block, Newest
Members, Latest Comments, Upcoming Events, Featured Club, Downloads
List, Gallery Highlights, Recent Videos, Site Statistics, Welcome/Join
CTA, admin-only Quick Links); the admin drag-drop placement UI itself
(placements are still migration-seeded only — the same limitation
ads/sponsors/ticker/search have all had all along); popularity/
most-commented sort mode on the Latest Content block (would need a new
batch join against `comments`, deferred until a real caller needs it).

## Stage 8, Second Slice: Remaining Block Library ✅ (SHIPPED 2026-07-18)

**Why**: closes out the confirmed v1 block-library list from the design
pass — the first slice shipped 5 blocks (Latest Content, Activity Feed,
Tag Cloud, Who's Online, HTML/Text escape hatches); this batch adds the
remaining 11, giving every column on the default front page real,
varied, verified content instead of just three blocks stretched thin.

**Build**: 11 new blocks, each following the same shape established in
slice one — a new cross-cutting service method (where one didn't already
exist) + a `Block` class in the owning module's `services/` directory +
a `registerBlocks()` wire-up in that module's `Module.php`:

- **`forum.recent_topics`** — new `ForumService::listRecentTopics()`
  (cross-board; `listTopicsForBoard()` was scoped to one board).
- **`calendar.upcoming_events`** — zero new query, wraps the already-
  existing `CalendarService::listUpcomingEvents()`.
- **`users.newest_members`** — new `AuthService::listNewest()`
  (`listUsers()` sorts by username, not signup order).
- **`comments.recent`** — new `CommentService::listRecent()` (cross-type;
  the only two existing methods were scoped to one piece of content),
  resolved through the existing `ContentResolver`, over-fetching 3x the
  display limit and dropping unresolvable rows (gallery/video/downloads
  aren't wired into `ContentResolver` yet) rather than showing a
  mysteriously short list.
- **`downloads.recent`**, **`video.recent`**, **`gallery.highlights`** —
  same shape: a new cross-category/cross-album method added to each
  service (all three existing list methods were scoped to one category/
  album), then a thin `Block` wrapper.
- **`org_spaces.featured_club`** — no "featured" flag exists on
  `org_spaces_orgs` (confirmed, not assumed); picks one random active org
  per render rather than adding schema for a single block.
- **`site_stats.summary`** — new minimal `site_stats` module, **real
  data only** (member count, new members this week, comments this week —
  all via `COUNT(*) ... WHERE created_at >= NOW() - INTERVAL 7 DAY`,
  MySQL's own clock, never PHP's). No page-view/visitor numbers, per the
  explicit decision already recorded in the first-slice design notes.
- **`custom.welcome_cta`** — small dedicated block (headline/text/button),
  added to `custom_blocks`; decided at build time it needed its own class
  rather than reusing `custom.text`, since a CTA button with its own href
  is a different shape than a text blob.
- **`custom.quick_links`** — admin-only shortcut panel, also in
  `custom_blocks`. First block in this app that needs to know who's
  viewing: takes the live, request-scoped `Auth` instance (captured fresh
  per render via the `Module.php` factory closure, not cached at module
  boot) and gates on `$auth->check() && $auth->can('admin.access')` —
  the exact same check the nav bar's own "Admin" link already uses, no
  new visibility mechanism invented.

**Two real constructor-signature bugs caught before they shipped, not
after**: `DownloadService`, `GalleryService`, `VideoService`, and
`OrgSpaceService` each take a second constructor argument beyond
`Database` (`storageDir` for the first three, `PermissionEngine` for the
fourth) that isn't obvious from a method-only read of each service —
`php -l` can't catch a wrong argument count, and since these blocks
weren't placed anywhere yet at boot time, the wrong call wouldn't have
thrown until the first real render. Caught by explicitly checking each
service's `__construct()` signature before finalizing the corresponding
`Module.php`, not discovered via a runtime error.

**Second front-page seed migration** (013): stacks the 11 new blocks
into the same three columns core migration 012 introduced, alongside the
three already there — weight-ordered, multiple placements per region,
same shape `sidebar_left`/`sidebar_right` already support. Final column
layout: col 1 = Welcome CTA, Activity Feed, Recent Forum Posts, Latest
Comments, Recent Videos; col 2 = Who's Online, Upcoming Events,
Downloads, Featured Club, Quick Links; col 3 = Tag Cloud, Newest Members,
Gallery Highlights, Site Statistics.

**Verification**: live end-to-end. Confirmed all 11 new block markers
rendered on the real homepage with genuine existing data (a real forum
topic, a real resolved comment via `ContentResolver`, a real org
"Riverside Chapter," a real gallery photo, real member counts), not
empty placeholders. Confirmed `custom.quick_links` correctly appeared for
a logged-in admin and was completely absent (0 matches) for a guest
request — the auth-gating actually works, not just compiles. Confirmed
Site Statistics showed real, plausible counts (7 members, 7 new this
week) with no fabricated numbers. Re-confirmed zero leakage onto
`/articles` (0 matches for every new block's marker class) — the
`front_page_only` scoping policy holds for the full library, not just
the first slice. Checked dev-server logs directly for PHP
errors/warnings across every request in this pass — none. Full
project-wide `php -l` sweep clean across all 27 new/touched files.

**Deliberately not built in this batch**: the admin drag-drop placement
UI (still migration-seeded only — placements can't be rearranged short
of editing the database, same limitation every block in this app has had
since `BlockRegistry` was first built); popularity/most-commented sort
modes anywhere (every "recent" method here is chronological only, same
deferral as slice one's Latest Content block); a real "featured" flag
for org_spaces (random pick is the deliberate v1 answer). The block
library itself is now feature-complete against the confirmed v1 list —
what's left in Stage 8 is the placement UI, the visual theme editor,
child themes, dark mode, and the menu builder.

## Stage 8, Third Slice: Admin Block Placement Manager ✅ (SHIPPED 2026-07-18)

**Why**: closes the one real gap every block placed so far (ads,
sponsors, ticker, search, presence, the entire front-page block library
across both prior slices) has shared since `BlockRegistry` was first
built: every placement was migration-seeded only, with no way for an
admin to actually add, remove, reorder, or relocate a block without
editing the database directly. This is that admin UI.

**Build**: new `BlockPlacementService` (`core/services/`, alongside
`AuditLogService`/`AdminNoteService` — genuinely core, not owned by any
toggleable module) with `listRegions()`, `listGroupedByRegion()`,
`create()`, `setEnabled()`, `delete()`, and `moveUp()`/`moveDown()`.
`BlockRegistry` gained one new method, `registeredTypes()`, so the admin
UI can offer every block type any enabled module has registered — 22
distinct types across 10 regions at time of shipping. New admin-only
`blocks.manage` capability (not folded into `admin.access` — block
placement affects every visitor's page, same "new narrow capability for
meaningfully more consequential blast radius" reasoning already applied
to `system.update`/`system.backup`). New `/admin/blocks` page, listed
under System nav.

**Reordering is move-up/move-down against the existing `weight` column,
not literal drag-and-drop JS** — a deliberate scope call, not a
shortcut: "drag-drop" in the original vision notes was about giving
admins *control* over ordering, and this app already has that exact
mechanism (`weight`) driving order everywhere else (forum boards, link
categories, affiliate links). Real HTML5 drag-and-drop would mean
dragstart/dragover/drop event handling plus an AJAX persistence layer
for what's functionally the same swap this ships with a plain POST —
more JS complexity for a cosmetic difference in *how* the reorder is
triggered, not the capability itself, and out of step with this app's
"no framework, minimal vanilla JS" posture that's held throughout every
other feature this session.

**No inline edit — delete and recreate instead**, matching the exact
precedent Link Directory and Affiliate Links already set for this app's
admin CRUD screens. (A first draft did attempt inline per-row editing
with a `<form>` wrapping multiple `<td>` cells in one `<tr>` — caught
during review as invalid HTML, since `<form>` isn't a permitted direct
child of `<tr>`; rather than reach for the `form="id"` HTML5 attribute
workaround, simplifying to the same delete+recreate shape already used
elsewhere in this app was the better fix, both because it's less code
and because it doesn't introduce a form-association pattern used nowhere
else in the codebase.)

**Verification**: live end-to-end against the dev server. Confirmed
`blocks.manage` seeded and a guest hitting `/admin/blocks` gets
redirected to `/login`, not a 403 leak of admin structure. Confirmed
invalid JSON in the config field is rejected with zero rows written
(tested with a deliberately malformed string) and a visible error
banner. Created two real placements in `sidebar_right`, confirmed
`config_json` round-tripped correctly (`{"limit": 3}` stored and
re-readable, an empty string correctly stored as `NULL`). Called
move-up on the lower-weight one and confirmed the two placements'
weights actually swapped in the database, not just in the response.
Toggled a placement disabled and confirmed `is_enabled` flipped.
Deleted both test placements and confirmed they were gone. Confirmed
zero server errors across the whole test pass and the public homepage
still rendered fine (200) throughout. Full project-wide `php -l` sweep
clean across all 8 new/touched files.

**Deliberately not built**: literal drag-and-drop reordering (see
above — move-up/move-down is the deliberate answer, not a placeholder
for a future upgrade); inline editing (delete + recreate, matching
established precedent); a live preview of where a block will render
before saving (the admin already knows the site's layout from using it;
this isn't a visual theme editor, which is still separately unbuilt).

## Stage 8, Fourth Slice: Site Header/Masthead Banner ✅ (SHIPPED 2026-07-18)

**Why**: closes the user's one specific, explicit design request for the
entire project (confirmed 2026-07-17, restated 2026-07-18 while
scoping this build: "make it easy for an admin to change it out and add
their own"). Replaces the flat `#12141c` header bar with the real
Stratum brand art (`public/assets/images/logo-wide.png` — hexagon "S"
mark, blue/silver metallic, "Built for communities. Designed to last."),
centered, blended into the surrounding header background, with an
admin-uploadable replacement.

**Build**: `SettingsController` gained `uploadHeaderBanner()`/
`revertHeaderBanner()`, using the same `FileUploadValidator` allow-list
pattern (jpg/png/webp, 5MB cap) every other image upload in this app
already uses. Stored outside the webroot at
`storage/uploads/site/header-banner.{ext}` — a **fixed base filename**,
so only one can ever exist and a re-upload with a different extension
deletes the old file first rather than accumulating orphans. Served back
out via a new public `GET /site/header-banner` route (registered in
`public/index.php` alongside `sitemap.xml`/`robots.txt`, not module-
toggleable, same reasoning those two already established) — not a
static public path, matching the route-served model every other
uploaded image in this app uses (gallery, downloads, etc.), unlike the
plain-pasted-URL model `avatar_url`/`banner_url`/`og_default_image`
use, since this one is a genuine file upload. `App::renderPage()`
resolves the header banner URL once per request (custom upload if
`header_banner_ext` is set, else the built-in default) and passes it to
`layout.php` as `$headerBannerUrl`. New `.site-banner` block in
`layout.php`, rendered *above* the existing nav bar (which keeps its
exact current structure/content, just now visually below the banner
image instead of sharing one flat bar with it) — a radial gradient
(`#1a3a6e` → `#0a0d16`) behind the centered image so the edges blend
into the header rather than showing a hard rectangle, capped at
`max-height: 200px` (120px on narrow viewports) so the banner reads as
a masthead, not a full-page hero, on every single page it appears on.

**No new capability** — gated by the existing `settings.manage`, since
this is genuinely a site setting, not a new class of admin-only action.

**A real bug caught during verification, not before**: the streaming
route's closure initially only captured `$app` (`use ($app)`) but
referenced `$rootDir` inside its body — a `PHP Warning: Undefined
variable $rootDir` on every request to the route, silently degrading to
a 404 instead of serving the file (the `is_file()` check failed against
a null-concatenated path). PHP closures don't inherit outer scope
automatically the way some languages' closures do; caught by watching
the dev server logs during live verification (not by `php -l`, which
can't catch this class of bug), fixed by adding `$rootDir` to the `use`
clause.

**Verification**: live end-to-end. Confirmed the default banner
(`logo-wide.png`) renders on a fresh homepage load with no setting set.
Uploaded a real, freshly-generated PNG through the actual admin form
(multipart POST, not a direct DB write) and confirmed: the setting
persisted (`header_banner_ext = 'png'`), the file landed on disk at the
expected path, the homepage's `<img>` src switched to `/site/header-
banner`, and — after catching and fixing the `$rootDir` bug above — the
streamed response was byte-identical to the uploaded file with the
correct `Content-Type: image/png` header. Confirmed a non-image file
(plain text) was rejected with zero state change (setting and file both
untouched) and the settings page showed a real error message. Confirmed
revert deleted both the setting and the file and the homepage correctly
fell back to the default image, with the streaming route itself
correctly 404ing once nothing was set. Confirmed a guest hitting the
upload/revert POST routes gets redirected to `/login`, not a silent
failure or information leak, while the *streaming* GET route stays
genuinely public (no login required) since it needs to render for every
visitor. Used `preview_inspect` to confirm the computed gradient and box
dimensions matched the CSS as written, then a screenshot to confirm the
whole page reads correctly end to end. Full project-wide `php -l` sweep
clean across all 7 new/touched files. All test uploads and settings
cleaned up afterward.

**Deliberately not built**: cropping/positioning controls (the uploaded
image is shown as-is, scaled to fit `max-height`, same "no crop tool"
scope line Profile Banner Image already drew for the identical reason —
a real cropper is a meaningfully bigger feature than this one asked
for); multiple banner variants per page/section (one banner, site-wide,
matching the original design note's scope exactly); a dark-mode-specific
variant (Stage 8 lists dark mode separately — revisit banner contrast
if/when that ships, not bundled into this pass).

## Stage 8, Fifth Slice: Top Nav Redesign ✅ (SHIPPED 2026-07-18)

**Why**: the user shared a polished reference mockup and asked for the
header/nav to match it — icon-based nav, a compact "More" overflow, and
header icons for search/messages/notifications. Timely request: Stratum
had genuinely accumulated **21 module-contributed nav items** rendered
as one flat text list, an unmanageable link wall that the mockup's
curated-primary-items + overflow-dropdown pattern actually fixes, not
just a cosmetic upgrade.

**Build**: new `.site-topbar` bar (dark, compact, ~56px), rendered
*above* the existing `.site-banner` brand art (a reversal from the
original 2026-07-13 note, which had nav below the banner — the new
reference mockup shows nav on top, and since the user is now pointing at
a concrete image rather than a text description, that image is treated
as authoritative). Seven **primary** routes (`/`, `/forum`, `/articles`,
`/online`, `/calendar`, `/gallery`, `/downloads`) render as icon+label
directly in the bar via a small presentational `$navIcons` map in
`layout.php` — deliberately not a `module.json` field, same "lives here
until a second consumer needs it" reasoning admin-layout.php's own
route-group mapping already established. Every other nav item (14 of
them) folds into a **"More ▾" dropdown**. `/search` is excluded from
both since it already has its own icon.

**New dedicated `topbar_actions` block region** (migration 015) — split
out of the generic `header` region specifically because `header` is
*also* genuinely used for wider, unrelated content (`ticker.messages`,
`ads.banner`, `presence.whosonline` all render there too), which would
have looked broken crammed into a tight icon row. Moved the two
placements that actually belong in the new topbar
(`search.searchbox`, `notifications.bell`) rather than duplicating them;
ticker/ads/who's-online stay in `header` exactly as before, unaffected,
still rendering right below the brand banner.

**Three blocks restyled/added** to the same shared icon treatment
(`.strat-header-icon` + `.strat-header-icon-badge` for unread counts):
- `SearchBoxBlock` — was an always-visible inline text input; now a
  magnifying-glass icon that toggles a small dropdown panel containing
  the same form, via one small shared vanilla-JS click handler (see
  below).
- `NotificationBellBlock` — was a text link (already existed, already
  showed a real unread badge); restyled to match, no behavior change.
- **New `messages` module** — the one genuinely new piece. Private
  messaging itself is **not built** (confirmed with the user: reserve
  the header spot now, build the real feature later as its own Stage 9
  slice, per the existing "PMs are decoupled from chat rooms" design
  note). `messages.icon` links to `GET /messages`, an honest "Member-to-
  member messaging is coming soon" placeholder — not a 404, not a fake
  inbox. No database table, no badge count (always absent, not a fake
  "0"). Swapping in the real feature later only touches this module,
  not the header markup.

**User profile dropdown** replaces the old flat "My Profile / Friends /
Log out" link list — an avatar circle (initials from the username,
first 2 characters uppercased — this app has no separate display-name
field, so inventing one to match the mockup's "FP" treatment more
literally would have meant adding schema for a cosmetic difference) that
opens a dropdown with profile/friends/admin (if applicable)/logout.
`App::renderPage()` now passes `currentUser` to the public layout (it
already did for the admin layout) since the avatar needs the username.

**One shared vanilla-JS click handler** drives all three dropdowns (nav
overflow, search, profile) — a single delegated `document.addEventListener('click', ...)`
matching `[data-dropdown-trigger]`/`[data-dropdown-panel]` attribute
pairs, closing any other open panel first and closing on an outside
click. No framework, no per-dropdown script, consistent with every
other interactive piece in this app (the hero slider's rotator, the
ticker's rotator) being small hand-written vanilla JS.

**Verification**: live end-to-end. Confirmed the primary nav renders all
7 icon+label items in the intended order with the current page correctly
marked `.active`, and the "More" panel contains the remaining 14 items.
Confirmed the region split worked — `topbar_actions` holds exactly
`search.searchbox`/`messages.icon`/`notifications.bell`, `header` still
holds `ticker.messages`/`ads.banner`/`presence.whosonline` untouched.
Confirmed guest view: search icon shows, messages/notifications icons
correctly absent (both blocks gate on `Auth::check()`), Sign up/Log in
shown. Confirmed logged-in admin view: messages icon present, real
notification badge count shown (not fabricated — pulled from the actual
already-existing `NotificationService::unreadCount()`), profile avatar
showed real initials, dropdown listed Admin (capability-gated) and a
working logout form. Confirmed `/messages` redirects a guest to
`/login` and shows the real placeholder text to a logged-in member.
**Tested the actual JS dropdown behavior in the real browser**, not just
markup presence — programmatic `.click()` on the trigger correctly
toggled `.open`, a second click closed it, and a click elsewhere on the
page closed it too (an initial `preview_click` tool call missed the
element due to a stale viewport size from earlier in the session — caught
and re-verified with a direct `.click()` call and `preview_inspect`
rather than assuming the first negative result was a real bug). Confirmed
`nav-label` spans correctly hide at narrow viewport widths via
`preview_inspect`'s computed styles. Full project-wide `php -l` sweep
clean across all 12 new/touched files, zero server errors throughout.

**Deliberately not built**: real private messaging (explicitly deferred,
see above); a "Members" directory page (the mockup's "MEMBERS" tab maps
to Stratum's existing "Who's Online" page here, the closest real
equivalent — a full member directory listing isn't itself in scope for
this pass); touch/swipe gesture support for the overflow dropdown on
mobile (click/tap already works via the same delegated handler, no
separate mobile interaction was added or needed).

**Correction, same day, after the user reported the live result looked
"squeezed"**: the first shipped version capped `.site-topbar-inner` and
`.layout` at `max-width: 1200px` (later 1400px), centered via `margin: 0
auto` — a completely standard pattern, but one the reference mockup
never actually used (it was full-bleed edge-to-edge at every width, no
boxed container visible at any point). On a wide-enough browser window
this produced large, disproportionate grey margins that read as "broken"
by comparison, not just a minor cosmetic gap. Rather than keep guessing
at cap values (1200px, then 1400px, both reported as still squeezed),
the fix was to **remove the width cap entirely** — `.layout` and
`.site-topbar-inner` are now genuinely fluid, matching the mockup's
actual full-bleed treatment at any viewport width, verified by
`preview_inspect` up to a 2200px-wide test viewport (`max-width: none`,
element width tracking the viewport exactly, no residual cap). Also
fixed a real, separate overflow bug caught from the user's own
screenshot: `.topbar-nav ul` had `flex-wrap: wrap`, which let the
primary nav items (specifically "Downloads" and "More") wrap onto a
visibly broken second line under real-world browser widths that this
session's own testing hadn't happened to hit; changed to
`flex-wrap: nowrap` plus a `.topbar-nav { overflow: hidden }` safety net,
and raised the icon-only breakpoint from 900px to 1150px so labels
disappear well before nowrap could ever clip anything. **A live
screenshot from the user's own real Firefox tab, not just my own
screenshot tool, was what actually surfaced both of these** — my own
`preview_screenshot` calls were showing a similarly cramped render
throughout this whole slice, which I incorrectly wrote off as a
tool-capture artifact (since `preview_inspect`'s computed-style numbers
looked internally consistent at the time) instead of investigating
further. Worth remembering: computed styles being self-consistent within
one tool doesn't prove the visual output is actually correct — a real
screenshot from an independent, real browser is the check that actually
caught this.

**Second correction, same day**: removing the width cap fixed the
topbar but not the content grid below it — the user reported "the
header fits the page but the rest don't" at a real 1024×768 landscape
window, and suggested a percentage-based width directly. Root cause was
a different, genuinely separate bug from the first correction: CSS Grid
items default to `min-width: auto`, which refuses to shrink a track
below its content's natural (min-content) size — `.layout`'s
`main`/`aside` grid items, and the nested `.strat-front-hero`/
`.strat-front-columns` grid items inside `<main>`, never had an explicit
`min-width: 0` override, so at narrower real-world widths their content
held those grids open wider than the viewport instead of actually
shrinking — the opposite mechanism from the first correction's fixed
`max-width` cap, but visually similar ("doesn't fit the screen").
Fixed by adding `min-width: 0` to every relevant grid item, and adopted
the user's own suggested pattern for the outer container:
`width: 95%; margin: 0 auto` instead of a fixed pixel cap or padding —
scales proportionally at any viewport rather than either a hard cap or
true edge-to-edge with no breathing room. Verified via
`document.documentElement.scrollWidth` vs `window.innerWidth` (the
actual test for "does this genuinely fit," not just eyeballing a
screenshot) at the exact reported 1024×768 landscape size plus a narrow
~500px width and the earlier 2200px wide test — zero horizontal overflow
at any of them.

**Third correction, same day — two more real, separate issues from a
follow-up screenshot**: (1) the content grid still looked squeezed even
after the min-width fix, and (2) nav labels had disappeared entirely
("no one will know what the little images are for").

For (1): the root cause wasn't width math at all — `sidebar_left`/
`sidebar_right` each had an `ads.banner` placement from earlier in the
session, but with no active ad campaign right now `AdBlock::render()`
correctly returns `''`. The block region was "occupied" but visually
empty, and the static `grid-template-columns: 200px 1fr 200px` reserved
200px for each sidebar regardless — 400px given to nothing, on top of
whatever the width fixes above already provided. Fixed by capturing
`renderRegion('sidebar_left'/'sidebar_right', ...)` into variables in
`layout.php` *before* building the grid, and computing
`grid-template-columns` (and whether to emit the `<aside>` at all)
based on whether each actually rendered non-empty content — an empty
region's column collapses entirely instead of reserving space for
nothing. Verified both directions live: confirmed the grid becomes a
single full-width column when both sidebars are empty (today's real
state), then seeded a real temporary ad banner into `sidebar_left` and
confirmed the grid correctly became `200px 734px` (sidebar reappears
with its content, `sidebar_right` — still empty — stays collapsed) —
not just "always collapse," genuinely adaptive to whatever's actually
placed there.

For (2): the 7-primary-item nav set from the original slice didn't
reliably fit with labels on the user's actual (normal, not unusually
narrow) window width, so the `max-width: 1150px` "hide labels" media
query was firing more often than intended — and icon-only doesn't
self-explain what a given icon links to, so hiding text was never an
acceptable degradation in the first place, only a stopgap that turned
out to trigger too easily. Fixed by **trimming primary nav from 7 items
to 5** (Home/Forum/Articles/Calendar/Downloads; Gallery and Who's
Online move into "More," where labels always render regardless of
screen width) and **removing the icon-only breakpoint entirely** —
labels now stay visible at every desktop width; the only remaining
narrow-screen accommodation is the existing 620px breakpoint where the
whole nav wraps to its own full-width row rather than losing any text.
Verified live: at 1024×768 the primary nav renders all 5 items with
visible `display: block` labels in a single non-wrapped row, and the
"More" panel's text content was read directly to confirm both Gallery
and Who's Online moved there correctly. Zero horizontal overflow at
1024×768 after both fixes together. Full project-wide `php -l` sweep
clean, zero server errors.

**Follow-up, same day — ad banners relocated out of sidebars entirely**
(confirmed with the user, smaller-scope option chosen over removing
sidebars site-wide): the sidebar-collapse fix above was correct, but
the underlying cause of *why* the sidebars kept ending up empty was
that `ads.banner` was the one thing seeded there, site-wide, since the
Stage 7 ads build — a real campaign being active or not directly
determined whether that 200px track had anything in it. Migration 016
deletes the `sidebar_left`/`sidebar_right` ad placements and adds a new
one into `front_col_2` (`page_scope: front_page_only`, a fresh `zone:
"front_col_2"` config so it's a distinct ad zone from the old sidebar
ones) — admin-placeable and reorderable via `/admin/blocks` like any
other block, same system this session already built rather than a
special case. `header`/`footer` ad placements are untouched — those
regions were never part of the fixed-column grid, so they never had
the reserved-empty-space problem the sidebars did. Verified live: the
sidebar grid now collapses to a single full-width column with zero ad
placements left anywhere in it, and a real temporary test campaign
confirmed the banner renders correctly inside `front_col_2` (properly
weight-ordered before `presence.whosonline`, working click-tracking
link). Full lint sweep clean, zero server errors, test data cleaned up.

**Fourth correction, same day — header banner image treatment**: the
user separately asked whether anything could fill the empty space
flanking the centered banner image, then shared a wider reference
banner variant with content spread across its full width, asking to
"shrink this down and use it across the whole section to fill it up."
First attempt: `width: 100%; height: 200px; object-fit: cover;` — spans
full width correctly, but a fixed height on a wide-aspect-ratio image
necessarily crops most of it (confirmed via `naturalHeight`/`clientHeight`
math: only ~30% of the image's height was actually visible, top-aligned).
**The user confirmed this cropped real content in an actual Firefox
tab**, not just an app screenshot — same "verify against a real
independent browser, not just this app's own tooling" discipline the
earlier corrections already established. Fixed by dropping the fixed
height and `object-fit` entirely: `width: 100%; height: auto;` — the
banner now spans full width at whatever height its own natural aspect
ratio produces, showing the complete image with zero cropping. Verified
via `img.naturalWidth`/`naturalHeight` vs `clientWidth`/`clientHeight`
math (`fullyVisible: true` — the displayed dimensions match the natural
aspect ratio exactly, not an approximation) and confirmed zero
horizontal overflow. Removed the now-obsolete mobile-specific
`max-height`/`height` override in the same pass, since `height: auto`
already scales correctly at any width without a per-breakpoint rule.
Trade-off, stated plainly: the banner is now taller on wide screens
(proportional to whatever aspect ratio the active banner image has) —
accepted deliberately, since showing the complete image was the
explicit ask, not a fixed short masthead height at the cost of cropping.

**Fifth correction, same day — auto-crop replaces the trade-off above**:
the user prepared a real cropped banner asset and uploaded it through
the actual Settings page (not a database edit — the real upload flow,
first live end-to-end test of the feature built earlier this same day)
at roughly a 3:1 ratio; still "kinda tall" once displayed. Rather than
push the user to keep manually re-cropping their own art by hand,
`SettingsController::uploadHeaderBanner()` now **auto-crops in place at
upload time** using GD (same library `ImageThumbnailer` already uses
elsewhere in this app) — any upload narrower than `MIN_BANNER_RATIO`
(width:height) gets center-cropped down to it; anything already wide
enough is left completely untouched, preserving the "show the whole
image" behavior from the correction above for well-shaped banners. The
crop isn't a naive 50/50 split — `BANNER_CROP_TOP_BIAS` (0.30) weights
most of the removed height toward the *bottom*, protecting whatever
sits near the top (a logo mark, typically) rather than cutting into it
for the sake of symmetry.

**Both constants were tuned by actually looking at the result, not
guessed**: prototyped several crop ratios/biases in Python against the
user's real banner art first, viewed the output directly, and only
ported the confirmed-good numbers (5.5 ratio, 0.30 top bias — logo and
full wordmark/tagline stayed intact, only a decorative icon row at the
very bottom got trimmed) into the actual PHP method afterward, rather
than picking a ratio in the abstract and hoping. Verified live through
the real upload endpoint (not a direct file write): re-uploaded the
user's original 3:1 asset through `POST /admin/settings/header-banner`
and confirmed the stored file came out at exactly 1717×312 (5.503:1,
matching the prototype), rendering at 230px tall on a 1280px viewport
(down from 415px before this fix) with zero horizontal overflow.
Separately confirmed the no-op path with a synthetic already-wide
(7.2:1) test image — stored completely unchanged, proving the auto-crop
doesn't touch anything that doesn't need it. Full lint sweep clean, zero
server errors, all test uploads restored back to the user's real banner
afterward.

**Sixth correction, same day — front-page blocks wrapped as cards**:
stacked blocks within one `front_col_1/2/3` column had nothing visually
separating them, reported as "running together" against the reference
mockup's clearly bordered panels. Fixed at the framework level, not per
block: `BlockRegistry::renderRegion()` gained an opt-in `$wrapInCards`
parameter that wraps each individual placement's rendered output (only
non-empty ones) in a `.strat-block-card` div before concatenating —
every block gets consistent card styling automatically, without
touching any of the ~19 individual block classes. Deliberately an
opt-in per call, not a global default: `public/index.php`'s front-page
route passes `wrapInCards: true` only for the three column regions;
`header`/`topbar_actions`/`footer`/the hero row are untouched, since a
ticker strip, an ad banner, or the search icon shouldn't turn into a
bordered card the same way a stacked content list should. Verified
live: 12 real `.strat-block-card` elements rendered (matching the
current non-empty placement count across all three columns), confirmed
via `getComputedStyle` (not just `preview_inspect`, which silently
omitted several properties from its response for this element — border/
border-radius/padding were checked directly to be sure) that
background/border/radius/padding all match the CSS exactly, zero
horizontal overflow, full lint sweep clean, zero server errors.

**Seventh correction, same day — the cards from the sixth correction
were real but invisible**: the user reported "still no cards" after the
fix above, even though the `.strat-block-card` divs were independently
re-confirmed present in the raw HTML twice (once via the browser DOM,
once via a fresh `curl` of the live server) — ruling out a rendering or
caching bug (checked `PAGE_CACHE_ENABLED` too: `false`, zero cached
files, not the cause). The actual problem: `.strat-block-card`'s
background (`#f8f9fb`) sat directly on `<main>`'s white (`#fff`)
background — a contrast delta small enough to be technically present in
the DOM/CSS but practically imperceptible at a glance, which reads
identically to "no cards" from a real user's perspective. Fixed by
giving `.strat-front-columns` its own visible gray backdrop (`#dde1e8`
— deliberately pushed to a clearly-different shade, not just barely
different, after the first contrast attempt at `#eef0f4` still measured
a fairly small delta) scoped to the front-page column area specifically
(not `<main>` globally, which stays white on every other page), and
making `.strat-block-card` genuinely white with a visible border
(`#d1d5db`) and a real box-shadow — cards now pop against a clearly
distinct backdrop instead of one near-white shade sitting on another.
Verified via `getComputedStyle` on both elements together: card
background `rgb(255,255,255)` against columns background
`rgb(221,225,232)`, a real, visually obvious difference this time, not
just a passing DOM check. Zero overflow, full lint sweep clean, zero
server errors. **Lesson for any future light-theme styling work in this
app**: a DOM/CSS property being correctly present and provably matching
what was written is not the same as it being visually perceptible —
check actual color contrast, not just "did the style apply."

## Stage 8, Sixth Slice: WordPress-Style Block Management ✅ (SHIPPED 2026-07-18)

**Why**: the third slice's `/admin/blocks` UI worked but exposed each
block's config as a raw JSON textarea, and placement only happened by
picking a block type + region from a dropdown then submitting a form —
functional but not what "visual theme editor" should feel like for a
non-technical club admin. The user asked whether WordPress's
widgets-panel model (drag a block type from a palette into a region,
edit its settings as real fields) would be easier to use, and when asked
whether to build the settings-forms upgrade and the drag-and-drop upgrade
as two separate passes or together, chose **"Do both together, full
WordPress-style."**

**Build — real settings forms, no more raw JSON**: new `ConfigurableBlock`
interface (extends `Block`, adds `configFields(): array` describing each
field's name/label/type/options/default) implemented by all 14 blocks
that actually take config (`RecentCommentsBlock`, `RecentTopicsBlock`,
`GalleryHighlightsBlock`, `RecentVideosBlock`, `RecentDownloadsBlock`,
`NewestMembersBlock`, `UpcomingEventsBlock`, `TagCloudBlock`, `AdBlock`,
`HtmlBlock`, `TextBlock`, `WelcomeCtaBlock`, and `LatestContentBlock` —
the last one the most involved, with a `category_id` select built from
real DB categories, a `display` select, and a numeric `limit`). New
`BlockConfigForm` service turns that schema into real HTML inputs
(`render()`) and turns a settings-form POST back into a config array
(`extractConfig()`, blank values dropped so they fall back to the
block's own defaults rather than persisting empty strings). New
`BlockRegistry::make()` instantiates a block without calling `render()`,
specifically so inspecting `configFields()` doesn't trigger side effects
a real render would have (`AdBlock::render()` increments an impression
counter — inspecting its schema must not count as a view).

**Build — real drag-and-drop**: `block-placements.php` rewritten as a
palette of draggable block-type chips plus one drop-zone per region.
Dropping a palette chip calls a new `POST /admin/blocks/api/create`
(creates the placement with empty config, returns the fully-rendered
card HTML — same `block-placement-card.php` partial used on page load,
so the client never duplicates template logic in JS); dropping an
existing card into a different zone calls `POST /admin/blocks/api/move`
(persists `region_id`/`weight`). Both are plain `fetch()` calls with
`URLSearchParams` bodies, not JSON — `Request::fromGlobals()` only
populates its body from `$_POST`, which requires form-encoding, so this
avoided touching the `Request` class at all rather than adding JSON-body
support for two endpoints. `BlockPlacementService::create()` now returns
the new row's `int` id directly instead of `bool`, replacing an initial,
fragile "find the latest placement of this type in this region"
lookup written before this fix. The existing move-up/move-down buttons
stay as a precise fallback — a drop always appends to the end of a
zone's list, no drag-position math.

**Verification**: full project-wide `php -l` sweep clean. Logged in as
`modtest_admin` in the real browser (not curl) and confirmed `/admin/blocks`
renders 23 palette chips and drop-zones for every region with zero
console errors. Confirmed `LatestContentBlock`'s card shows real
`<select>`s (populated with actual DB categories, correctly pre-selected)
and a real number input — zero raw JSON exposed anywhere in the UI
anymore. Simulated genuine HTML5 drag gestures with `DragEvent`/
`DataTransfer` (not just proving the AJAX endpoints work via curl):
dragging a `tags.cloud` palette chip onto a region created placement #31
live, in the DOM and in the database (confirmed via direct MySQL query);
dragging that same card into a different region's drop-zone moved it
instantly in the DOM and persisted the new `region_id`/`weight` server-
side (confirmed via MySQL query showing `region_id=8, weight=60`).
Also re-confirmed (via curl, from the same verification pass) `saveConfig()`
persists correctly — POSTing real field values produced the exact
expected `config_json`, and the public homepage re-rendered with the new
values live (3 articles from the chosen category, correct display mode,
draft articles still excluded). CSRF and guest-blocking both verified on
the new AJAX endpoints (bad token → 400, no session → 403). All test
placements deleted afterward, `LatestContentBlock`'s config reverted to
its original value, confirmed the homepage and `/admin/blocks` both
returned to their pre-test state (25 cards). Final lint sweep and dev-
server error log both clean.

**Deliberately not built**: drag-based fine-grained reordering within a
zone (drop always appends to the end; the move-up/down buttons remain
the precise tool) — same reasoning as the third slice's original
weight-swap mechanism, just no longer the *only* way to place a block.

## Stage 8, Seventh Slice: Child Themes ✅ (SHIPPED 2026-07-18)

**Why**: next item down Stage 8's confirmed list after block management.
Most of the actual plumbing already existed, unshipped and unverified,
from the Addons & Themes plugin system pass (2026-07-17): `theme.json`'s
optional `parent` field, and `TemplateEngine::resolve()`'s override chain
(active theme's own `overrides/{module}/{template}.php` → parent's same
path, built-in themes only → the module's own default template). What
was genuinely missing was what makes that plumbing actually *usable* as
a "child theme" feature rather than inert scaffolding.

**Three real gaps closed**:
1. `TemplateEngine::renderLayout()` had no parent fallback at all — a
   theme that shipped no `templates/layout.php` of its own threw
   `RuntimeException("Layout not found")` on every request, full stop.
   This directly contradicted the whole point of a *child* theme (inherit
   what you don't override) for the one file every theme actually has —
   the base layout. Fixed: falls back to the parent's `templates/
   layout.php` (built-in themes dir only, matching the existing
   override-chain constraint) when the active theme has none of its own.
2. `ThemePackageInstaller::install()` unconditionally required
   `templates/layout.php` in every uploaded zip — meaning even a
   theme.json declaring a `parent` couldn't actually be lean; the
   requirement is now conditional: a declared `parent` must resolve to a
   real built-in theme (checked at install time, not deferred to the
   first page render and a 500), and only then is `templates/layout.php`
   optional.
3. **No way to create a child theme without hand-building a zip.** Given
   the production reality (non-technical club admins, per
   `stratum-production-context.md`), requiring an admin to manually
   author `theme.json` + zip it up defeats the "give clubs a real
   customization story" point of Stage 8 entirely. New `ThemePackageInstaller::
   createChild(id, name, description, parentId)` scaffolds
   `storage/themes/{id}/theme.json` + an empty `overrides/` directory
   directly on disk, no zip round-trip — immediately activatable, byte-
   for-byte identical to its parent until the admin adds real override
   files later (still a hands-on-the-server step for now, same as any
   override file today — a full in-browser template editor is separate,
   larger scope, not attempted here).

**Build**: new `POST /admin/themes/create-child` (`ThemesController::
createChild()`, gated `themes.manage`, CSRF-checked) and a "Create a
child theme" form on `/admin/themes` — a `<select>` of parent choices
restricted to built-in themes only (the same constraint the override
chain already enforces, so every choice offered is guaranteed to
actually resolve), an id field, name, and description. Rejects an
invalid/non-built-in parent and a colliding id with a clear inline
error, same pattern every other admin action in this app uses
(`?error=` query param).

**Verification**: full project-wide `php -l` sweep clean, both before
and after. Live, via curl against the real dev server (logged in as
`modtest_admin`): created a real child theme (`verify_child`, parent
`default`, no `templates/layout.php` at all) — confirmed on disk
(`theme.json` + `overrides/`), activated it, and confirmed the homepage
still rendered correctly (200, real `.site-topbar` markup present) purely
via the new parent-layout fallback — no layout.php of its own anywhere.
Added a real override (`overrides/tags/index.php`, a distinguishing
`<h1>CHILD THEME OVERRIDE MARKER</h1>`) and confirmed `/tags` rendered
the override while the child theme was active, then reactivated `default`
and confirmed `/tags` reverted to the real, un-overridden heading —
proving the override is genuinely scoped to the theme that's active, not
a global leak. Confirmed the negative paths too: a bogus `parent_id`
rejected with a clear error and zero directory created; a colliding
theme id rejected the same way; an unauthenticated request redirected
instead of creating anything. Deleted the test theme afterward and
confirmed `storage/themes/` was empty again and no stray references
remained anywhere in the app. Final lint sweep and dev-server error log
both clean throughout.

**Deliberately not built**: an in-browser template/override editor (a
club admin still edits `overrides/*.php` files on the server by hand,
or via a properly-zipped theme upload — the same technical bar every
other override in this app already has); child themes extending a
*custom* (not built-in) parent theme — unchanged from the existing,
documented constraint set during the Addons & Themes pass, not
reopened here.

## Stage 8, Eighth Slice: Color & Typography Manager ✅ (SHIPPED 2026-07-18)

**Why**: next item down Stage 8's list after child themes. Scope was
deliberately kept to what the default theme's own chrome can honestly
support, not a general re-skinning engine — this app has no CSS
framework or design-token system, `layout.php`'s `<style>` block is
hand-written CSS with hardcoded hex values, and ~40 module templates
across the app style themselves independently with their own inline
colors. A real "recolor everything" feature would mean sweeping every
module template onto CSS variables, a much larger refactor out of scope
here.

**Build**: one new CSS custom property, `--strat-accent`, set once at
the top of `layout.php`'s `<style>` block from a new `theme_accent_color`
setting (hex, `<input type="color">` picker — native, zero JS) and
referenced by the 4 declarations in the file that actually use the
accent blue (nav active state, profile avatar background, the guest
"Log in" button) — one place to change instead of four. Body font
family comes from a new `theme_font_stack` setting, resolved through a
new `FontStacks` service (`core/services/FontStacks.php`) — a curated
map of 4 real font stacks (System Default, Classic Serif, Friendly
Sans-Serif, Monospace), deliberately not a free-text field: a
non-technical admin has no way to debug an invalid raw CSS string, and a
picker matches how this kind of customizer normally works anyway
(WordPress's own font controls are a preset list, not free text). Both
settings live on the existing `/admin/settings` page (`core_settings`
key/value rows, same `getSetting()`/`setSetting()` helpers every other
setting on that page already uses) under a new "Colors & Typography"
section — no new page, no new capability. `App::renderPage()` reads
both, re-validates the hex format even though `SettingsController::
update()` already validated it at save time (defense-in-depth against a
malformed value ever reaching a raw `<style>` block from any future
write path), and passes them into `renderLayout()`'s data array — which
means **any** theme's own `layout.php`, not just the built-in default,
can choose to read `$accentColor`/`$fontStackCss` if its author wants to
support the setting; only the default theme currently does.

**A real bug caught before it shipped, not after**: the first version
used `e($fontStackCss)` to embed the font-family value inside the
`<style>` block, exactly like `$accentColor`. That's wrong — `<style>`
is an HTML5 "raw text" element, so character entities like `&quot;`
inside it are never decoded by the browser; two of the four curated font
stacks contain quoted font names (`Georgia, "Times New Roman", serif`),
and `e()`'s `ENT_QUOTES` would have turned those into literal `&quot;`
text in the rendered page, silently breaking the `font-family`
declaration for anyone who picked Classic Serif or Monospace. Caught by
inspecting the actual rendered HTML during verification, not just
confirming the page returned 200. Fixed with `raw()` instead (the
existing helper this app already uses for any value that's safe by
construction rather than needing escaping) — safe here specifically
because `$fontStackCss` only ever comes from `FontStacks::cssFor()`'s
own fixed, hardcoded map, never directly from request input.

**Verification**: full project-wide `php -l` sweep clean, both before
and after the fix above. Live via curl against the real dev server:
confirmed the default render (`--strat-accent: #2f6fed`, real
double-quoted `system-ui, -apple-system, "Segoe UI", sans-serif`, not
escaped entities); set a real custom accent (`#c0392b`) and font
(Classic Serif) through the actual settings form and confirmed the
homepage's rendered CSS picked up both immediately, with all 4 accent
declarations correctly using `var(--strat-accent)`; confirmed the admin
panel's own chrome was completely unaffected (zero occurrences of the
new variable or the custom color anywhere in `/admin`'s HTML — matches
the standing, documented rule that the admin shell never goes through
the public theme system). Tried a real XSS payload
(`<script>alert(1)</script>`) as the accent color and a bogus font key —
both rejected server-side and silently fell back to the safe defaults,
confirmed both in the re-rendered homepage HTML and by querying
`core_settings` directly in MySQL (stored values were the literal
defaults, not the injected string). Reset to defaults afterward (the
invalid-input test happened to leave the settings at their clean
baseline already, confirmed via the same DB query). Dev-server error
log and a final lint sweep both clean throughout.

**Deliberately not built**: a page-background-color control, a
secondary/heading font distinct from the body font, or any control that
would require touching the ~40 module templates' own hardcoded inline
colors — all explicitly out of scope per the reasoning above, not
gaps; free-text font-family input (the curated picker is the deliberate
choice, not a placeholder for a future upgrade).

## Stage 8, Ninth Slice: Menu Builder ✅ (SHIPPED 2026-07-18)

**Why**: last item on Stage 8's list before dark mode. Before this,
public nav was purely dynamic — `ModuleManager::navItems()` re-derives
the whole list from every enabled module's `module.json` on every single
request, zero DB involvement, zero admin control over order, labels, or
which items show directly in the topbar ("primary") vs. fold into the
"More" dropdown. That split was even hardcoded directly in `layout.php`'s
own `$navIcons`/`$primaryRoutes` arrays from the Fifth Slice's top-nav
redesign — this slice replaces that hardcoded list with real,
admin-editable state.

**The core design problem this had to solve**: module-contributed nav
items need to keep "just appearing" the moment a module is enabled,
exactly like today, with zero admin action required — a menu builder
that silently hides every newly-enabled module's nav link until an
admin remembers to visit `/admin/menu` would be a real regression, not
an upgrade. Solved with a *reconciling* overlay rather than a one-time
seed: new `nav_menu_items` table (migration 017) plus `NavMenuService`,
whose read path (`orderedItems()`, called from `App::renderPage()` on
every public request) first calls `syncModuleItems()` — a cheap upsert
that inserts a default row (placement `more`, appended to the end) for
any live module nav item that doesn't have a DB row yet — before
returning the weight-ordered, placement-split result. A module-sourced
row whose module has since been *disabled* is skipped at render time
(not deleted), so re-enabling the module later restores whatever
placement/label the admin had already given it, with zero data loss.
The migration also seeds the exact pre-existing primary-route list
(Home/Forum/Articles/Calendar/Downloads) so activating this feature is
a verified no-op for every existing install's visible nav until an
admin deliberately changes something.

**Build**: `/admin/menu` (new `NavMenuController`, gated by a new
`nav.manage` capability — split out the same way `blocks.manage`/
`themes.manage` were, so a club's designer/webmaster role doesn't need
full `admin.access`), one row per nav item (module-sourced or admin-
added "custom" link) with an editable label, a Primary/More/Hidden
placement `<select>`, move-up/move-down (weight-swap within the same
placement bucket, same mechanism `BlockPlacementService`'s own
`moveUp()`/`moveDown()` already established), and Remove/Reset (a
`source='custom'` row is genuinely deleted; a `source='module'` row is
just reset — the very next `syncModuleItems()` call re-adds it with
default placement, since the row is genuinely gone). A second form adds
a custom link — internal path or full external URL, detected by an
`http(s)://` prefix rather than a stored flag, rendered with
`target="_blank" rel="noopener"` automatically when external.
`layout.php` no longer computes `$primaryNav`/`$moreNav` itself — they
arrive pre-resolved from `App::renderPage()`; the file keeps its small
route→emoji icon map as presentational-only, now with a generic fallback
glyph for any newly-promoted item that has no specific icon defined.
`/search` stays fully excluded from the whole system (never synced,
never a manageable row) since it already has its own icon in
`topbar_actions` — the exact exclusion `layout.php` used to hardcode.

**A real markup bug caught before shipping, not after**: the first
draft of the admin table used one `<form>` per row wrapping several
`<td>` cells directly inside a `<tr>` — invalid HTML (a `<form>` can't
be a `<tr>`'s child, and `<td>`s can't be a `<form>`'s children), which
real browsers "fix" via foster-parenting the form content out of the
table structure in unpredictable ways. Caught before any live testing,
not discovered as a rendering bug — rewritten as flexbox `<div>` rows
(matching the `block-placement-card.php` pattern the block-placement
manager already established for exactly this "one row = one form with
several actions" shape), which sidesteps the whole table-content-model
question entirely.

**Verification**: full project-wide `php -l` sweep clean, migration
applied cleanly (seeded exactly 5 primary rows, `nav.manage` capability
granted to admin/founder). Live via curl against the real dev server,
logged in as `modtest_admin`: confirmed the homepage's rendered primary
nav and "More" dropdown content matched the pre-migration baseline
byte-for-byte (the "no-op upgrade" guarantee); loading `/admin/menu`
for the first time correctly auto-synced all 21 live module nav items
(5 seeded primary + 16 newly-discovered "more") with zero duplicates.
Exercised the full real feature, not just the plumbing: promoted
"Gallery" to primary and renamed it to "Photos" — appeared correctly
and immediately in the live topbar; added a real external custom link
("Club Facebook") — appeared in "More" with `target="_blank"`; moved
"Photos" up a position — topbar order changed correctly; hid "Tags" —
disappeared from the live page entirely. **Module disable/re-enable
interaction tested directly, the riskiest part of this design**:
disabled the real `links` module — its nav item vanished from the live
page immediately and its admin-table row was correctly flagged stale
(dimmed, "module disabled" label) without being deleted; re-enabled it
— reappeared on the live page instantly, same DB row (same id), same
label/placement as before disabling, not reset or duplicated. Confirmed
the negative paths: an invalid placement value rejected server-side
with the row provably unchanged (checked via direct MySQL query, not
just the redirect); a blank label/route on the custom-link form
inserted zero rows; a forged CSRF token rejected with 400; a guest
request redirected to `/login` without touching the database. Cleaned
up every piece of test data afterward (deleted the custom Facebook
link, reverted Gallery/Tags back to their real state) and confirmed the
final row set was exactly the original 21 items with correct content —
final lint sweep and dev-server error log both clean throughout.

**Deliberately not built**: per-item nav icon picker (icons stay a
small presentational fallback map in `layout.php`, cosmetic only);
nested/dropdown submenus beyond the existing flat "More" bucket;
managing `guestNav` (the Sign up/Log in links) through this same
system — a conceptually different, much smaller "auth actions" list,
not general site navigation, left untouched.

## Stage 8, Tenth Slice: Dark Mode ✅ (SHIPPED 2026-07-18) — closes out Stage 8

**Why**: the last item on Stage 8's confirmed list. Three admin-chosen
modes rather than one blanket switch — Off (always light, today's exact
behavior), On (always dark), and Auto (follows each visitor's OS
preference, with a manual per-visitor toggle that overrides it) — because
a forced site-wide mode and a visitor-personalized one are genuinely
different features with different implementation needs, not one
mechanism with a settings flag.

**Build**: extends the Eighth Slice's `--strat-accent` CSS-custom-
property pattern with a small palette of theme-role variables
(`--strat-bg`, `--strat-text`, `--strat-card-bg`, `--strat-card-border`,
`--strat-columns-bg`, `--strat-dropdown-bg`, `--strat-dropdown-hover-bg`,
`--strat-muted-text`) — light and dark value sets both defined once in
`layout.php`, applied to every place in the file that used to hardcode
one of those colors directly (body bg/text, `.layout main`, the
front-page column backdrop, `.strat-block-card`, the header dropdown
panels, footer/muted text). The topbar itself (`.site-topbar`, already
a dark bar) is deliberately untouched — it stays visually dark in both
site themes, a common, intentional pattern, not an oversight. Each mode
is handled at the layer that actually fits it: Off/On just bake the
matching palette directly into the one `:root` block, no JS, no
attribute, no media query at all — a site forced one way doesn't need
any client-side machinery. Auto is the one mode needing a per-visitor
override on top of an OS-level default: `:root` carries the light
values, `@media (prefers-color-scheme: dark)` supplies the dark ones
automatically, and a `:root[data-theme="dark"]` block (only ever stamped
onto `<html>` by a tiny synchronous inline script, from `localStorage`
or `matchMedia`, running in `<head>` before `<body>` starts painting —
no flash of the wrong theme on load) lets a manual toggle win over both
directions. The toggle itself is a small moon-icon button rendered next
to the profile/login controls, only in Auto mode, wired to a second
small inline script that flips `data-theme` and persists the choice to
`localStorage`.

**A real, useful finding from surveying the codebase before building,
not just after**: grepped all ~40 module templates for hardcoded
background/text colors before starting, specifically to answer "will
dark mode look broken the moment it hits real content pages, not just
layout.php's own chrome" — found zero hardcoded `background: #fff`/
`color: #000`-style declarations anywhere in module templates. They
were already relying on inherited color from `body`/`<main>` the whole
time, which meant this slice reaches much further than the Eighth
Slice's documented "theme chrome only" boundary could — real content
pages (forum, articles, etc.) render correctly in dark mode with zero
changes to their own templates, confirmed live, not just theorized from
the grep.

**Verification**: full project-wide `php -l` sweep clean. Live via curl
against the real dev server: Off mode confirmed byte-identical to the
pre-dark-mode baseline (zero occurrences of `data-theme`, the media
query, or the toggle button anywhere in the HTML) and admin panel
confirmed completely unaffected by any mode. On mode confirmed the dark
palette values baked directly into `:root` with zero toggle/script
markup present. Auto mode confirmed all the expected pieces present
(light default, dark media-query block, `data-theme="dark"` block, FOUC
script, toggle button, toggle click handler). **Exercised the actual
toggle interaction in the real browser, not just the markup**: the
preview browser's OS-level dark preference correctly triggered the dark
palette automatically on first load with zero stored preference (real
`getComputedStyle` check: `rgb(21, 23, 28)`, not just "the CSS rule
exists"); clicking the toggle flipped to light and persisted to
`localStorage`; reloading the page confirmed the stored choice
correctly *overrode* the OS's own dark preference (proving the override
direction that actually matters — a visitor's manual choice has to win
over their system setting, not just supplement it) — checked via a real
computed background-color both before and after reload, not assumed
from the attribute alone. Checked the profile dropdown panel's computed
colors directly (`rgb(30, 33, 40)` background, `rgb(228, 230, 235)`
text — the exact intended dark-palette values, not close-enough
approximations) and loaded a real content page (`/forum`) to confirm
the "reaches further than chrome" finding above held up visually, not
just in a grep. Reverted the settings to Off afterward and confirmed
the homepage and `core_settings` both matched the original baseline
exactly. Dev-server error log and a final lint sweep both clean
throughout.

**Deliberately not built**: a per-module dark-mode pass (module
templates already inherit correctly, per the finding above — nothing to
do there); a fourth "high contrast" or other accessibility-specific
theme (out of scope, a different feature); remembering the Auto-mode
toggle choice server-side per logged-in account (deliberately
`localStorage`-only, matching this app's "no framework, minimal state"
posture — a signed-in member's preference doesn't need to follow them
to a different browser for this to be a complete, useful feature).

**Stage 8 — Customization is now fully shipped**: all six deliverables
from the original scope (visual theme editor/block placement, child
themes, color/typography manager, drag-drop block placement, menu
builder, dark mode) are done, across ten slices in one continuous
session on 2026-07-18.

## Vision Parity Backlog (established 2026-07-14)

**Why this section exists**: Stratum's actual driving requirement is feature
parity with e107, SMF, and ocPortal — there are real clubs and groups
currently running those systems, on a list to migrate, who expect to find
the same features when they switch. That's not speculative scope creep;
it's the spec. Earlier doc-audit language in this file called some of the
items below "revisit only if a real need shows up" — that framing was
wrong, because the real need already exists (the migrating clubs), it's
just not yet built. Every item below is tracked, real, intended work — not
a maybe. There is no timeline pressure on any of it: it gets built across
however many sessions it takes, in whatever order makes sense, until each
item is done. Whether a given gap gets closed by reopening its original
stage or by building it as new work at the end of the roadmap makes no
practical difference — nothing below requires reverting or un-shipping
anything already built; every item is additive to what exists.

**Sequencing note**: most items below are independent of each other and can
be tackled in any order. Two are not — **Notifications** and **Activity
Feed** are the two genuine dependencies underneath a lot of the rest (forum
mentions/replies only mean something once there's a notification system to
deliver them to; "recent activity across the club" needs an activity feed
engine to aggregate into). Building those two first avoids building
mentions/replies/uploads-notify shallow now and having to reopen them later
to wire into notifications properly. Everything else on this list has no
such ordering constraint.

### Foundational / shared platform systems (none started — build first)

- **Notifications** ✅ — SHIPPED 2026-07-14, see the "Notifications"
  section above. The `notify` hook + `App::notify()` mechanism is now the
  standard way any future module (or parity feature below) pushes a
  notification — mentions, invitations, and event reminders plug into it
  rather than building anything new.
- **Activity Feed** ✅ — SHIPPED 2026-07-16, see the "Activity Feed"
  section above. Both foundational dependencies (this and Notifications)
  are now done — everything else on this backlog is unblocked and can be
  tackled in any order.
- **Global tagging** ✅ — SHIPPED 2026-07-18. Full writeup above.
- **Bookmarks / favorites** ✅ — SHIPPED 2026-07-16, see the "Bookmarks /
  Favorites" section above. Articles, wiki pages, and forum topics are
  bookmarkable in v1; downloads/classifieds join by adding a
  `ContentResolver` case + allow-list entry + button each.
- **Audit log** ✅ — SHIPPED 2026-07-18. Full writeup above.
- **Moderation / reporting queue** ✅ — SHIPPED 2026-07-16, see the
  "Moderation / Reporting Queue" section above, built together with forum
  Reports as its first consumer. Gallery photos / classifieds listings
  join by adding a resolver-map entry + a report link each.
- **Localization / i18n** ✅ — SHIPPED 2026-07-18 (framework +
  representative demonstration, not full-app string coverage — see the
  full writeup above for exactly what that means). Full writeup above.
- **Cache manager** ✅ — SHIPPED 2026-07-18. The long-standing "wait for
  real traffic data" hold was explicitly overridden by the user at the
  2026-07-17 night-before check-in ("build it anyway tomorrow"), so this
  shipped speculatively, ahead of any real hosting/traffic signal — see
  the full writeup above for the off-by-default posture that keeps that
  a low-risk choice.
- **Who's Online / presence** ✅ — SHIPPED 2026-07-17. New `presence`
  module — one `strat_presence` table, one row per active session
  (`UNIQUE(session_id)`, `user_id` nullable, guest and member are the same
  kind of row, just told apart by whether `user_id` is set), a public
  `/online` page, and a header block (seeded at weight 30, after ticker/
  search/notifications) showing a live "N members, M guests online"
  summary plus who they are.
  **The one genuinely new architectural wrinkle**: this is the first
  feature in the app that needs to run on *every* request regardless of
  route — not something any module's `routes.php` or hook can express.
  Wired directly into `public/index.php` right after `ModuleManager::boot()`
  (same "needs to exist before normal module wiring" reasoning that
  already put `AuthService`'s require there), gated on
  `isEnabled('presence')` so disabling the module actually stops the
  writes, not just hides the UI.
  **"Online" is computed, not cached** — same discipline as everywhere
  else in this app (forum/wiki counts, gallery likes, donation progress):
  a session counts as online if `last_seen_at` is within the last 5
  minutes, checked fresh on every read, no separate "is_online" flag to
  drift out of sync.
  **Deliberately uses MySQL's `NOW() - INTERVAL 5 MINUTE` for both the
  write and the read, never a PHP-computed timestamp** — directly
  motivated by the same-day timezone bug found while building scheduled
  publishing (PHP defaulting to UTC vs. MySQL/OS on CDT). A presence
  feature comparing a PHP-computed cutoff against MySQL-stored timestamps
  would have been silently wrong on any install where `APP_TIMEZONE`
  isn't configured to exactly match the DB server's own timezone — using
  MySQL's own clock for both sides removes that dependency entirely,
  correct regardless of whether `APP_TIMEZONE` is set right.
  **Verified end-to-end**: a bare guest request recorded a presence row
  with `user_id NULL`; the same session logging in and making a follow-up
  request correctly flipped that row to the member's `user_id` (the login
  POST itself was correctly still recorded as a guest, since the touch
  happens before that request's own auth state changes — proved this
  wasn't a fluke by inspecting the actual row sequence, not assumed);
  `/online` showed real usernames with correct last-seen timestamps and an
  accurate combined guest count (which correctly included the test
  script's own bare `curl` hits as genuine additional guest sessions —
  proving the counting isn't just counting logged-in users); the header
  block rendered in the correct position after the three existing
  header blocks; manually aging one session's `last_seen_at` to 10
  minutes old correctly dropped it out of both the member list and the
  count on the very next read, with zero code path treating "online" as
  anything other than a live computation; disabling the module stopped
  new rows from being written at all (confirmed via a real page view
  producing zero new rows, not just an assumption) and 404'd `/online` and
  removed the block, with re-enabling immediately restoring tracking.
- **Built-in SEO** ✅ — SHIPPED 2026-07-17. Meta tags, sitemap.xml,
  canonical URLs, OG/Twitter Card tags for shareable content, site-wide
  defaults in Site Settings. Full writeup above.
- **Trash / recycle bin** ✅ — SHIPPED 2026-07-17. `core/services/
  TrashService` — one per-type registry (table, title resolution, URL,
  restore), same pattern `ContentResolver`/`SearchService`/`ActivityService`
  already established for "one map, many content types," at
  `/admin/trash` gated by a new `trash.manage` capability (one capability
  for the whole bin, same "one queue, one capability" precedent
  `moderation.manage` set — not a per-content-type check). Confirmed the
  premise exactly right: the hard part (soft-delete discipline) really was
  already done everywhere, this genuinely was just a missing UI layer over
  data that already existed correctly.
  **v1 covers 11 site-wide content types**: articles, pages, wiki pages,
  forum topics, forum posts, calendar events, downloads, classifieds
  listings, gallery albums, gallery photos, videos. Two structural
  patterns needed: most types carry their own title (a direct row read),
  but forum posts and gallery photos don't (a post has a body, not a
  title; a photo has an optional caption) — those two use a `LEFT JOIN`
  to their parent (topic / album) for both title ("Post in \"...\"") and
  URL, with a `deleted_at`-count row missing check.
  **Deliberately excludes `users`** — a soft-deleted account has
  different restore semantics (password state, sessions, capacity) than
  restoring a piece of content, and deserves its own explicit decision
  rather than being folded into this feature.
  **v1's other two scope cuts — the six org_spaces private-content tables
  and polymorphic `comments` — shipped the same day as "Trash Bin:
  Remaining Type Coverage"** (see that entry above): 18 types total now,
  same registry, same restore path, zero new architecture.
  **Verified against real data, not seeded fixtures**: the live dev
  database already had genuine soft-deleted rows spanning back to
  2026-07-13 from earlier sessions' verification work across 8 of the 11
  types — the trash list surfaced all of them correctly on the first
  load, titles and URLs resolved right including both join-based types
  (a gallery photo correctly showing "Photo in \"Club Picnic 2026\""
  linking to its album, a forum post correctly showing "Post in \"Welcome
  to the club\"" linking to its topic). Restored a real classifieds
  listing and a real forum post through the actual UI and confirmed each
  reappeared exactly where it belonged (the public listing, the forum
  thread) with zero data loss. Created and soft-deleted a page, wiki
  page, and calendar event to cover the 3 types with no pre-existing
  trash data, confirming all 11 types end-to-end. Guest redirected to
  login, a real plain-member account got 403, missing CSRF got 400,
  disabling a type's module correctly hid its rows from the list and
  re-enabling restored them (data untouched throughout), and restoring a
  nonexistent id was a harmless no-op rather than an error.

### Member system parity (extends Stage 2 — not started)

- Friends / following ✅ — SHIPPED 2026-07-17. Full writeup above.
- Achievements / badges ✅ — SHIPPED 2026-07-17. Full writeup above.
  Admin-awarded only in v1; auto-triggered achievement rules deliberately
  not built, see that entry for the scoping reasoning. `strat_ranks.icon`
  remains untouched — no rank-management admin UI exists to hang it off.
- Reputation ✅ — SHIPPED 2026-07-17. Decision made explicitly (asked the
  user directly, per this entry's own instruction): build a real system.
  Full writeup above.
- Member notes / staff notes ✅ — SHIPPED 2026-07-17. Full writeup above.
- Profile banner image ✅ — SHIPPED 2026-07-17. Full writeup above.
- Account export / deletion workflow ✅ — SHIPPED 2026-07-17. Full writeup
  above.
- Account merge ✅ — SHIPPED 2026-07-17. Full writeup above.

### Forum parity (extends Stage 3b — ✅ ALL SHIPPED)

- Sub-boards ✅ — SHIPPED 2026-07-17. Full writeup above.
- Polls, attached to topics ✅ — SHIPPED 2026-07-17. Full writeup above.
- Reports ✅ — SHIPPED 2026-07-16 with the Moderation/reporting queue
  (posts are reportable; topics are reported through their posts).
- Likes ✅ — SHIPPED 2026-07-16 (see "Forum Parity Batch" above; likes
  only, no reaction variants).
- Mentions ✅ — SHIPPED 2026-07-16 (same batch; notification-only, no
  in-body highlighting or autocomplete).
- Signatures ✅ — SHIPPED 2026-07-16 (same batch).

### Articles & content workflow parity (extends Stage 3a — ✅ ALL SHIPPED)

- Article revision history ✅ — SHIPPED 2026-07-17, built directly against
  wiki's proven Stage 3c pattern — same append-only shape, same "compute,
  don't cache" discipline: `articles.body` is gone entirely, "current" is
  simply the latest row in a new `articles_revisions` table, exactly like
  wiki's pages have no `body` column of their own. `ArticleService` gained
  `currentRevision()`/`listRevisions()`/`findRevision()`/`addRevision()`/
  `restoreRevision()`, mirroring `WikiService` method-for-method; new
  public routes `/articles/{slug}/history` and `/history/{revisionId}`
  (view-only, public — same visibility as the article itself) and
  `/history/{revisionId}/restore` (`articles.manage`-gated, unlike wiki's
  `wiki.edit`-for-everyone model, since articles stayed admin-curated
  content throughout this app). The admin edit form gained an optional
  edit-summary field, same as wiki's `comment`.
  **Real data migration, not just a schema change**: a new core migration
  creates `articles_revisions` and migrates every existing article's
  then-current `body` into a first revision (comment "Initial version,
  migrated") *before* dropping the column — no article's content was at
  risk of being silently lost by this change.
  **Two other real call sites had to be found and fixed, not just the
  articles module itself** — this is exactly the kind of ripple a "drop a
  column" change causes, and both were caught by grepping the whole
  codebase rather than assuming articles was self-contained: Search's
  `articlesBranch()` was reading `body` directly off the articles table
  (now a join to the latest revision, the identical pattern its own
  `wikiBranch()` already used one field over); the RSS feed exporter
  (`ArticleFeedExporter`, Stage 4c) was doing the same for `/feed.xml`'s
  `<description>` — both fixed and re-verified working.
  **Verified end-to-end**: all 8 pre-existing articles correctly migrated
  and rendering their original body unchanged after the migration, not
  just schema-valid but content-correct; created a real article, edited it
  with an edit-summary comment (second revision recorded, listed
  newest-first with correct summary text); viewed the original first
  revision directly and confirmed its distinct original content; restored
  it as admin and confirmed a **third** revision appeared (not a history
  rewrite) with the live page immediately reflecting the restored
  content; a non-admin got a hard 403 attempting restore directly via
  POST (and saw no restore control in the UI at all), while a guest could
  still freely view history/individual revisions (public, read-only,
  matching wiki's own visibility model); Search picked up the *current*
  revision's content correctly; `/feed.xml` produced correct, real,
  BBCode-rendered `<description>` content for every article after the
  fix, confirmed by inspecting the raw XML output directly, not just a
  200 status.
- Article ratings ✅ — SHIPPED 2026-07-17 as the new shared "Content
  Ratings" system (see that entry above) — covers both articles and
  downloads together, since both were confirmed wants at once.
- Scheduled / draft publishing workflow ✅ — SHIPPED 2026-07-17. Admin form
  now offers three explicit choices — Save as draft / Publish now /
  Schedule for [datetime] — replacing the old single "Published" checkbox.
  Scheduling stores a future `published_at`; `is_published` stays 0 until
  the moment passes. **Critical design point**: since `cron.daily` only
  runs once a day, gating public visibility on `is_published` alone would
  leave a 9am-scheduled article invisible for up to ~18 hours after it
  should have gone live. Fixed by making every read path (article show
  page, `listPublished()`, Search's article branch, Activity Feed's
  article branch, `ContentResolver`'s article case used by
  bookmarks/moderation) check `is_published = 1 OR (published_at IS NOT
  NULL AND published_at <= NOW())` — visibility is accurate to the second
  the moment it's queried, regardless of whether cron has caught up.
  `cron.daily` still flips the flag (via a new `ArticleService::
  publishDueScheduled()`, articles' `Module.php` gained a `cron.daily`
  listener matching ticker's/rss_aggregator's pattern) purely as DB-state
  housekeeping — a consistent-looking flag for admin-list filtering and
  future direct-query consumers, never the actual gate.
  **A real, previously-undiscovered bug was found and fixed during
  testing, not before**: this dev machine's PHP defaulted to UTC while
  MySQL/the OS both run `America/Chicago` (CDT) — a live 5-hour gap, the
  exact class of PHP-vs-MySQL timezone mismatch the original Site Search
  bug warned about, except this one was systemic, not scoped to one
  query. It silently affected the calendar module too (`CalendarService`
  parses `datetime-local` input via the same un-timezoned
  `DateTimeImmutable` pattern) — every calendar event ever created may
  have been stored an offset away from what the admin actually typed.
  Root-caused and fixed in `core/bootstrap.php`: `date_default_timezone_
  set()` now reads a new `APP_TIMEZONE` env var (default `UTC`, safe but
  imprecise until set), fixing PHP/MySQL agreement for every
  `DateTimeImmutable` parse app-wide, not just articles. The web installer
  (`public/install.php`) gained a timezone field on the DB-connect step
  (a text input with an HTML `<datalist>` of all IANA identifiers,
  server-side validated against `timezone_identifiers_list()`) so every
  future real install sets this correctly on day one instead of silently
  defaulting to UTC forever with no self-service way to fix it.
  **Verified end-to-end on the live dev server**: created a draft (stayed
  fully invisible, correct empty `published_at`), a publish-now article
  (immediately live), and — after fixing the timezone bug the first
  attempt surfaced — a genuinely future-scheduled article that stayed
  correctly hidden from the public listing and its own direct page,
  then automatically became visible on both the instant its scheduled
  time passed (confirmed via a real wait-loop against MySQL's own clock,
  zero cron involvement) while `is_published` was still 0 in the
  database; running `bin/cron.php` then flipped the flag as expected
  housekeeping. Confirmed the newly-visible scheduled article was
  simultaneously found by Search, appeared in the Activity Feed, and
  could be bookmarked — all three integration points that would have
  silently lagged behind the article's own page by up to 18 hours without
  the OR-condition fix applied consistently everywhere. A scheduled time
  entered in the past correctly auto-published immediately instead of
  erroring. The admin list correctly displays "Draft" / "Scheduled for
  {datetime}" / "Published" as a live-computed status, and the edit form
  correctly pre-fills which of the three radio states an existing article
  is actually in, including the datetime value for a scheduled one.
  **Deliberately not extended to pages/wiki in this pass** — matches the
  original scope note; a natural follow-on once/if needed, same pattern
  ready to copy.

### Organization tools parity (extends Stage 4 — partially started, confirmed real wants 2026-07-16)

- Generic surveys / forms ✅ — SHIPPED 2026-07-17. Full writeup above.
- Calendar: maps ✅ — SHIPPED 2026-07-17. Full writeup above.
- Calendar: attendance tracking ✅ — SHIPPED 2026-07-17. Full writeup
  above.
- Link directory ✅ — SHIPPED 2026-07-17. Full writeup above.

**Organization tools parity is now 100% shipped.**

### Organization Spaces parity ✅ (extends Stage 4e — SHIPPED 2026-07-17, GO-LIVE BLOCKER for the 18-chapter club, cleared)

**Corrected 2026-07-16, then decided same day**: this section was written
with hedge language ("revisit if multi-chapter clubs actually ask for it")
as if the need was hypothetical. It isn't — one of the 8 real clubs
migrating to Stratum has 18 chapters around the US, and `org_spaces` was
purpose-built for exactly that. Confirmed with the user: that club's
launch needed the fuller per-chapter slice — private forum, private
calendar, shared files, and shared photo gallery per chapter — not just
the v1 that shipped in Stage 4e (officers/roster/announcements). That
club still also needs the Web-Based Installer to actually go live; this
entry only covers the feature-completeness half. The other 7 clubs don't
need any of this — it's specific to the one multi-chapter deployment.

**What shipped**: four new per-org content types, all under
`core/modules/org_spaces/` — `OrgForumService`/`OrgForumController`
(flat topics/posts, no boards/categories, since an org space is one small
community, not a multi-board site forum — lock/delete moderation, BBCode,
reply notifications), `OrgCalendarService`/`OrgCalendarController`
(events with title/description/location/starts_at/ends_at — deliberately
no recurring-event materialization or RSVP, neither was part of the
confirmed requirement), `OrgFileService`/`OrgFileController` (single-
version uploads — no version history like the site-wide `downloads`
module has, same "not confirmed, don't build ahead of it" reasoning),
`OrgGalleryService`/`OrgGalleryController` (albums + real GD thumbnails
via the shared `ImageThumbnailer`, minus likes/EXIF). One new migration,
`003_add_forum_calendar_files_gallery.php`, six tables.
**The real architectural decision**: built as entirely new, dedicated
tables rather than adding a nullable `org_id` onto the existing public
`forum_topics`/`calendar_events`/`gallery_photos`/`downloads_files`
tables. Those modules' whole read path assumes public visibility with no
membership check anywhere in already-live, already-verified queries —
retrofitting privacy onto that surface was a much larger regression risk
than a handful of new, cleanly-separated tables where privacy is the
default, not an exception threaded through existing code. Every read on
every one of the four new content types is gated in the controller on
`OrgSpaceService::isMember()` (the exact check the roster already uses)
or the existing `org_spaces.moderate` scoped capability — no new
capabilities needed, since "any org member can post" doesn't need the
public forum's separate create_topic/reply capability split (spam control
across a small, admin-curated roster isn't the public signup forum's
problem). Reused, not reinvented: `BBCodeParser`, `FileUploadValidator`
(5th consumer now), `ImageThumbnailer`, `Response::streamFile()`/`file()`,
and the `notify` hook (org forum replies notify the topic author, same
self-reply-skip rule as everywhere else) — zero parallel logic for
anything that already had a shared service.
**Verified end-to-end, all four sub-features, real accounts not just code
review**: a genuine non-member (a throwaway account on no org's roster)
got a hard 403 on every single read AND write endpoint across all four
features — forum index/topic, calendar index/event, files index/download,
gallery index/album/image/thumbnail — not just the listing pages, the raw
image-streaming endpoints too, so a guessed/shared photo URL can't leak
content either. A guest got redirected to login on all of them. A real
member posted a BBCode topic (rendered correctly, not literal brackets),
replied, and the reply correctly notified the topic author (and correctly
did NOT notify on a self-reply); a non-officer member saw zero moderation
controls and got 403 attempting lock/delete directly via POST; an officer
locked a topic (member reply then correctly blocked with 403), unlocked
it, deleted a post, then deleted the whole topic (404 after). Calendar:
member created an event via a real `datetime-local` form submission,
correctly parsed/stored; non-member blocked from the event detail page
directly; officer-only deletion enforced the same way. Files: a real PDF
uploaded and downloaded back byte-identical with correct
`Content-Disposition`, download counter incremented on each real request;
a `.php` renamed `.pdf` was rejected by the MIME sniff (same test every
upload path in this app has now passed); officer-only deletion enforced.
Gallery: a real bulk JPEG+PNG upload in one submission produced real
GD-generated 300px-wide JPEG thumbnails with correct dimensions and no
`Content-Disposition` (inline display); full images served byte-identical;
officer deleted one photo (survived in DB, soft-deleted) then the whole
album (same); a second org's member correctly got 403 attempting to
reach the first org's content via URL, including by guessing a valid
photo/topic ID under the wrong org slug. Confirmed disabling `org_spaces`
404s every one of these new routes and re-enabling restores them with all
data intact — including a real forum topic and a real uploaded file
surviving the full disable/enable cycle untouched. Confirmed the
pre-existing roster/officer/announcements functionality was completely
unaffected by any of this.
**Deliberately not built** (matching every deferred item's discipline
elsewhere in this app — v1.1 additions if a real chapter asks, not gaps):
forum sub-boards/polls/likes (the org forum is intentionally flatter than
the site-wide one), calendar recurrence/RSVP, file version history,
gallery likes/EXIF/bulk-batch-partial-failure-isolation. Committees /
committee pages and a richer org homepage remain **not confirmed** as
launch-required — still open questions, not assumed in scope. Per-org
dues remains an open question, not a commitment.

### Media & commerce parity (extends Stages 5/6 — not started)

- Downloads: ~~ratings~~ ✅ shipped 2026-07-17 as part of the shared
  Content Ratings system (see that entry above); mirrors ✅ and virus
  scanning ✅ SHIPPED 2026-07-17. Full writeup above.
- Video: playlists ✅ — SHIPPED 2026-07-17. Full writeup above.
- Gallery photo captions / video titles+descriptions in Site Search ✅ —
  SHIPPED 2026-07-17 (the v1.1 addition noted in the Site Search entry
  above). Full writeup above.
- Digital downloads tied to commerce/paywall (distinct from the free
  `downloads` module — vision notes list this separately from file
  repository) ✅ — SHIPPED 2026-07-19, payment gateway confirmed as Cash
  App (the same manual-confirmation gateway `dues`/`donations` already
  use). Full writeup below, "Commerce/Paywall Downloads" section.
- RSS "auto articles" ✅ — SHIPPED 2026-07-17. Full writeup above.

**Media & commerce parity: 5 of 5 items shipped.**

### Admin system parity (fits Stage 10 — mostly not started)

- ~~Dashboard widgets.~~ ✅ shipped 2026-07-17 as the Admin Dashboard
  Redesign (panel grid, dedicated chrome — see that entry above).
- Maintenance mode ✅ — SHIPPED 2026-07-17. Full writeup above.
- System health page ✅ — SHIPPED 2026-07-17. Full writeup above.
- Log viewer ✅ — SHIPPED 2026-07-17. Full writeup above.
- Update checker ✅ — SHIPPED 2026-07-17. Full writeup above.
- Backup manager ✅ — SHIPPED 2026-07-17. Full writeup above.
- Permissions audit view ✅ — SHIPPED 2026-07-17. Full writeup above.
- Module dependency viewer ✅ — SHIPPED 2026-07-17. Full writeup above.
- **Admin scratchpad / admin-to-admin notes** ✅ — SHIPPED 2026-07-18
  (confirmed wanted at the 2026-07-17 night-before check-in, overriding
  the earlier "V2, maybe" tag). Full writeup above.

## Stage 7 — Advertising & Revenue ✅ (split across one long session — 2026-07-18)

**Deliverables**: banner manager (zones, scheduling, rotation, stats) and ad
tracker (impressions/clicks/CTR/campaigns/advertisers) ✅ SHIPPED
2026-07-18 — see that entry above (one combined `ads` module, built on the
existing `BlockRegistry` placement system), affiliate links ✅ SHIPPED
2026-07-18 — see that entry above (admin-curated partner links, its
own small module distinct from both Link Directory and the ad system),
sponsor blocks ✅ SHIPPED 2026-07-18 — see that entry above (an
always-on logo strip, deliberately the opposite shape from ads.banner
rather than a third advertiser/campaign stack), donation goals ✅ SHIPPED
2026-07-18 — see that entry above (a visible target/progress framing on
top of Stage 6b's `donations` campaigns — not a new payment mechanism),
premium memberships ✅ SHIPPED
2026-07-18 — see that entry above (a paid membership tier gated the same
way any other capability is, layered onto Stage 6a's `dues` plans rather
than a separate system).
**Usable outcome**: a club can run sponsor banners, see performance stats,
and offer a paid membership tier or goal-driven donation campaigns.
**Doc audit note (2026-07-14)**: donation goals and premium memberships were
in the original vision notes for this stage and had dropped out of this
roadmap entry silently — added back here, not new scope.

## Stage 8 — Customization ✅ (SHIPPED 2026-07-18, ten slices in one session)

**Deliverables**: visual theme editor, child themes, color/typography manager,
drag-drop block placement anywhere (built on `strat_block_placements`, see
`theme-block-system.md`), menu builder, dark mode. Theme *installation*
(upload a theme, activate it, remove it) shipped 2026-07-17 as part of
"Addons & Themes: User-Uploadable Plugin System" above — what's still open
here is authoring/editing tools (a visual editor, not just install/activate),
not the underlying install mechanism itself. **First slice shipped
2026-07-18** — see "Stage 8, First Slice: Front Page & Block Library Core"
above: the front page is now block-composed (5 real blocks, real default
placements) instead of a hardcoded stub. **Second slice shipped same
day** — see "Stage 8, Second Slice: Remaining Block Library" above: the
other 11 blocks from the confirmed v1 list, closing out the block library
entirely. **Third slice shipped same day** — see "Stage 8, Third Slice:
Admin Block Placement Manager" above: a real `/admin/blocks` UI (create/
reorder/enable-disable/delete across every region) replacing the
migration-seed-only state every block in this app has had until now.
**Fourth slice shipped same day** — see "Stage 8, Fourth Slice: Site
Header/Masthead Banner" above: the site header now shows the real
Stratum brand art (centered, blended background) with a genuine admin
upload/revert control — closes the user's one explicit design request
for the whole project. **Fifth slice shipped same day** — see "Stage 8,
Fifth Slice: Top Nav Redesign" above: icon-based primary nav + a "More"
overflow dropdown (fixing a real 21-item flat-list problem, not just a
cosmetic pass), a new `topbar_actions` block region, search/notifications
restyled to match, a user profile dropdown, and a `messages` module
placeholder reserving the header spot for real private messaging (built
out for real 2026-07-19 — see "Private Messaging" below). **Sixth slice shipped same day** — see
"Stage 8, Sixth Slice: WordPress-Style Block Management" above: real
per-block settings forms (`ConfigurableBlock`/`BlockConfigForm`) replacing
raw JSON, and genuine drag-and-drop placement from a palette into
regions, replacing the third slice's dropdown-and-submit flow.
**Seventh slice shipped same day** — see "Stage 8, Seventh Slice: Child
Themes" above: a theme can now declare a `parent` and inherit its base
layout (fixed a real gap — `renderLayout()` had no parent fallback at
all before this), plus a one-click "Create a child theme" admin flow
that scaffolds a real, independent, activatable theme with zero files
to hand-author. **Eighth slice shipped same day** — see "Stage 8, Eighth
Slice: Color & Typography Manager" above: an admin-editable accent color
(one CSS custom property, `--strat-accent`) and a curated body-font
picker (`FontStacks`, 4 real stacks) on `/admin/settings`, scoped
honestly to the default theme's own chrome rather than a full re-skinning
engine. **Ninth slice shipped same day** — see "Stage 8, Ninth Slice:
Menu Builder" above: `/admin/menu` replaces the hardcoded primary-nav/
"More"-dropdown split with real admin control (reorder, rename, hide,
add custom links) via a self-reconciling `nav_menu_items` overlay that
still auto-adopts newly-enabled modules' nav items with zero admin
action, exactly like before this feature existed. **Tenth and final
slice shipped same day** — see "Stage 8, Tenth Slice: Dark Mode" above:
three admin-chosen modes (Off/On/Auto), Auto adding a real per-visitor
toggle with `localStorage` persistence that correctly overrides the
visitor's OS preference, verified to reach real content pages (not just
the theme's own chrome) since module templates never hardcoded their
own colors to begin with. **Stage 8 is now fully shipped** — all six
original deliverables done.

**Block library & default front page design (2026-07-18, planning only,
nothing built yet)**: worked through both a giant combined "7 clubs'
wishlist" of ~250 possible blocks and two real reference mockups (admin
dashboard + public homepage) before starting the actual Stage 8 build,
to land on a small, real v1 list instead of either extreme. Recording the
settled scope here.

- **Governing idea**: give clubs a genuinely good set of real, data-backed
  blocks plus two generic escape hatches (HTML block, Text/Markdown
  block) — "enough blocks to cover the hard-coded features, so an admin
  can build their front page and feel in control, without us having to
  build every conceivable widget" (the user's framing, and the right one).
  One parameterized block (config-driven data source + display mode)
  beats a dozen near-identical block types for the same underlying data —
  see the front-page hero design below for the concrete payoff.
- **Front page is currently a hardcoded stub** (`public/index.php`'s `/`
  route literally returns `<h1>Welcome to Stratum</h1>` and nothing else)
  — this is the actual thing being replaced. Full layout design (hero +
  side list + 3-column grid, five new region keys) is in
  `docs/theme-block-system.md`'s Layout section — read that instead of
  re-deriving it.
- **Confirmed v1 block list** (~19 blocks, landed here after starting
  from ~250 and cutting to what's real):
  - **Content**: Latest Content (parameterized — source
    category/tag/author, sort recent/popular, display
    hero-slider/compact-list/card-grid, limit; this single block covers
    the hero slider, the front-page "Latest Articles" side list, *and*
    general "latest from category" placements anywhere else), Tag Cloud
    (`TagService::popularTags()` already exists — free).
  - **Community**: Recent Forum Posts, Members Online (Presence),
    Newest Members, Latest Comments, Activity Feed.
  - **Org/Club**: Upcoming Events (site calendar), Featured
    Club/Club Directory (org_spaces).
  - **Media**: Downloads List, Gallery Highlights, Recent Videos.
  - **Site pulse**: Site Statistics — **real data only** (comment count,
    new-user count, member count, all queryable today), explicitly *not*
    page views or visitor counts. Confirmed with the user 2026-07-18 after
    both reference mockups showed a Page Views/Visitors stat tile: no new
    page-view tracking infrastructure gets built for this — it would be
    new scope (nothing today logs a historical per-page visit count,
    Presence only tracks who's online *right now*) and would contradict
    the principle the Admin Dashboard Redesign already committed to
    ("draw on real existing services, don't invent data Stratum doesn't
    track"). If real analytics is ever wanted, that's its own future
    decision, not something to back into via a block's stat tile.
  - **Welcome/Join CTA**: small configurable headline + text + button —
    close enough to the generic Text block that it may not need its own
    class, decide at build time.
  - **Admin-only**: Quick Links — shortcut panel (Add News, Add Event,
    Media Manager, Settings, etc.), gated the same way the nav bar
    already gates the "Admin" link (`isAdmin` check), no new visibility
    mechanism needed.
  - **Revenue (already block-shaped, free reuse)**: `ads.banner`,
    `sponsors.strip` — both shipped this session, slot straight into the
    3-column area or anywhere else.
  - **Escape hatches**: HTML block (admin-only capability — raw markup,
    no PHP/SQL execution, matching the security stance below), Text/
    Markdown block (safe for any editor-level user).
  - **Newsletter** ✅ — SHIPPED 2026-07-19 as `newsletter.current_issue`,
    registered via the addon's own `Module.php` exactly like
    `ads`/`sponsors` do. See "Newsletter / Mini-Mag Addon" below.
  - **Chat (Stage 9, not built yet)**: Available Chat Rooms block —
    ships with the built-in `chat` module once Stage 9 is built, public
    rooms only. Full note under Stage 9's chat design section below.
- **Deliberately cut from the original ~250-item proposal, and why**:
  - **Custom PHP / SQL Query blocks** — cut for security, not just scope.
    Arbitrary PHP/SQL execution via a web UI is a straight path to RCE if
    an admin account is ever compromised — a materially different risk
    than "feature we don't need."
  - **AI blocks** (translation, image generation, summaries), **Giphy-
    style third-party embeds**, **Weather/Currency/Unit-converter blocks**
    — all pull in an external API dependency and/or ongoing cost,
    contradicting "self-hosted, nothing but PHP/MySQL required." Same
    reasoning already applied to cut Giphy from the chat slash-command
    list.
  - **Full commerce blocks** (cart, checkout, coupons, gift cards) — goes
    past Stratum's deliberate "no payment processing" boundary that
    dues/donations/premium-memberships/ads have all kept (generate a
    payment link, admin confirms — never handle a card).
  - **Heavy analytics blocks** (heatmaps, conversion funnels, country
    maps, device/browser breakdowns) — same "don't invent untracked data"
    reasoning as the Site Statistics decision above, just at a much
    bigger scope.
  - **Dashboard/Security/SEO "blocks"** (Site Statistics-as-admin-widget,
    Security Log, Error Log, Backup Status, SEO Score, etc.) — these
    aren't page blocks at all, they're admin-dashboard material, and
    mostly already shipped as dedicated pages (System Health, Log Viewer,
    Backup Manager, Update Checker, Built-in SEO). Don't rebuild as a
    second UI; if anything, surface a summary on the existing `/admin`
    dashboard panel grid.
- **The #1 constraint carried over from the chat design work, restated by
  the user 2026-07-18**: this all has to run smoothly on shared hosting —
  a real number of the 8 migrating clubs can't afford anything better.
  Nothing in this block list requires anything beyond what's already
  running (PHP/MySQL, no external services, no background workers).
- **Default block scoping policy, confirmed 2026-07-18 (closing design
  decision)**: general content/community blocks default to
  `page_scope = 'front_page_only'` — that column already exists and
  already distinguishes `site_wide` from `front_page_only`/a specific
  route, so this needs no new mechanism, just a seeding choice. The
  concern raised: forum threads, article pages, etc. should stay focused
  on their own content, not carry the same sidebar widget stack as the
  homepage. `ads.banner`/`sponsors.strip` are the deliberate exception —
  they stay `site_wide`, unchanged, since constant visibility is the
  actual point of a revenue placement, not clutter in the same sense.
  This is a *default*, not a hard restriction: once Stage 8's drag-drop
  placement UI exists, an admin can still place any block on a specific
  non-front-page route if they choose to — nothing ships that way out of
  the box.

**Site header/masthead** (noted 2026-07-13, not yet implemented): replace the
current flat-color header bar in `themes/default/templates/layout.php` with
an admin-uploadable banner image — the existing brand art at
`Stratum/de1f5f5a-5a3c-4994-9ea9-543de047cc43.png` (hexagon "S" mark, blue/
silver, "Built for communities. Designed to last.") is the reference/default.
The header background should blend into the banner's own blue tones (rather
than the current hard-edged `#12141c` bar) so the image reads as part of the
header, not a rectangle dropped on top of it. The banner image itself is
horizontally centered in the header (not left-aligned/stretched), with the
blended background filling the space on either side. Nav (link bar) stays in its
current position, rendered *below* the header image region. Site admins get
an upload/replace control in theme settings to swap in their own header art
— same "admin can turn this off/customize it" spirit as everything else in
Stratum, not a fixed hard-coded banner. Natural fit for this stage's visual
theme editor / color-typography-manager work, not a separate module.
**Confirmed 2026-07-17: this is the user's one specific, explicit design
request for the entire project** — not one of several equally-weighted
layout musings. Doesn't need to be pixel-exact (it's a mockup for
direction, not a spec to trace), but the underlying structure below is a
real requirement to get right, not optional inspiration.

**Correction (2026-07-16) — this isn't a new idea, it's the original spec,
confirmed by re-checking the vision notes**: `these are features clubs.txt`
(lines 37-38) already specifies this precisely: "feature section on left
slider picks up from articles, right side square small static articles
chosen by category, below that 3 columns for blocks..main, left and right
side bars." A 2026-07-16 layout reference image turned out to be a fairly
faithful visualization of this exact original spec, not a new proposal —
worth citing the spec directly here instead of the looser "hero/carousel"
paraphrase this note originally had. The three concrete, spec-backed
pieces:
1. **Left: a slider auto-populated from articles** — specifically
   article-driven (not a generic promotional banner carousel, not
   arbitrary content).
2. **Right: small square-thumbnail article cards, "chosen by category"**
   — i.e. admin/category-curated selection, not simply "latest articles."
   An implementation detail worth nailing down precisely when this is
   built (which category, how many, curated vs. a category filter) rather
   than defaulting to "just show recent ones."
3. **Below both: a 3-column block grid** (main/left/right sidebars) — see
   the "Front page block-grid direction" note directly below, which is the
   same spec line continuing.

**Front page block-grid direction** (spec line above, continued;
reference image confirmed 2026-07-16 — content/labels in the image itself
are still placeholders, not feature decisions): a concrete visualization
of the block/region system that already exists in the backend
(`BlockRegistry`/`strat_block_placements`, five regions already defined
including `sidebar_left`/`sidebar_right`/`front_feature`) but sits unused
on the real front page today — the sidebar regions currently render
empty. The reference showed a 3-column grid of independent block panels
below the feature section (not just two thin sidebars flanking a single
main column), each panel following the same structural convention: a bold
title plus a "View All"-style action link, regardless of what content
type the panel holds. Worth building toward once Stage 8's block-
placement admin UI exists (today blocks are only ever seeded directly via
migration — ticker, search box, notification bell — there's still no
admin screen to place one, per `theme-block-system.md`).
**Also surfaced, a real current problem, not hypothetical**: the site's
primary nav is module-driven and already sits around 15 top-level items
(Articles, Calendar, Forum, Gallery, Wiki, Bookmarks, Activity, etc.) —
observed wrapping across two lines on the real running site well before
reaching a fully-loaded module count. The reference's "primary items +
More overflow dropdown" pattern is a reasonable direction for the
menu-builder work already scoped in this stage.

**Admin dashboard direction — "modernized e107"** (noted 2026-07-16;
**core structural direction shipped 2026-07-17 as the Admin Dashboard
Redesign** — dedicated top-bar/sidebar chrome, grouped nav, bottom-pinned
user card, real-data panel grid, see that entry above. Visual polish below
— soft elevation, spacing scale, accent-color restraint — landed as part
of that pass too; the "keep collecting references" / right-hand status
rail note at the end of this block is what's still open, not the whole
section). Confirmed direction: keep e107's
actual admin-home philosophy — a grid of small boxed panels giving
at-a-glance operational awareness, all visible on one screen, no digging
— rather than the currently-fashionable SaaS-analytics-dashboard look
(big glossy gradient KPI hero cards). Modernize purely through visual
execution: soft elevation/shadow instead of hard 1px borders, consistent
spacing scale, a real typographic hierarchy, one accent color used
sparingly (active nav state, status dots, badge counts) instead of
default-blue-links everywhere.
**Structural patterns confirmed across two reference images so far**: a
dedicated top-bar + collapsible-left-sidebar admin chrome, distinct from
the public site's shell — **shipped** (`core/admin/templates/admin-layout.php`,
no longer reusing `layout.php`); a bottom-pinned user-identity card in the
sidebar — **shipped**; a narrow right-hand rail for small glanceable status
widgets — **not yet built** (the shipped pass folded status info into the
main panel grid instead — "System Status"/"Needs Attention" panels — rather
than a separate rail; revisit if the grid gets crowded); and — the one that
solves a real, concrete problem — **sidebar nav grouped under section
headers** rather than one flat list, needed because Stratum's admin nav is
module-driven and already past 20 entries (each enabled module contributes
its own `admin_nav` item) — **shipped**, real taxonomy (Content / Community
/ Commerce / Site Tools / System) built against Stratum's actual modules,
not a generic mockup's category names.
Panels shipped draw on real existing services (Activity Feed, Moderation
queue, Trash, Presence, `ModuleManager::list()`) rather than inventing data
Stratum doesn't track (e.g. pageview analytics), per the general principle
noted here. Collapsible-sidebar interactivity (the toggle itself, not just
the layout) and the right-hand status rail remain open follow-ups, not
blockers.

**Usable outcome**: an admin reskins the site and rearranges layout without
touching code.

## Stage 9 — Realtime & Modern Extras ✅ (SHIPPED 2026-07-19 — chat, private messaging, live notifications, and PWA support all closed out in one session)

**Deliverables**: chatroom (the one stage with no direct e107/SMF/ocPortal
analog, see `architecture.md` — design settled 2026-07-18, full notes
below). **First slice shipped 2026-07-19** — see "Stage 9, First Slice:
Chat Rooms" below: rooms (admin-permanent public/private, member-created
always-public and self-deleting when empty), AJAX-polling messaging,
`/me` actions, an admin moderation screen, and the "Available Chat
Rooms" block — deliberately simplified from the original 2026-07-18
design notes (no operators/bans/reactions/uploads/Markdown/SSE-WebSocket
transport in this pass, see that entry's full "deliberately not built"
list). Live notifications ✅ and PWA support ✅ — both SHIPPED 2026-07-19,
see "Live Notifications" and "PWA Support" below. Private messaging
(member-to-member — referenced as "Stage 9, unbuilt" back in Stage 6c's
classifieds writeup but never actually added to this deliverable list
until this audit pass — same gap, now closed; **deliberately decoupled
from the chatroom build**, see below, not a shared data model) ✅ SHIPPED
2026-07-19 — see "Private Messaging" below. **A `messages` module already
existed as
of 2026-07-18's top-nav redesign** (see "Stage 8, Fifth Slice" above) —
`messages.icon` block in the new `topbar_actions` region and a `GET
/messages` placeholder route/template, both intentionally minimal (no
table, no real data). Building the real feature means adding
conversations/messages tables and services to this same module and
swapping the placeholder page/icon for the real UI — the header spot and
routing are already reserved, no header/layout changes needed when this
gets picked up.
**Usable outcome**: members can chat in real time, DM each other, and get
push notifications.

**Chat design notes (2026-07-18, planning only — nothing built yet)**:
worked through a detailed architecture proposal before starting Stage 8;
recording the settled direction here so the actual build doesn't have to
re-derive it.

- **Philosophy**: IRC-inspired concepts (channels/rooms, operators, voice,
  topics, slash commands, invites, bans), not the IRC protocol itself, with
  a modern web interface layered on top (reactions, Markdown, image
  uploads, mobile-friendly, typing indicators). Same "modernize the old-web
  concept, don't reinvent it" approach already used for forums, clubs, and
  link directories.
- **Transport, tiered by hosting capability — this is the load-bearing
  decision, not a footnote**: AJAX polling as the universal baseline (works
  on any shared host with nothing but PHP/MySQL, matching the same
  shared-hosting-first posture behind `cron.daily`-instead-of-a-queue and
  file-based page caching instead of Redis), Server-Sent Events as an
  automatic upgrade where the host allows long-running PHP, WebSockets
  (Swoole/Ratchet) as an opt-in upgrade for VPS/dedicated hosts. The
  previous "WebSocket-based" framing in this doc and `architecture.md` was
  wrong and has been corrected — a WebSocket-only chat would silently not
  work for a meaningful share of the 8 migrating clubs.
- **Reuse, not new architecture, for most of it**:
  - Content-attached chat (article discussion, download support chat,
    gallery album chat, calendar event chat) reuses the same generic
    polymorphic `*able_type`/`*able_id` pattern `comments`/`tags`/
    `bookmarks` already established — a chat room gets an optional
    `chattable_type`/`chattable_id` pair, no new per-content-type wiring.
  - Room roles (operator `@`, voice `+`, moderator) reuse
    `PermissionEngine`'s scoped-role mechanism already established three
    times over (org_spaces officers, forum per-board moderators, premium
    memberships' auto-provisioned roles) — a chat room operator is a
    fourth instance of the same pattern, not a bespoke permissions table.
    Drop the originally-proposed `chat_room_permissions`/`chat_room_roles`
    tables in favor of this.
  - Message reactions and file uploads get their own small tables
    (`chat_message_reactions`, `chat_uploads`) — neither maps cleanly onto
    an existing mechanism (ratings' 1-5 scale isn't emoji reactions), so
    no forced reuse there.
- **Club chat is a separate case from generic/content-attached chat**:
  org_spaces (clubs) already has its own *dedicated* per-club sub-tables
  for forum/calendar/gallery/files (not the polymorphic pattern) — so each
  club auto-gets one permanent chat room the same way it already
  auto-gets a forum/calendar/gallery/files section, owned by the club's
  existing officer scoped role. This is a fifth org_spaces sub-resource,
  not a row in the generic `chat_rooms` table.
- **Room lifecycle (confirmed with the user 2026-07-18)**: admin-created
  rooms (site-wide chat, plus club rooms which are effectively
  officer-owned) are permanent — they persist until explicitly deleted,
  never auto-expire. Member-created rooms are ephemeral — they
  auto-delete once the room is empty (last member leaves), most likely
  checked via a `cron.daily`-style sweep rather than live per-request,
  matching the same "revisit once a day" shape premium membership
  revocation and scheduled publishing already use, rather than inventing
  a new live-cleanup mechanism.
- **Private messaging stays a separate feature**: decoupled from chat
  rooms on purpose (confirmed with the user 2026-07-18) — a DM
  conversation is a different data shape (two-party thread, not a
  multi-user channel) and was already tracked as its own Stage 9
  deliverable before this discussion. Build chat rooms first; PMs reuse
  the same notification hooks (mention/DM alerts go through the existing
  `notifications` module, not a new alerting path) as a later, separate
  slice.
- **Schema sketch** (not finalized to migration form yet): `chat_rooms`
  (name, topic, `chattable_type`/`chattable_id` NULL for standalone rooms,
  `owner_scope` distinguishing admin-created/permanent vs
  member-created/ephemeral, `created_at`), `chat_messages`, `chat_uploads`,
  `chat_message_reactions`, `chat_room_bans`, `chat_room_invites` — roles
  and permissions deliberately *not* in this list, per the reuse note
  above.
- **The #1 constraint, stated explicitly by the user (2026-07-18): chat
  must run smoothly on cheap shared hosting, full stop** — a meaningful
  share of the 8 migrating clubs can't afford anything better, and a
  feature that only works well on a VPS isn't acceptable as the default
  experience. Every decision below is downstream of this, not a
  standalone nice-to-have trim.
- **Trimmed from the original proposal, confirmed with the user
  2026-07-18** (each of these adds real weight — DB rows, background
  processes, or outside dependencies — disproportionate to what a club
  chat room actually needs):
  - **No third-party integrations** (e.g. `/giphy`) — pulls in an
    external API dependency, contradicts "self-hosted, nothing but
    PHP/MySQL required."
  - **No read receipts on chat rooms** — per-message-per-user read state
    is real DB weight for a public-room feature that mostly matters in
    1:1 DMs; if it's ever built, it belongs to the separate private
    messaging slice, not chat rooms.
  - **No chat-owned push notifications** — push delivery (service
    workers/VAPID) is genuinely PWA-stage infrastructure. Chat just
    feeds the existing `notifications` module like everything else does;
    it doesn't own a separate delivery mechanism.
  - **No dedicated message/attachment search** — site search already
    exists as its own module; extending it to chat content is a fine
    future addition but not a v1 requirement, and building a second
    search index just for chat would be pure duplication.
  - **No configurable countdown expiry UI** (1hr/1day/1week/never) —
    already simplified to "member rooms expire when empty," which
    covers the real use case (an abandoned room cleans itself up)
    without a scheduler UI.
  - **Typing indicators — undecided, revisit at build time.** Cheap if
    chat is already polling for new messages (indicator state can ride
    the same request), but adds a bit of chatter under an AJAX-polling
    transport specifically. Not worth deciding now; the actual polling
    interval chosen at build time will make this an easy call either way.
  - Everything else from the original proposal stands: rooms, topics,
    operators/voice/mute/ban/invite, the non-integration slash commands
    (`/me`, `/join`, `/topic`, `/invite`, `/ban`, `/mute`), emoji
    reactions, Markdown, file uploads, word filters, and the admin
    dashboard stats panel.
- **"Available Chat Rooms" block, added 2026-07-18** (closing item of the
  Stage 8 block-library planning pass — see that entry above): a block
  listing joinable rooms (name, topic, member/online count, join link) for
  placement anywhere on the site, not just inside the Chat section itself.
  **Public rooms only** — private/password-protected/invite-only rooms
  stay invisible to this block, matching chat's own visibility rules; a
  block a random visitor might see is not the place to leak room
  existence. Ships via the built-in `chat` module's own
  `registerBlocks()` once Stage 9 is actually built — same "a module
  provides its own block" pattern `ads.banner`/`sponsors.strip` already
  established, not part of the core Stage 8 v1 block-library pass (chat
  is core, not an addon, so this is closer to that precedent than to the
  newsletter's addon-block case).

## Stage 9, First Slice: Chat Rooms ✅ (SHIPPED 2026-07-19)

**Why**: first Stage 9 deliverable, and the one item on the roadmap with
no direct e107/SMF/ocPortal precedent. The 2026-07-18 design notes above
were extensive (operators/voice, bans, invites-as-ACL, reactions,
uploads, SSE/WebSocket transport tiers); the user explicitly asked to
simplify for this actual build, confirmed 2026-07-19 through a short
back-and-forth on the one genuinely open design question: **should a
member-created room support privacy at all?**

**The confirmed simplification, and why it's not just a cut corner**: a
member-created room is now *always* public — no invite-as-access-control
system was built. Reasoning worked through with the user: an invite
token/ACL system for member rooms would have meant real infrastructure
(invite tokens, private-membership checks, exclusion logic in the
discovery block) for a feature whose whole point was supposed to be
simple. Making member rooms always-public turns "invite a friend" into
a lightweight **notification nudge** ("Alex invited you to Room X")
rather than an access grant — it still does something real (pulls a
friend's attention to the room) via the existing `notifications` module,
with zero new ACL mechanism. Nobody's actually locked out of a public
room whether invited or not. Admin rooms kept the privacy option, since
those are few, deliberate (an officers-only room), and admin already has
a natural, existing way to manage a membership roster by hand (the same
"admin manages a list directly" shape org_spaces officers already use) —
no invite tokens needed there either.

**The confirmed lifecycle rules**:
- Admin-created rooms are permanent (admin deletes explicitly) and can
  be public or private. Private = an admin-managed membership list, no
  self-serve join at all.
- Member-created rooms are always public, and self-delete the instant
  their last member leaves — **checked synchronously in
  `ChatService::leaveRoom()`, not a `cron.daily` sweep** like the
  original 2026-07-18 notes proposed. Simpler (no scheduler wiring) and
  more honest about *when* "empty" actually happened, not up to a day
  stale.
- Admin has full moderation control over every room, admin- or
  member-created alike — delete, edit name/topic, manage private
  membership.

**Build**: new `chat` module — 3 tables (`chat_rooms`, `chat_room_members`,
`chat_messages`; `chat_room_members` deliberately does double duty as
both the private-room ACL and the live "is this member room empty yet"
signal, not two separate tables), a new `chat.manage` capability
(admin-only — base participation, room creation, and posting need no
capability at all beyond being logged in, matching the "give clubs a
frictionless default, not a feature that looks broken until an admin
flips a switch" reasoning). `ChatService` owns all room/membership/
message logic; `ChatController` (public: list, view, create, join-on-
view, post, poll, leave, invite) and `ChatAdminController` (create
admin room, edit/delete any room, manage private membership) both live
inside the module, following the exact `ForumController`/
`ForumAdminController` split this app already established — not new
core-admin-owned controllers like the Stage 8 block/theme/menu screens,
since chat is a feature module, not a core system concern.

**Transport**: AJAX polling only for this slice (client polls every 4s
for new messages via `GET /chat/rooms/{id}/messages?after={lastId}`,
returns pre-rendered HTML fragments) — the confirmed shared-hosting-first
baseline from the original design notes; SSE/WebSocket upgrade tiers are
future work, not attempted here. Message posting is also AJAX (`fetch`
+ `URLSearchParams`, same convention the Stage 8 block-management
drag-and-drop endpoints established), returning the newly-posted
message's rendered HTML so the client just appends a string rather than
re-implementing the message template in JS.

**The one slash command this simplified v1 supports**: `/me <action>`
(classic IRC action message — "* Alice waves"), parsed and stripped in
`ChatService::postMessage()`, flagged via an `is_action` column. Every
other slash command from the original design notes (`/join`, `/topic`,
`/invite`, `/ban`, `/mute`) is cut, since those imply operator/moderation
roles this simplified pass doesn't build.

**A real bug caught before any live testing, not during it**: the first
draft of `room.php` called `$this->app->templates->render(...)` from
*inside* the template itself, to render each message. That's broken —
`TemplateEngine::capture()`'s render closure is defined inside a
`TemplateEngine` instance method, so `$this` inside any included
template is bound to `TemplateEngine`, not `App` (`TemplateEngine` has
no `$app` property at all — this would have fataled on the very first
page load). Caught by reasoning through `TemplateEngine::capture()`'s
actual closure-binding behavior before testing, not discovered as a
live 500. Fixed by pre-rendering every initial message in the
*controller* (`ChatController::room()`) and passing the already-built
HTML string to the template — the exact same reasoning
`BlockPlacementsController::renderCard()` already established for
"a template can't call back into the render system itself."

**Verification**: full project-wide `php -l` sweep clean, both before
and after the fix above. Migration applied cleanly; confirmed the
`chat.manage` capability auto-registered and granted to admin/founder on
the next real HTTP request (module discovery doesn't fire from
`bin/install.php` alone — the same established gotcha every prior
module/theme/addon pass this project has hit). Live, via curl and the
real browser, logged in as `modtest_admin`/`modtest_member`/a genuine
non-member (`modtest_outsider`): created one public and one private
admin room; a member visiting the public room auto-joined with zero
extra click, while the same member hit a real 403 on the private room
until an admin explicitly added them via the membership-management
form, after which they could view it. **The riskiest behavior in the
whole feature, exercised directly**: a member created their own room
(always public, auto-joined as first member confirmed via direct MySQL
query), left it as the sole member — the room was gone entirely,
confirmed by a follow-up query returning zero rows; recreated the
scenario with two members and confirmed the room correctly *survived*
one of the two leaving. Posted a normal message and a `/me` message and
confirmed both rendered with the exact intended markup; confirmed the
AJAX polling endpoint genuinely returns new messages after a given id
and nothing when there's nothing new. **Tested the actual live-update
experience in the real browser, not just the endpoints**: posted a
message through the real chat input field (fetch-based submit, input
cleared afterward, message appended to the DOM instantly), then posted
a second message via a separate curl session simulating another
tab/user, and confirmed the open browser tab's 4-second poll picked it
up with zero manual reload. Confirmed the invite flow fires a real
`chat_invite` notification (not a membership grant) and that public-
room invites don't apply to private rooms. Confirmed the "Available
Chat Rooms" block renders correctly once actually placed via the Stage 8
block-management system (dropped it onto the front page live, confirmed
it rendered, removed it) — proving the two features integrate cleanly.
Confirmed every negative path: a guest redirected to `/login` on every
mutating action; a non-admin got a real 403 on `/admin/chat`; a forged
CSRF token was rejected; an empty message was rejected; a genuine
non-member (not just someone who happened not to have visited yet) got
403 posting to a room they were never added to; a raw `<script>` payload
in a message body rendered as inert escaped text, not executed. All test
rooms/messages/notifications deleted afterward and confirmed via direct
MySQL query — zero rows remaining in any of the three new tables. Final
lint sweep and dev-server error log both clean throughout.

**Deliberately not built** (cut from the original 2026-07-18 design
notes, confirmed acceptable for this simplified pass): operator/voice
roles, bans, message reactions, file uploads, Markdown formatting, word
filters, an admin dashboard stats panel, typing indicators, SSE/
WebSocket transport upgrades, content-attached chat (article/download/
gallery/event chat rooms), and a dedicated club-chat room per
org_spaces. Private messaging (member-to-member DMs) was tracked as its
own separate Stage 9 deliverable ✅ SHIPPED 2026-07-19, see "Private
Messaging" below — per the original design
notes' explicit "decoupled from chat rooms" decision — unaffected by
this slice.

## Design System Foundation ✅ (SHIPPED 2026-07-19, First Slice)

**Why**: the user compared the live test site against `look.png` (their
concrete target mockup) and correctly diagnosed the gap — every feature
across Stages 1–9 had been built and verified functionally in
isolation, with just enough CSS to make each module's own forms/buttons
usable, but no one had ever built the shared visual layer e107, Composr,
and WordPress all ship as their theme. The result worked but didn't read
as one cohesive product. Full audit (two parallel Explore agents, one
covering all 16 front-page block placements, one covering 8 representative
content-page templates + the admin panel's own `<style>` block) confirmed
this precisely rather than assuming it: zero of 15 front-page block
classes emitted an icon, a badge, or any per-card accent color; the only
color used anywhere was one hardcoded `#2f6fed` hex copy-pasted across
three files (not a CSS variable, so it didn't even respect the admin's
own chosen accent or dark mode); 12 of 16 blocks had no "View All" CTA
at all; and `QuickLinksBlock` rendered nothing for any regular visitor
(gated on `admin.access`, duplicating the admin dashboard's own separate
"Quick Actions" panel) despite `look.png`'s "Quick Links" being a public
feature. The proof this was fixable without a rewrite: `admin-layout.php`
already had a complete, working design system (CSS variables, a real
`.admin-panel` card component, a grid system) — the pattern existed in
this codebase, it was just never extended to the public site.

**`docs/design-system.md`** (new) — the concrete spec, derived directly
from `look.png`, written before any code changed: the color palette
(the existing Stage 8 dark-mode variables, plus eight new fixed
"badge/category" accent variables — `--strat-color-blue` through `-cyan`
— `blue` is literally `var(--strat-accent)`, never a second independent
blue), a typography scale (h1–h6/p/small), a spacing scale, and the full
component list below. Reference this doc for any future template work
rather than re-deriving conventions ad hoc — same role
`docs/theme-block-system.md` already plays for the block system.

**CSS delivery restructured, not just relocated**: `layout.php`'s
`<style>` block (~250 lines, previously fully re-sent/re-parsed on every
navigation, uncacheable since it lived inline in the HTML) is now
`public/assets/css/theme.css` — a real, versioned, cacheable static
file (`<link rel="stylesheet" href="/assets/css/theme.css?v=1">`).
`layout.php` itself keeps only a 2-line inline `<style>` block for the
values that are genuinely per-install-dynamic: `--strat-accent` and a
new `--strat-font` variable (font-family is now a CSS var too, not
interpolated directly into a `body{}` rule). **The dark-mode mechanism
was genuinely simplified in the process**, not just moved: both the
light and dark palettes are fixed design constants (never admin-set
values), so they moved to static CSS entirely — `layout.php`'s only job
now is choosing which `<html>` attributes to emit server-side
(`data-theme="dark"` for "on" mode — zero JS at all; `data-dark-mode="auto"`
for "auto" mode, gating the OS-preference `@media` query in `theme.css`
so "off" mode can never be accidentally pulled into dark by a visitor's
OS setting). This replaces the old approach of looping a PHP array into
inline `:root{}`/`@media{}` blocks on every request. Verified all three
modes end-to-end after the rewrite, including the specific safety
property the redesign had to preserve: with the browser's OS preference
set to dark, "off" mode still rendered `rgb(244,245,247)` (light) —
confirmed via a real computed-style check, not just reasoning about the
selector logic.

**New `CardBlock` interface** (`core/services/CardBlock.php`, optional,
alongside the existing `ConfigurableBlock`) — `cardTitle()`/`cardIcon()`/
`cardAccent()`/`viewAllUrl()`. `BlockRegistry::renderRegion()` (new
private `wrapCard()`) renders the icon-badge header and CTA footer
itself for any block implementing it, when card-wrapped — one shared
rendering path, not markup hand-copied across 14 block classes, the
same reasoning `ConfigurableBlock`/`block-placement-card.php` already
established for settings forms and drag-and-drop cards respectively. A
block that doesn't implement it still gets the plain `.strat-block-card`
shell it always had; the two hero-slider placements (never card-wrapped)
are unaffected either way.

**Applied to all 14 card-eligible front-page blocks** (`WelcomeCtaBlock`,
`ActivityFeedBlock`, `RecentTopicsBlock`, `RecentCommentsBlock`,
`RecentVideosBlock`, `WhosOnlineBlock`, `UpcomingEventsBlock`,
`RecentDownloadsBlock`, `FeaturedClubBlock`, `TagCloudBlock`,
`NewestMembersBlock`, `GalleryHighlightsBlock`, `StatsBlock`, and the
rebuilt `QuickLinksBlock`) — each given a title/icon/accent matched to
`look.png`'s actual cards (Recent Activity/orange, Upcoming Events/purple,
Downloads/orange, Featured Chapter/gold, Online Users/green, Community
Stats/teal, Recent Forum Posts/teal, Latest Videos/red, etc.), and a
`viewAllUrl()` wherever a real destination route exists (9 of 14 now have
one — the other 5, like the stats summary, genuinely have nothing to
link to). `WelcomeCtaBlock` had its own redundant inline `<h3>` removed
in favor of the new shared header (its admin-configured headline now
*is* `cardTitle()`, not a separate heading). Every hardcoded gray hex
touched in this pass (`#eee`/`#999`/`#666` borders and muted text) was
swapped for `var(--strat-card-border)`/`var(--strat-muted-text)` while
already in each file, so these cards are correctly dark-mode-aware too,
not just newly iconed.

**`QuickLinksBlock` rebuilt, not just re-skinned** — the old version was
a vertical list of 5 hardcoded admin-only nav shortcuts, invisible to
every regular visitor; it duplicated the admin dashboard's own separate,
already-built "Quick Actions" panel, a real scope mistake from when it
was first built. Now: a public 2×2 colored-icon-tile grid
(`.strat-quick-link-grid`/`.strat-quick-link-tile`), four tiles each
admin-picked via a `<select>` from a small curated destination map
(Articles/Forum/Downloads/Calendar/Gallery/Wiki/Chat/Classifieds — icon
and accent color are a fixed, known property of each destination, not
free-text config, avoiding a 16-field form for four tiles), defaulting
to Articles/Forum/Downloads/Calendar exactly like `look.png`.

**Verification**: full project-wide `php -l` sweep clean throughout —
**and a real gap in that sweep caught live, worth remembering**: `php -l`
only checks syntax, not interface completeness, so a class with
`implements CardBlock` added before its four required methods landed
(a genuine, if brief, intermediate state during this edit) triggered a
real PHP fatal error (`RecentVideosBlock` "contains 4 abstract methods
and must therefore..."), invisible to `php -l` and only surfaced by
checking the dev-server error log directly — confirmed stale/transient
(three timestamps clustered in a ~10-second window, zero recurrence
after the fix landed) via a fresh request before considering this done,
not just assumed resolved once the file looked right. All three
dark-mode paths re-verified end-to-end after the CSS restructuring (not
just before it): "off" stays light under a dark OS preference, "on"
renders `rgb(21,23,28)`/`rgb(30,33,40)` with zero JS present, "auto"'s
toggle flips and persists across reload with the explicit choice
correctly overriding the OS preference. Every one of the 14 blocks'
icon badges checked via real `getComputedStyle` (not just confirming the
CSS rule exists) — 18 total badges (14 cards + 4 quick-link tiles) each
resolved to a genuinely distinct `color-mix()`-computed color, proving
the accent system actually works, not just parses. All 9 "View All"
CTAs confirmed present with correct hrefs. `QuickLinksBlock` confirmed
rendering publicly (not just for admins) with the exact default tile
set. Full homepage screenshots taken in both light and dark mode and
visually compared against `look.png` directly — the transformation (icon
badges, card titles, colored quick-link tiles, CTA buttons, dark theme
throughout) is real and dramatic, not incremental. Dev-server error log
and browser console both clean on a final fresh reload.

**Deliberately not built in this slice** (see the plan's own sequencing
— follow-up work, not scope creep dropped silently): the content-page
pass across forum/articles/wiki/calendar/downloads/tags/chat/gallery
(giving every non-front-page content page the same consistent
typography/spacing/`.strat-pill`/`.strat-inline-box` treatment,
documented in `docs/design-system.md` but not implemented yet); the
admin panel's own `<style>` block getting the same CSS-externalization
treatment (lower priority — it's already a working design system, just
inline, unaffected by this pass either way).

**Token vocabulary extended same day, from a CSS scaffold the user had
independently started** (`themes css start/default/css/` — a partial,
disconnected starter directory: real design tokens and a genuinely good
modern reset, but 8 of 10 page-specific files and 2 of 5 layout files
were empty 0-byte stubs, its own `style.css` entry point didn't import
its own `core/`, and referenced two files that don't exist —
`components/modal.css`/`components/search.css` vs. the real
`modals.css`/no search.css at all). Read through all of it before
deciding what to keep, rather than adopting or discarding wholesale.
**Ported in** (renamed to the `--strat-*` convention already wired into
14 PHP block classes, `BlockRegistry`, and `/admin/settings` — not
switched to the scaffold's own `--bg-card`/`--color-primary` naming,
which would have meant a rename pass across already-working code for no
functional gain): radius scale (`--strat-radius-sm/-/-lg`), shadow scale
(`-sm`/full), transition durations, a z-index scale, and a formal
`--strat-space-1` through `-6` spacing scale — plus two genuinely
risk-free reset additions (`img/picture/svg/video/canvas { display:
block; max-width: 100% }`, `table { border-collapse: collapse }`).
`.strat-block-card`, `.strat-icon-badge`, `.strat-card-cta`,
`.strat-quick-link-tile`, and `.strat-header-dropdown-panel` retrofitted
to use the new tokens instead of hardcoded literals in the same pass.
**Deliberately NOT ported**: the scaffold's full modern reset (zeroes
every element's margin, strips list-style/table-border defaults
globally) — genuinely good, but landing it *before* the content-page
pass would strip real spacing from ~40 untouched module templates that
still lean on default browser paragraph/list/table margins, with
nothing yet in place to replace it; revisit alongside that pass, where
compensating rules get verified page-by-page as they're added, not
applied blind. Also not adopted: the scaffold's global `*,*::before,
*::after { transition: ... }` rule — a known anti-pattern (forces the
browser to watch every property on every element for a possible
transition); this codebase uses specific, deliberate `transition:`
declarations on actual interactive elements instead. `theme.css`'s
version bumped to `?v=2` so browsers pick up the change. Verified live:
brace-balance sanity check on the CSS, a real computed-style check
confirming `--strat-shadow-sm` resolves correctly through the cascade on
`.strat-block-card`, zero failed network requests, full `php -l` sweep
and dev-server error log both clean. The original scaffold directory
was left in place, untouched — nothing currently references it, and
removing a file the user created themselves isn't this session's call
to make unprompted.

## Content-Page Pass, First Slice: Forum & Downloads ✅ (SHIPPED 2026-07-19)

**Why**: after the homepage design-system pass, the user looked at
forum/downloads/other sections and reported they "look like stripped
down versions" of the equivalent features in e107/SMF/Composr/
WordPress, and floated scrapping the whole project to rebuild on "a
proper framework." Before agreeing to anything close to that, the right
move was to actually check the claim rather than accept or dismiss it —
a framework swap addresses infrastructure (routing, ORM, migrations),
none of which is what "features feel thin" is actually about, and this
codebase already has a comparable amount of real infrastructure
(module system, permission engine, migrations, block/template system)
to any framework. Read every forum and downloads template/service in
full before responding. **What was actually there**: forum has nested
sub-boards, single-choice polls with live results and vote-changing,
post likes, @mentions, file attachments, moderation (pin/lock/delete),
signatures — genuinely comparable to SMF's own base feature set, not a
stub. Downloads has mirrors, ClamAV virus scanning, file version
history, ratings. **But every one of those pages rendered as a bare
`<h1>` and an unstyled `<table>`** — zero icons, no "last post by X"
preview, a poll UI using raw hex colors instead of the theme's own
accent system, posts as flat divs with no author avatar or visual
separation. Real depth, presented with none of the visual hierarchy
that makes SMF/e107 boards *read* as feature-rich. Presented this
analysis directly to the user (not just agreed or refused) — they
accepted the reasoning and asked to continue rather than scrap.

**Real service-layer work, not just a re-skin**: `ForumService::
listBoards()`/`listTopicsForBoard()` gained a genuine "last post by X"
preview (username + topic title, not just a bare timestamp) — standard
forum-software UX that was legitimately missing, not a presentation
issue. Implemented via a derived-table subquery resolving each board/
topic's single latest post id (`ORDER BY created_at DESC, id DESC LIMIT
1`), then one join on that id — deliberately not a naive join on
`MAX(created_at)`, which could return two rows if two posts share a
timestamp. **A real bug this introduced, caught live**: first version
joined on `p.user_id`, which doesn't exist — `forum_posts`' real author
column is `author_id` (confirmed by checking the migration directly
after the live 500). `php -l` can't catch a wrong column name (valid
PHP, invalid SQL only MySQL rejects at execution) — this was only found
by actually loading `/forum` in the browser and reading the real
PDOException stack trace, not by lint or by assuming the edit was
correct because it looked right.

**New CSS components** (`theme.css`, alongside the front-page ones from
the previous slice): `.strat-pill` (status labels — pinned/locked/scan-
status/tags, finally replacing the tag-pill snippet that had been
copy-pasted inline across 4+ files), `.strat-avatar` (initials circle,
same idea `.topbar-profile-avatar` used but now a real reusable class),
`.strat-list-row`/`.strat-list` (replaces bare `<table>`s for board/
topic/file listings — icon, title+meta, stats, last-activity preview),
`.strat-post`/`.strat-post-author`/`.strat-post-body`/`.strat-post-meta`/
`.strat-post-signature`/`.strat-post-footer` (a real post component with
an avatar sidebar, replacing the flat `background:#f4f5f7` div), and
`.strat-poll`/`.strat-poll-bar-fill` (the poll widget, progress bars now
using `var(--strat-accent)` instead of a hardcoded `#2f6fed` — respects
the admin's chosen accent color and dark mode for the first time).

**Applied to 5 templates**: `forum/index.php` (board list — icon, stats,
last-post preview, sub-board indentation via the existing recursive
render closure, unchanged logic), `forum/board.php` (topic list —
pinned/locked as real pills, last-post-by preview), `forum/topic.php`
(every post now has a real avatar/header/body/signature/footer
structure; the poll widget restyled onto the shared component; tags as
real pills) — all functionality preserved exactly (bookmark, moderation
actions, likes, attachments, reports, reply form, poll voting/
vote-changing all still wired to the same routes, only the markup
changed. `downloads/index.php` (file list rows with scan-status pills)
and `downloads/show.php` (mirrors and version history as list rows,
scan-status/rating pills, the download button using the existing
`.strat-card-cta` style).

**Verification**: full `php -l` sweep clean, `theme.css` bumped to
`?v=3`. Live via curl, logged in as `modtest_admin`/`modtest_member`:
confirmed the last-post preview shows real usernames and topic titles
(not placeholder text) on both the board list and topic list. **Tested
the actual functional flows, not just that pages render** — a real
reply posted through the redesigned reply form appeared correctly
inside a real `.strat-post` card; created a real topic with a 2-option
poll through the actual creation form, confirmed the poll widget
rendered with the new styled progress bars, cast a real vote, and
confirmed the bar width updated to reflect it (100%/0%) using the
theme's actual accent color, not a hardcoded hex. Two soft-deleted test
topics and one legitimately-locked topic were hit by accident during
this pass and correctly returned 404/403 — confirmed via direct MySQL
query these were genuine pre-existing test-data states, not bugs
introduced by this slice. All test replies/topics/polls/votes deleted
afterward and confirmed gone via a follow-up 404. Final lint sweep and
a full page-status sweep (homepage/forum index/board/topic/downloads
index/downloads show, all 200) both clean.

**Deliberately not built in this slice**: the remaining content pages
(wiki/calendar/tags/gallery/chat) — same component vocabulary, next
slice, per the established "verify in slices" sequencing, not attempted
in one pass.

## Content-Page Pass, Second Slice: Wiki, Calendar, Gallery, Tags, Chat ✅ (SHIPPED 2026-07-19)

**Why**: the follow-up to the forum/downloads slice above, closing out
the last of the "every content page still looks like a bare `<h1>` and
an unstyled list" gap. Same reasoning as before — reuse the existing
component vocabulary everywhere it already fits, and only add new
components where the content genuinely needs a different shape (a photo
grid isn't a list of rows; a chat window isn't a forum post).

**Two components finished the vocabulary `docs/design-system.md`
documented but hadn't built yet**: `.strat-inline-box` (a simpler
sibling to `.strat-post` — author/timestamp/body only, no avatar
sidebar or footer actions) finally replaces the `background:#f4f5f7;
border-radius:6px` comment-box snippet that had been copy-pasted inline
across wiki/calendar/gallery comment sections. `.strat-pill` (already
built for forum) now also covers wiki page tags and the site-wide tag
cloud, replacing the last two copies of that same inline tag-pill
snippet the design doc had flagged.

**Two genuinely new components, not in the original doc** — the content
shape didn't fit anything that already existed: `.strat-photo-grid`/
`.strat-photo-tile` (gallery album covers and individual photos read as
a responsive image grid, not full-width list rows — `.strat-list` was
the wrong shape here, not just an unstyled one) and `.strat-chat-window`/
`.strat-chat-message` (the chat message log needed its own scrollable
container and a compact author-inline-with-body bubble shape, distinct
from a forum post's avatar-sidebar layout; `/me` action messages get a
muted-italic variant via one `.is-action` modifier class rather than a
second component).

**Applied to all 5 modules' public templates** (13 files): `wiki/index.php`
(page list → `.strat-list`), `wiki/show.php` (tags → `.strat-pill`,
comments → `.strat-inline-box`), `wiki/history.php` (revision table →
`.strat-list`), `wiki/revision.php` + `form.php` (muted-text cleanup
only); `calendar/index.php` + `calendar.php` (day-grouped event lists →
`.strat-list`), `calendar/event.php` (comments → `.strat-inline-box`,
attendance list → `.strat-list` with avatar initials, RSVP/muted-text
cleanup); `gallery/index.php` (albums → `.strat-photo-grid`, each tile a
cover thumbnail + title + count), `gallery/album.php` (photos →
`.strat-photo-grid`), `gallery/photo.php` (comments →
`.strat-inline-box`); `tags/index.php` (tag cloud → `.strat-pill`),
`tags/show.php` (tagged-item list → `.strat-list` with a type pill);
`chat/index.php` (room list → `.strat-list`, member-room badge as a
pill), `chat/room.php` (message log → `.strat-chat-window`, member list
→ `.strat-list`), `chat/message.php` (the same fragment the AJAX
post/poll endpoints already share → `.strat-chat-message`, both normal
and `/me`-action variants). Every hardcoded `color:#888`/`#666`/`#999`
touched in this pass became `class="strat-muted"` (already defined,
just newly applied consistently) rather than a new one-off style
attribute. All functional logic — comment/RSVP/attendance/like/join/
leave/invite forms, the chat AJAX post-and-poll loop — preserved exactly;
only markup changed.

**Verification**: full `php -l` sweep across all 13 files clean.
`theme.css` bumped to `?v=4`. Live via the real browser preview, logged
in as `modtest_member`: confirmed via real `getComputedStyle` checks
(not just that the classes were present in markup) that every new
component actually renders its CSS — `.strat-list-row`'s border/padding,
`.strat-photo-grid`'s grid-template-columns, `.strat-inline-box`'s
background/border/radius/padding, and `.strat-chat-window`/
`.strat-chat-message`'s border/background/height/flex-gap and the
`.is-action` variant's muted-italic styling all resolved correctly. The
chat check was end-to-end through the real flow, not just static markup:
created a real room, posted a real message and a real `/me` action
through the actual `/chat/rooms/{id}/messages` endpoint, confirmed both
rendered with the correct classes in the AJAX JSON response and in the
room page's initial HTML (same shared `message.php` fragment, so no
drift between the two paths), then left the room to confirm Stage 9's
auto-delete-when-empty behavior still fires correctly. Wiki/calendar/
gallery/tags list pages spot-checked live with real seeded test content.
Dev server stopped clean at the end with no errors in its log.

**Deliberately not built in this slice**: the admin panel's own
`<style>` block getting the same CSS-externalization treatment — still
lower priority, still a working (if inline) design system, unaffected by
either content-page slice.

## Commerce/Paywall Downloads ✅ (SHIPPED 2026-07-19)

**Why**: the last open item in the Vision Parity Backlog's "Media &
commerce parity" section — blocked since 2026-07-17 on a real payment
gateway decision, deliberately not guessed at given the financial/security
stakes. Confirmed 2026-07-19: **Cash App**, the same gateway `dues`/
`donations` already use.

**New module `commerce`, composing over `downloads` rather than modifying
it** — the backlog entry itself calls this out as distinct from the free
`downloads` module, and reusing `DownloadService`'s existing public methods
(`findFile()`, `currentVersion()`, `absolutePath()`) instead of touching
`DownloadsController` at all means **zero regression risk** to the
already-shipped free downloads feature; nothing in `core/modules/downloads/`
changed. An admin picks an *existing* `downloads_files` row to sell — no
separate upload path, no duplicated file storage.

**Exact reuse of the `donations` pending→confirmed pattern**, not a new
design: `commerce_products` (wraps a `downloads_files` row with `price` +
a Cash App `payment_url`) and `commerce_purchases` (`pending`/`confirmed`
status, `amount`/`notes`/`recorded_by`/`confirmed_at` — identical shape to
`donation_contributions`). `CommerceService::recordIntent()`/
`confirmPurchase()` mirror `DonationService::recordIntent()`/
`confirmContribution()` line for line. `hasPurchased()` is a plain
`WHERE user_id=? AND product_id=? AND status='confirmed'` query, modeled
on `DuesService::currentPaymentForPlan()` minus the `expires_at` clause (a
one-time purchase never expires) — **deliberately not built on
`PermissionEngine`'s scoped-role mechanism**, confirmed via research to be
the wrong tool here: scoped roles answer "who can act as a
moderator/officer" (a handful of roles, each with potentially many
members), not "did this one user pay for this one item" (a 1:1 transaction
fact); a role-per-product would mean one throwaway role per purchasable
file for what a single indexed query already answers, and neither
`donations` nor `dues` reach for `PermissionEngine` for their own base
payment lifecycle either.

**The actual gate**: `GET /shop/products/{id}/download` — checks
`hasPurchased()`, 403s if false; if true, resolves the file through
`DownloadService` and streams it, applying the exact same
`scan_status === 'infected'` block the free downloads module already
enforces. This is the only place commerce code touches a downloads file
directly, and it's entirely additive (new route in a new module) — the
free module's own `/downloads/files/{id}/download` route is completely
unaffected and still has zero access control, exactly as before.

**Verification**: full `php -l` sweep clean. Live via curl, full purchase
lifecycle exercised as three real accounts: as `modtest_admin`, created a
real product from an existing download; as `modtest_member`, confirmed
`/shop/products/{id}/download` correctly 403'd *before* purchasing,
clicked "I've paid" (confirmed a real `pending` row), then as admin
confirmed the purchase with a real amount; back as the member, confirmed
the download route now genuinely streamed the actual stored file (fetched
it, verified the returned bytes/filename matched the real
`downloads_versions` row, not a placeholder). Confirmed the gate is
correctly per-user, not global: a third account (`modtest_outsider`, who
never purchased) still got 403 on the same product. All test
products/purchases deleted afterward, confirmed via a follow-up
zero-row query. Dev server stopped clean.

## Newsletter / Mini-Mag Addon ✅ (SHIPPED 2026-07-19)

**Why**: the design was signed off by all 8/8 migrating clubs on
2026-07-18 (on-site multi-page Issue → ordered pages → table of contents
→ Next/Previous nav, explicitly no email) but deliberately never turned
into a concrete build ("attack later," at the time). Confirmed 2026-07-19
to go ahead now. Some clubs call it a "newsletter," others a "mini club
magazine" — the same `Issue` design serves both; the label difference is
solved for free by the existing Stage 8 menu-builder's per-item label
editing, no per-install config needed.

**A real infrastructure gap found and fixed first**: `AddonPackageInstaller
::install()` (`core/services/AddonPackageInstaller.php`) never ran an
uploaded addon's own `migrations/` — `MigrationRunner::runAll()` only ever
scanned `core/modules`, so any addon with its own tables (this one needs
two) would have installed with no schema and fatal on first real use.
Fixed by having `install()` call `MigrationRunner::run($id, ...)` right
after the extracted addon lands in `storage/addons/{id}/`, gated on a
`migrations/` directory actually existing. `AddonPackageInstaller` now
takes a `Database` in its constructor (both call sites in
`ModulesController` updated). Small, additive, benefits every future
addon with its own schema, not scope creep specific to this feature.

**Ships as a real addon, not a core module** — built at
`storage/addons/newsletter/`, the exact directory `ModuleManager` already
scans as its second, "custom" module source. Shaped identically to a
built-in module (`module.json`, `Module.php`, `routes.php`, `services/`,
`controllers/`, `templates/`, `migrations/`), pattern-matched against the
verified `core/starters/addon/` skeleton and the real shipped `ticker`
module (the reference for a `Module.php` that captures `$app` in its
constructor to register a block needing `$app->db`).

**Schema**: `newsletter_issues` (title, unique slug, `is_published`,
`published_at`) and `newsletter_pages` (`issue_id` FK cascade, title,
body, `position`) — `position` drives both the table-of-contents order
and Next/Previous (adjacent `position` within the same issue).
`NewsletterService::movePageUp()`/`movePageDown()` reuse the exact
weight-swap pattern `NavMenuService::moveUp()`/`moveDown()` already
established for the Stage 8 menu builder; `deletePage()` closes the
position gap afterward so numbering always stays contiguous.

**Body content is BBCode**, via the existing `core/services/BBCodeParser.php`
— the same `data-bbcode-toolbar` textarea convention wiki pages and forum
posts already use — not a new content pipeline. Two capabilities,
deliberately separate: `newsletter.edit_issue` (write issues/pages) and
`newsletter.publish` (publish/unpublish) — matches the naming convention
`docs/permission-model.md` already documented as its own example
(`newsletter.edit`), and lets a club give someone edit access without
publish authority if it wants an editorial-review step.

**Block**: `newsletter.current_issue` (`CurrentIssueBlock implements
CardBlock`, icon 📰, accent purple) — the "Current/Latest Issue" block
the Stage 8 block-library plan named back on 2026-07-18, shipped exactly
as scoped: with the addon itself, registered via `Module.php`'s
`registerBlocks()` identically to how `ticker` registers `TickerBlock`.

**Verification, exercising the real installer, not a dev shortcut**:
`php -l` sweep clean across all files. Zipped the finished addon and
uploaded it through the actual `/admin/modules` addon-upload endpoint
(not just placed in `storage/addons/` by hand) — confirmed it landed
there, showed up in `/admin/modules` as "Newsletter (newsletter) — Addon
(custom) — Enabled", and critically, confirmed via direct query that its
migration actually ran and created both tables (proving the
`AddonPackageInstaller` fix above works for real, not just in theory).
As `modtest_admin`: created a real issue, added 3 real pages. As a guest:
confirmed the unpublished issue was a real 404, not just hidden from a
listing. Published it; confirmed the guest-visible list, the
`/newsletter/{slug}` → `/newsletter/{slug}/1` redirect, real `[b]...[/b]`
BBCode rendering to `<strong>`, and Next/Previous correctly present/absent
at every boundary (page 1: Next only, page 2: both, page 3: Previous
only, page 4: 404). TOC sidebar confirmed listing all 3 page titles.
Dropped `newsletter.current_issue` onto the front page via a real
`block_placements` row, confirmed it rendered the published issue's
title with the shared card chrome, removed it. All test
issues/pages/placements deleted afterward — issue deletion's `ON DELETE
CASCADE` confirmed via a follow-up query showing zero rows in both
tables. Dev server stopped clean.

## Private Messaging ✅ (SHIPPED 2026-07-19)

**Why**: the last real user-facing feature gap in Stage 9. Tracked as its
own deliverable since 2026-07-18, deliberately decoupled from chat rooms
(a DM is a two-party thread, a different data shape from a multi-user
channel) and explicitly deferred until chat shipped first — chat shipped
earlier the same day this was built. The `messages` module has existed
since the Stage 8 top-nav redesign purely as a placeholder (an icon
always showing 0, a "coming soon" page) reserving the header spot; this
slice replaces both with the real feature.

**Scope, deliberately bounded**: a normal, page-load-based inbox —
compose, reply, unread badge — not a live/AJAX-polling chat window. Live
notifications and PWA push remain separate, still-open Stage 9 items; a
DM inbox is checked periodically like email, not watched live like a chat
room, so no polling transport was built here.

**Schema**: `message_conversations` (`user_one_id`/`user_two_id`, always
stored in canonical smaller-id-first order, `UNIQUE(user_one_id,
user_two_id)`) and `direct_messages` (`conversation_id`, `sender_id`,
`body`, `read_at`). Strictly two-party by design, per the original
decoupling decision — no group-thread capability, no junction table
needed. `read_at` is deliberately per-message and DM-specific, not a
reuse of the generic `notifications` table's read state — reusing that
would have conflated DM-unread with every other notification type in the
same badge count.

**`MessagesService`** (`core/modules/messages/services/MessagesService.php`)
— `findOrCreateConversation()` (idempotent, canonicalizes user-id order so
the same pair can never get two conversation rows regardless of who
messages whom first), `sendMessage()`, `listConversationsForUser()`
(joined with the other participant's username and a per-conversation
unread count), `listMessagesInConversation()`, `markConversationRead()`,
`unreadCount()` (powers the header badge), `isParticipant()` (the
access-control check every controller action runs before letting anyone
view or post — never trust the URL alone, same discipline chat's private-
room membership check already established).

**A real bug caught live, not by lint**: `sendMessage()`'s conversation-
touch update originally wrote `SET last_message_at = :now, updated_at =
:now` — the same named placeholder twice in one query, bound once. Ran
fine everywhere else tonight by coincidence (this codebase's own
`FriendService` had already independently hit and solved this exact class
of bug with suffixed placeholder names — `:user_id1`/`:user_id2`/etc —
which this file's *other* multi-reference-to-the-same-value queries had
correctly followed; this one update was the one spot that got missed).
Surfaced as a real `PDOException: SQLSTATE[HY093]: Invalid parameter
number` 500 on the very first live reply attempt — `php -l` can't catch a
malformed parameterized query, only a live request against a real prepared
statement can, the same lesson this project has hit before (`forum_posts`'
`author_id` column name, earlier tonight). Fixed by using distinct
placeholder names for every repeated value. Caught a second-order effect
of the same bug too: the message `INSERT` and the conversation `UPDATE`
aren't wrapped in a transaction (this codebase doesn't use transactions
anywhere — not a new gap introduced here), so the two requests made while
the bug was live had already partially written a message row despite the
overall request 500ing; both were found and removed during test-data
cleanup, confirmed via a direct row-count query, not just assumed gone.

**Real `MessagesIconBlock`** replaces the placeholder — identical badge
pattern to `NotificationBellBlock`
(`core/modules/notifications/services/NotificationBellBlock.php`), just
backed by `MessagesService::unreadCount()` instead. Also fires a real
`$app->notify()` event on every new message — exactly what the original
2026-07-18 design notes specified ("PMs reuse the same notification hooks
... not a new alerting path"), giving a DM visibility in the general
notification feed independent of the envelope badge, the same
dual-signal pattern donations/commerce/chat invites already use.

**Verification**: full `php -l` sweep clean (after the fix above). Live
via curl as three real accounts. `modtest_member` messaged
`modtest_admin` by username twice in a row — confirmed only one
conversation row ever existed (`findOrCreateConversation`'s idempotency
holding under a real repeat), both messages landed in it. `modtest_admin`
replied; confirmed strict chronological ordering in the thread.
Confirmed the header badge accurately showed a real unread count before
a fresh reply was viewed and correctly dropped to zero immediately after
— tested with a purpose-built fresh message rather than trusting an
earlier already-viewed one, since opening a conversation during earlier
verification steps had already marked prior messages read. `modtest_outsider`
(never a participant) got a real 403 fetching the conversation directly
by id. Confirmed a message to a nonexistent username 422s with a clear
error, not a fatal. Confirmed the notification actually appears at
`/notifications`. All test conversations/messages/notifications deleted
afterward, confirmed via a follow-up zero-row query across all three
tables. Dev server stopped clean.

## Live Notifications ✅ (SHIPPED 2026-07-19)

**Why**: the second-to-last open item in Stage 9. Scope confirmed with
the user up front, since there was a real architectural fork: real
OS-level Web Push (a new Composer dependency, VAPID keys, an HTTPS
requirement) vs. in-app live updates (polling, no new dependencies).
Chose the latter — real push was explicitly out of scope for this pass,
matching the same reasoning chat's own transport-tier decision already
established ("no chat-owned push notifications — push delivery is
genuinely PWA-stage infrastructure," from the 2026-07-18 design notes).

**The key reuse, not a new mechanism**: `layout.php`'s dropdown
click-handler (`document.addEventListener('click', ...)` matching any
`[data-dropdown-trigger]`/`[data-dropdown-panel]` pair) was already
generic — built for the "More" nav and profile menu, but never actually
specific to either. `NotificationBellBlock`
(`core/modules/notifications/services/NotificationBellBlock.php`) now
emits its own self-contained dropdown (trigger + panel) and the existing
JS just works, zero `layout.php` changes needed for the toggle itself.

**Two new thin routes/actions**
(`core/modules/notifications/controllers/NotificationsController.php`):
`GET /notifications/unread-count` (tiny JSON, polled every 20s — longer
than chat's 4s interval since notifications are less time-sensitive,
deliberately lower request volume for the same shared-hosting-first
reasoning behind chat's own transport choice) and `GET
/notifications/panel` (re-fetched only when the dropdown is actually
opened, not on a timer, so panel content is fresh exactly when viewed
without polling the full list constantly). Both reuse
`NotificationService` completely unchanged — no service-layer changes at
all.

**New shared fragment** `core/modules/notifications/templates/panel.php`
— used for both the initial server-render (dropdown works before any JS
runs) and the AJAX re-fetch, same "one template, not duplicated in JS"
reasoning `chat/templates/message.php` already established. Explicit
mark-read behavior deliberately unchanged — polling/opening the panel
never silently marks anything read.

**Verification, the actual live behavior, not just the endpoints**:
`php -l` sweep clean. Live in the real browser as `modtest_member`:
recorded the badge's exact starting count, inserted a real notification
row directly, then **waited a real 22 seconds with zero page reload**
and confirmed the badge updated on its own (8 → 9) — proving the running
`setInterval` poll actually works, not just that the endpoint returns
correct JSON in isolation. Clicked the bell, confirmed the panel fetch
returned real fresh content including the just-inserted notification.
Marked it read through the real endpoint, confirmed the count decremented
correctly (9 → 8). Confirmed a logged-out guest gets the same empty
block render as before (unaffected). **A real cleanup gap caught along
the way**: found 8 leftover test notifications on `modtest_member` from
several *earlier* verification passes tonight (reports, friend requests,
even an uncleaned `commerce.purchase_confirmed` row from the commerce
slice) that had never been deleted — cleaned all of them up here rather
than leaving stale test data behind uncorrected. Dev server stopped
clean.

## PWA Support ✅ (SHIPPED 2026-07-19)

**Why**: the last open item in Stage 9. Built alongside live
notifications in the same pass rather than deferred, since real icon
assets already existed — `favicon.png`/`icon-circle.png` are genuine
1024–1254px brand art already wired into the header/footer/favicon, not
placeholders, so there was no reason to wait on new artwork.

**Icons generated from that real source art**, not invented: PHP GD
(confirmed available earlier this session) resized `icon-circle.png`
down to the three sizes a manifest/PWA actually needs —
`icon-192.png`, `icon-512.png`, `apple-touch-icon.png` (180×180) — a
one-time generation step, verified afterward at their exact intended
pixel dimensions, not assumed correct.

**`GET /manifest.json`**, registered in `public/index.php` right
alongside the other core-infrastructure routes already there
(`/sitemap.xml`, `/robots.txt`, `/site/header-banner`) — same "not
module-toggleable" reasoning, same `Response::streamFile()` helper those
already use. Dynamic, not a static file: reads `site_name`/
`theme_accent_color` from `core_settings` the identical way
`App::renderPage()` already does, so an installed app's name and theme
color genuinely match each club's real configured branding.

**`public/sw.js`** — a plain static file (no per-install dynamic values
needed inside it). A real, narrow offline shell: cache-first only for
genuinely static assets (`theme.css`, icons) — deliberately **not**
HTML, since almost every page here is per-member personalized
(unread counts, forum posts, dashboards) and caching that would show
stale, actively misleading content. Navigations are always
network-first, falling back to a small cached `/offline.html` only on a
genuine network failure. No background sync, no push event handler,
matching the "no Web Push this pass" decision directly.

**`layout.php`** gained `<link rel="manifest">`, a `<meta
name="theme-color">` using the accent color already in scope there, a
service-worker registration script, and a real bug fix in passing: the
existing `apple-touch-icon` link had been pointing at the raw
1254×1254 source image the whole time instead of a properly-sized icon —
now points at the new 180×180 file.

**A real, pre-existing bug found and correctly ruled out, not chased**:
`curl -I` on `/manifest.json` initially showed the wrong Content-Type
(`text/html`). Investigation showed `/sitemap.xml` and `/robots.txt` —
both pre-existing, already-shipped routes — showed the identical
symptom. Traced to `curl -I` issuing a HEAD request, which this router
doesn't register (GET/POST only) and 404s; a real `GET` request returns
the correct `application/manifest+json` every time. Confirmed harmless
for the actual feature (browsers and service workers only ever issue GET
for manifest fetches), not something introduced by this change, and not
something in scope to fix here.

**Verification**: confirmed `/manifest.json` returns valid JSON with the
real site name, real accent color, and correct icon paths via real `GET`
requests; confirmed `/sw.js` serves as `application/javascript`. Live in
the real browser: confirmed the service worker registered successfully
(`navigator.serviceWorker.getRegistrations()` — 1 registration, correct
scope) with zero console errors throughout. Confirmed `<link
rel="manifest">`, the theme-color meta tag, and the corrected
apple-touch-icon link are all present in the actual rendered DOM with
the right values, not just written in the template. `php -l` sweep
covered every PHP file touched (the service worker itself is static JS,
no PHP involved). Documented in `docs/roadmap.md`, closing out the last
open item in Stage 9 — the whole stage (chat, private messaging, live
notifications, PWA support) shipped in a single session.

**Known deployment caveat, documented not silently assumed**: real
installability requires HTTPS in production (`127.0.0.1`/`localhost`
qualify as secure-context exceptions for local testing, which is why
this verified cleanly here). Most cheap shared hosts default to free
HTTPS today, but this hasn't been verified per-club and isn't a
guarantee.

## Stage 10 — Platform Hardening & API

**Deliverables**: REST API surface over the existing service layer, optional
GraphQL, automated test suite consolidation, CI/CD, container-friendly
deployment, security audit. (The web-based installer itself is no longer
scoped here as "polish" — see "Web-Based Installer" above; it's a go-live
blocker that needs to exist well before this stage, not a refinement of
something already built.)
**Usable outcome**: Stratum is deployable via container/CI pipeline and has a
documented public API for future integrations/mobile apps.

**Added to scope 2026-07-19**: a real end-user manual — "how to use this
site" content covering members' actual features (forum, wiki, calendar,
chat, messages, downloads, the shop, the newsletter addon if installed),
plus one link/card in the admin dashboard pointing to it. Recommended
approach: reuse the existing `pages` module (`core/modules/pages/`,
already gives admins a real editable, sanitized content page) rather than
building a new module for this — one comprehensive page is the right v1,
split into multiple only if it actually gets unwieldy once written.

---

*Deferred, not scoped to a stage yet*: native mobile app (explicitly "not
required now" per the original vision notes).

All of the above (localization, caching, profile depth, downloads extras,
RSS auto-articles, and everything else in the Vision Parity Backlog) is
tracked in that section now — this is deliberate, real, intended work
against a concrete spec (feature parity with e107/SMF/ocPortal for the
clubs migrating to Stratum), not speculative scope held back by a "don't
build ahead of demand" default. No stage assignment or urgency ranking
beyond the sequencing note at the top of that section (Notifications and
Activity Feed first; everything else in any order).

*Brand/design assets to create* (noted 2026-07-13 — user working on these
over the next few days, matching the existing hexagon "S" / blue-silver
metallic style from `Stratum/de1f5f5a-5a3c-4994-9ea9-543de047cc43.png`):
- **Favicon** — square-cropped hexagon "S" only, no wordmark. Cross-cutting,
  needed soonest (before the Stage 8 header banner work even).
- **Default user avatar** — neutral placeholder for users with no
  `avatar_url` set (Stage 2's `strat_users.avatar_url` has no rendered
  fallback yet).
- **404/403/500 error page art** — small branded touch, used constantly.
- **Site header banner** — see the detailed Stage 8 entry above (centered
  image, header background blends into its blue tones).
- **Org space banner variants** (2-3 color accents) — Stage 4 organization
  spaces will likely want their own header banner per club/org, same upload
  mechanic as the site-wide one; good to have sample defaults ready.
- **Dark-mode variant of the site header banner** — Stage 8 lists dark mode
  as a deliverable; confirm/adjust banner contrast now rather than
  retrofitting later.
- **Module icon set** — extend the existing 7-icon strip (Members/Forums/
  Events/Files/Gallery/Secure/Payments) to cover Wiki/Articles/Calendar/News,
  for consistent icons in admin nav and empty-states.
- **Rank badge icons** — `docs/permission-model.md`'s `strat_ranks.icon`
  column exists but nothing's been designed for it (New Member/Veteran/
  Founder etc.).
- **Social share (OG) image**, 1200×630 — link previews when articles/wiki
  pages get shared.
- **PWA icon set** — Stage 9 PWA support needs the hexagon "S" exported at
  several fixed sizes (192×192, 512×512, apple-touch-icon) for the manifest.
- **Empty-state illustrations** (lower priority) — "nothing here yet"
  graphic for an empty forum board/wiki/calendar; makes a fresh install feel
  finished rather than broken.
