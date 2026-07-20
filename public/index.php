<?php

declare(strict_types=1);

/**
 * Stratum CMS — front controller. All HTTP requests enter here.
 */

// The PHP built-in dev server (`php -S ... public/index.php`) runs this
// file as a router script for every request, including ones for real
// static files (assets/, favicon.png) — without this opt-out it 404s
// them through the app router instead of serving them directly, which
// silently broke every asset under public/assets/ (TinyMCE's JS/CSS,
// the favicon, the illustrated error pages) in local dev. Real Apache
// hosting never runs this SAPI (it uses public/.htaccess's `!-f` check
// instead), so this is a dev-server-only concern.
if (PHP_SAPI === 'cli-server') {
    $assetPath = __DIR__ . parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($assetPath)) {
        return false;
    }
}

require dirname(__DIR__) . '/vendor/autoload.php';

use Stratum\Core\ApiTokenService;
use Stratum\Core\App;
use Stratum\Core\Auth;
use Stratum\Core\BlockRegistry;
use Stratum\Core\HookRegistry;
use Stratum\Core\Logger;
use Stratum\Core\ModuleManager;
use Stratum\Core\PermissionEngine;
use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Core\Router;
use Stratum\Core\Session;
use Stratum\Core\SitemapService;
use Stratum\Core\TemplateEngine;
use Stratum\Modules\Users\AuthService;

/** @var string $rootDir @var \Stratum\Core\Config $config @var \Stratum\Core\Database $db */
[$rootDir, $config, $db] = require __DIR__ . '/../core/bootstrap.php';

$logger = new Logger($db, $rootDir . '/storage/logs');

set_exception_handler(static function (\Throwable $e) use ($logger, $config): void {
    $logger->error($e->getMessage(), ['exception' => get_class($e)]);

    if ($config->isDebug()) {
        http_response_code(500);
        echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';

        return;
    }

    Response::serverError()->send();
});

$session = new Session($config->get('APP_ENV') === 'production');
$hooks = new HookRegistry();
$blocks = new BlockRegistry($db);

// Read before TemplateEngine is constructed, same "settings row read early"
// pattern App::renderPage() already uses for site_name — 'default' is the
// only theme that could possibly be missing this setting (a fresh install,
// before core migration 001 even runs) or having it point at a theme that
// no longer exists (a custom theme was deleted while active), so this falls
// back to the one theme guaranteed to always exist.
$activeTheme = 'default';
try {
    $themeSetting = $db->fetchOne(
        'SELECT `value` FROM ' . $db->table('core_settings') . " WHERE `key` = 'active_theme'"
    );
    if ($themeSetting !== null && $themeSetting['value'] !== '') {
        $activeTheme = $themeSetting['value'];
    }
} catch (\Throwable) {
    // core_settings doesn't exist yet (mid-install) — 'default' stands.
}

$templates = new TemplateEngine(
    $rootDir . '/themes',
    $rootDir . '/core/modules',
    $rootDir . '/core/admin',
    $activeTheme,
    $rootDir . '/storage/themes',
    $rootDir . '/storage/addons'
);

// Same early-read pattern as $activeTheme above — site-wide, not
// per-user (see Translator's own docblock for why), so this has to be
// resolved before any template renders, not per-page.
$siteLanguage = 'en';
try {
    $languageSetting = $db->fetchOne(
        'SELECT `value` FROM ' . $db->table('core_settings') . " WHERE `key` = 'site_language'"
    );
    if ($languageSetting !== null && $languageSetting['value'] !== '') {
        $siteLanguage = $languageSetting['value'];
    }
} catch (\Throwable) {
    // core_settings doesn't exist yet (mid-install) — 'en' stands.
}
\Stratum\Core\Translator::load($rootDir . '/lang', $siteLanguage);

// Auth is needed before ModuleManager::boot() runs, so the 'users' module's
// AuthService is loaded directly here (users is permanently enabled — see
// docs/module-interface.md and Stage 1 plan). ModuleManager::boot() will
// require_once the same file again for the module's own lifecycle; that's
// a no-op the second time.
$permissions = new PermissionEngine($db);

// Built before Auth (Stage 10) so Auth can resolve a Bearer API token
// from it when there's no session — see Auth::userFromBearerToken().
$request = Request::fromGlobals();

require_once $rootDir . '/core/modules/users/services/AuthService.php';
$auth = new Auth($session, $db, new AuthService($db), $permissions, $request, new ApiTokenService($db));

// Full-page cache — checked here, before ModuleManager::boot(), so a
// hit skips essentially the entire request pipeline (module loading,
// routing, controller logic, DB content queries, template rendering),
// not just a faster version of the same work. Only ever GET requests
// outside /admin from an actual guest: $_SESSION['user_id'] absent is
// a cheap, safe "definitely not logged in" check that doesn't need a DB
// round trip the way $auth->check() would — see PageCache's own
// docblock for why a cached page can never contain a CSRF token.
$cachePath = rtrim($request->path(), '/') ?: '/';
$cacheEligible = $config->getBool('PAGE_CACHE_ENABLED', false)
    && $request->method() === 'GET'
    && !str_starts_with($cachePath, '/admin')
    && !isset($_SESSION['user_id']);
$pageCache = new \Stratum\Core\PageCache(
    $rootDir . '/storage/cache/pages',
    $config->getInt('PAGE_CACHE_TTL_SECONDS', 300)
);

if ($cacheEligible) {
    $cacheKey = (string) ($_SERVER['REQUEST_URI'] ?? $cachePath);
    $cached = $pageCache->get($cacheKey);
    if ($cached !== null) {
        header('Content-Type: text/html; charset=utf-8');
        header('X-Stratum-Cache: HIT');
        echo $cached;
        exit;
    }
}

$router = new Router();
$modules = new ModuleManager($db, $rootDir . '/core/modules', $rootDir . '/storage/addons');

$app = new App($rootDir, $config, $db, $session, $auth, $router, $hooks, $blocks, $templates, $logger, $modules, $permissions);

$modules->boot($app);

// Presence tracking needs to run on literally every request (guest or
// member, whatever route), which no module route/hook can do — same
// reasoning AuthService gets an early require above. isEnabled()-gated so
// disabling the module actually stops writes, not just hides the block.
if ($modules->isEnabled('presence') && session_id() !== '' && session_id() !== false) {
    require_once $rootDir . '/core/modules/presence/services/PresenceService.php';
    (new \Stratum\Modules\Presence\PresenceService($db))->touch(
        session_id(),
        $auth->check() ? (int) $auth->user()['id'] : null
    );
}

// The admin panel is not a toggleable module (see docs/architecture.md) — its routes always load.
(static function (string $routesFile, App $app): void {
    $router = $app->router;
    require $routesFile;
})($rootDir . '/core/admin/routes.php', $app);

// The REST API (Stage 10) spans every module's own service layer, so it
// isn't one module's concern either — same reasoning as the admin panel
// above, same loading pattern.
(static function (string $routesFile, App $app): void {
    $router = $app->router;
    require $routesFile;
})($rootDir . '/core/api/routes.php', $app);

/**
 * Front page, block-composed (Stage 8, 2026-07-18) — replaces the
 * previous hardcoded "Welcome to Stratum" stub. Layout is hero + compact
 * side list, then a 3-column freeform area; see
 * docs/theme-block-system.md's Layout section for the full design. All
 * five regions are `page_scope = 'front_page_only'`, so nothing renders
 * here unless a placement targets one of them (a fresh install seeds a
 * reasonable default via core migration 012).
 */
$router->get('/', static function (Request $request) use ($app): Response {
    $hero = $app->blocks->renderRegion('front_hero_main', '/');
    $heroSide = $app->blocks->renderRegion('front_hero_side', '/');
    // wrapInCards: true — the 3-column area stacks several blocks per
    // column, which ran together with nothing separating them
    // (reported 2026-07-18); the hero row above already has its own
    // distinct slider/list styling, so it's left as-is.
    $col1 = $app->blocks->renderRegion('front_col_1', '/', wrapInCards: true);
    $col2 = $app->blocks->renderRegion('front_col_2', '/', wrapInCards: true);
    $col3 = $app->blocks->renderRegion('front_col_3', '/', wrapInCards: true);

    $content = '<div class="strat-front-hero">'
        . '<div class="strat-front-hero-main">' . $hero . '</div>'
        . '<div class="strat-front-hero-side">' . $heroSide . '</div>'
        . '</div>'
        . '<div class="strat-front-columns">'
        . '<div class="strat-front-col">' . $col1 . '</div>'
        . '<div class="strat-front-col">' . $col2 . '</div>'
        . '<div class="strat-front-col">' . $col3 . '</div>'
        . '</div>';

    return Response::html($app->renderPage($content, $request));
});

// Core, always-on site infrastructure (Built-in SEO, 2026-07-17) — not
// module-toggleable, same "admin panel isn't a module" reasoning above, so
// registered here rather than as a module's own routes.php.
$router->get('/sitemap.xml', static function (Request $request) use ($app): Response {
    $xml = (new SitemapService($app->db, $app->modules))->buildXml($request->baseUrl());

    return Response::streamFile($xml, 'application/xml; charset=utf-8');
});

$router->get('/robots.txt', static function (Request $request) use ($app): Response {
    $body = "User-agent: *\nAllow: /\nSitemap: " . $request->baseUrl() . "/sitemap.xml\n";

    return Response::streamFile($body, 'text/plain; charset=utf-8');
});

// PWA manifest (Stage 9 PWA support, 2026-07-19) — dynamic, not a static
// file, so an installed app's name/theme color genuinely reflect each
// club's actual configured branding rather than a hardcoded default. Same
// core_settings read App::renderPage() already does. Icons were generated
// once from the real icon-circle.png brand art, not placeholders — see
// docs/roadmap.md.
$router->get('/manifest.json', static function (Request $request) use ($app): Response {
    $settingsRows = $app->db->fetchAll(
        'SELECT `key`, `value` FROM ' . $app->db->table('core_settings')
            . " WHERE `key` IN ('site_name', 'theme_accent_color')"
    );
    $settings = array_column($settingsRows, 'value', 'key');
    $siteName = $settings['site_name'] ?? 'Stratum CMS';
    $accentColor = $settings['theme_accent_color'] ?? '#2f6fed';
    if (preg_match('/^#[0-9a-f]{6}$/i', $accentColor) !== 1) {
        $accentColor = '#2f6fed';
    }

    $manifest = [
        'name' => $siteName,
        'short_name' => mb_substr($siteName, 0, 12),
        'start_url' => '/',
        'display' => 'standalone',
        'background_color' => '#15171c',
        'theme_color' => $accentColor,
        'icons' => [
            ['src' => '/assets/images/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
            ['src' => '/assets/images/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
        ],
    ];

    return Response::streamFile(
        (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        'application/manifest+json'
    );
});

// Custom header banner, if an admin has uploaded one (Stage 8 header/
// masthead, 2026-07-18) — served from outside the webroot same as every
// other uploaded image in this app, not module-toggleable so it lives
// here rather than in any single module's routes.php. Falls back to the
// static default image (/assets/images/logo-wide.png) in layout.php
// itself when no custom banner is set — this route is only ever linked
// to when one actually exists.
$router->get('/site/header-banner', static function (Request $request) use ($app, $rootDir): Response {
    $settingRow = $app->db->fetchOne(
        'SELECT `value` FROM ' . $app->db->table('core_settings') . " WHERE `key` = 'header_banner_ext'"
    );
    $ext = $settingRow['value'] ?? '';
    if ($ext === '') {
        return Response::notFound();
    }

    $mimeTypes = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    $path = $rootDir . "/storage/uploads/site/header-banner.{$ext}";
    if (!is_file($path)) {
        return Response::notFound();
    }

    return Response::streamFile((string) file_get_contents($path), $mimeTypes[$ext] ?? 'application/octet-stream');
});

// Maintenance mode — checked here rather than as a route/hook since it
// needs to intercept literally every request before normal dispatch,
// same "no module route can do this" reasoning presence tracking above
// already established. `/login` and `/admin/*` stay exempt so a staff
// member can actually authenticate and turn this back off; anyone with
// `admin.access` (the same blanket "can get into the admin panel at
// all" capability App::renderPage() already exposes as `isAdmin` — not
// the narrower `users.manage`) bypasses it entirely so admins can keep
// browsing/QA-ing the live site while it's "down" for everyone else.
$maintenancePath = rtrim($request->path(), '/') ?: '/';
$maintenanceExempt = $maintenancePath === '/login' || str_starts_with($maintenancePath, '/admin');
if (!$maintenanceExempt) {
    $maintenanceOn = $db->fetchOne(
        'SELECT `value` FROM ' . $db->table('core_settings') . " WHERE `key` = 'maintenance_mode'"
    );
    if (($maintenanceOn['value'] ?? '0') === '1' && !$auth->can('admin.access')) {
        $siteNameSetting = $db->fetchOne(
            'SELECT `value` FROM ' . $db->table('core_settings') . " WHERE `key` = 'site_name'"
        );
        $messageSetting = $db->fetchOne(
            'SELECT `value` FROM ' . $db->table('core_settings') . " WHERE `key` = 'maintenance_message'"
        );

        Response::maintenance(
            ($siteNameSetting['value'] ?? '') !== '' ? $siteNameSetting['value'] : 'Stratum CMS',
            ($messageSetting['value'] ?? '') !== '' ? $messageSetting['value'] : "We're performing scheduled maintenance. Please check back soon."
        )->send();
        exit;
    }
}

$response = $router->dispatch($request);

// Page cache write — same eligibility as the read side above, plus the
// response actually succeeded and is real HTML (never cache a redirect,
// a 404, a JSON/file download, etc.). PageCache::put() itself refuses
// anything containing a CSRF token regardless of this check, but
// filtering to HTML 200s here avoids even attempting to cache the
// obviously-wrong response types.
if ($cacheEligible
    && $response->status() === 200
    && str_starts_with($response->headers()['Content-Type'] ?? '', 'text/html')
) {
    $pageCache->put((string) ($_SERVER['REQUEST_URI'] ?? $cachePath), $response->body());
}

// Admin action audit log — "who changed what, when," distinct from
// Logger/core_logs (app/error logging). Written here, once, rather
// than as calls scattered across ~30 admin controllers: every admin
// mutation is captured automatically, present and future, with no risk
// of a new controller action forgetting to log itself. Only mutating
// methods under /admin/ that actually succeeded (2xx/3xx, not a
// rejected 4xx guard/CSRF failure) count as a real change.
$auditMethod = $request->method();
if (in_array($auditMethod, ['POST', 'PUT', 'PATCH', 'DELETE'], true)
    && str_starts_with(rtrim($request->path(), '/') ?: '/', '/admin/')
    && $response->status() < 400
    && $auth->check()
) {
    $auditUser = $auth->user();
    (new \Stratum\Core\AuditLogService($db))->record(
        (int) $auditUser['id'],
        (string) $auditUser['username'],
        $auditMethod,
        $request->path()
    );
}

$response->send();
