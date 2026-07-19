# Stratum CMS — Coding Standards

## Style

- PSR-12 formatting, PSR-4 autoloading (`Stratum\<Module>\` namespace per
  module, mapped via Composer).
- `declare(strict_types=1)` in every PHP file.
- Typed properties and return types everywhere; PHP 8 enums for fixed value
  sets (role keys, capability scope types) instead of string/int constants.
- No global functions except the small template helper set (`e()`, `raw()`,
  `route()`) — everything else is a class method reachable through the service
  layer.

## Database access

- PDO only, prepared statements only, named parameters preferred over
  positional for readability in longer queries.
- String interpolation of any request-derived value into a SQL string is
  disallowed without exception — this includes `ORDER BY`/column-name
  interpolation, which must go through an allow-list, not direct interpolation.
- All access through `core/services/Database.php` and module-level repository
  classes — no `new PDO()` scattered in controllers.

## Authentication & sessions

- Passwords hashed with `password_hash()` using `PASSWORD_ARGON2ID` (fallback
  to bcrypt only if Argon2i is unavailable in the PHP build).
- Session IDs regenerated on login and on privilege change (role grant/revoke).
- Session cookies: `HttpOnly`, `Secure` (enforced in production config),
  `SameSite=Lax`.
- Login rate limiting per IP + per account (sliding window in
  `strat_login_attempts`), independent of any module — this is core, not a
  security-module add-on.

## CSRF

- Every state-changing request (POST/PUT/PATCH/DELETE) requires a per-session
  CSRF token, checked in the front controller before a route handler runs —
  not opt-in per controller.

## Output escaping

- HTML output escaped by default via the template `e()` helper (see
  `theme-block-system.md`); raw HTML output is an explicit, reviewable
  exception (`raw()`), never the default.
- User-generated rich content (forum posts, articles) goes through a
  allow-listed BBCode/Markdown parser (`Stratum\Core\BBCodeParser`), never raw
  HTML storage — matches SMF's BBCode approach rather than allowing arbitrary
  HTML in stored content.
- **Exception: pages.** Page authorship is gated behind the `pages.manage`
  capability (admin/founder by default) — a materially different trust
  boundary than member-authored forum posts or articles — so pages use a
  real WYSIWYG editor (TinyMCE, vendored under
  `public/assets/vendor/tinymce/`) and store sanitized HTML rather than
  BBCode. "Sanitized" is load-bearing: bodies still go through
  `Stratum\Modules\Pages\HtmlSanitizer` (allow-listed tags/attributes,
  `on*` attributes stripped, non-`http(s)`/`mailto`/relative URLs rejected)
  before storage — admin-authored isn't zero-risk, so this is defense in
  depth, not a trust shortcut.

## File uploads

- Uploaded files stored outside the web root (or in a storage path with
  execution disabled), served through a controller that checks permissions and
  sets `Content-Disposition`/MIME type explicitly — never directly web-served
  from a user-writable directory.
- MIME type validated by content sniffing, not just file extension.

## Errors & logging

- No `@` error suppression.
- All caught exceptions logged via `core/services/Logger.php`
  (`strat_core_logs` table + optional file sink); user-facing error pages never
  leak stack traces or query text outside a `debug` environment flag.

## Testing (from Stage 1 onward)

- Every service-layer class gets PHPUnit coverage for its public methods.
- Every migration gets an up/down smoke test.
- No stage is marked complete without its service layer being tested — this is
  what makes Stage 10's "automated tests" deliverable a consolidation, not a
  first pass.
