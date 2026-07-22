<?php
/**
 * @var string $content
 * @var string $siteName
 * @var array<int, array{id: int, label: string, route: string, external: bool}> $primaryNav weight-ordered, admin-editable (Stage 8 menu builder — NavMenuService::orderedItems())
 * @var array<int, array{id: int, label: string, route: string, external: bool}> $moreNav weight-ordered, folds into the "More" dropdown
 * @var array<int, array{label: string, route: string}> $guestNav
 * @var string $currentPath
 * @var bool $isLoggedIn
 * @var bool $isAdmin
 * @var string $csrfToken
 * @var \Stratum\Core\BlockRegistry $blocks
 * @var ?string $pageTitle per-page title set by the controller, or null to just show the site name (App::renderPage())
 * @var string $metaDescription
 * @var string $canonicalUrl
 * @var string $ogType
 * @var ?string $ogImage
 * @var string $headerBannerUrl admin-uploadable header art, or the built-in default if none set (App::renderPage())
 * @var array<string, mixed>|null $currentUser (App::renderPage())
 * @var string $accentColor hex color, e.g. "#2f6fed" (App::renderPage(), Stage 8 color/typography manager)
 * @var string $fontStackCss a resolved CSS font-family value from Stratum\Core\FontStacks (App::renderPage())
 * @var string $darkMode 'off' | 'on' | 'auto' (App::renderPage(), Stage 8 dark mode)
 */
$fullTitle = $pageTitle !== null ? "{$pageTitle} — {$siteName}" : $siteName;

/**
 * Dark mode (Stage 8) — three admin-chosen modes. Since 2026-07-19's
 * design-system pass, both the light and dark palettes are static CSS
 * in assets/css/theme.css (real design constants, not per-install
 * settings — only --strat-accent/--strat-font are). This file's whole
 * job is now just choosing which server-set <html> attributes to emit:
 *   'off' — no attributes at all. Nothing in theme.css ever matches
 *     dark without an explicit data-theme="dark" or the auto-mode
 *     marker below, so this is unconditionally always light.
 *   'on' — data-theme="dark" directly, server-side. No JS at all;
 *     theme.css's `:root[data-theme="dark"]` rule does the rest.
 *   'auto' — data-dark-mode="auto" (theme.css's OS-preference media
 *     query only fires under this marker, so 'off' sites are never
 *     accidentally pulled into dark by a visitor's OS setting) plus the
 *     FOUC-prevention script below, which stamps the actual data-theme
 *     from localStorage/matchMedia before first paint.
 */
$htmlAttrs = match ($darkMode) {
    'on' => ' data-theme="dark"',
    'auto' => ' data-dark-mode="auto"',
    default => '',
};

/**
 * Top nav redesign, 2026-07-18 (reference mockup): a curated set of
 * "primary" routes render as icon+label directly in the bar; everything
 * else folds into a "More" dropdown. Which routes are primary vs. "More"
 * — and their order, labels, and any admin-added custom links — is now
 * real admin-controlled state (Stage 8 menu builder, `/admin/menu`,
 * `NavMenuService`) rather than the hardcoded list this file used to
 * carry directly; $primaryNav/$moreNav arrive here already resolved.
 *
 * $navIcons stays presentational-only (not a DB column) — icons are
 * cosmetic, and every route the admin can newly promote to "primary"
 * still gets a sane fallback glyph below rather than needing an icon
 * picker UI. Originally trimmed from 7 to 5 default primary routes the
 * day this shipped (label-wrapping at real window widths); that history
 * is now just this file's starting default, freely adjustable by an
 * admin from here on.
 */
$navIcons = [
    '/' => "\u{1F3E0}",
    '/forum' => "\u{1F4AC}",
    '/articles' => "\u{1F4F0}",
    '/calendar' => "\u{1F4C5}",
    '/downloads' => "\u{2B07}\u{FE0F}",
];
$fallbackNavIcon = "\u{1F517}";
$themeToggleIcon = "\u{1F313}";

$isActiveRoute = static fn (string $route): bool => $route === '/' ? $currentPath === '/' : str_starts_with($currentPath, $route);
$userInitials = $currentUser !== null ? strtoupper(substr((string) $currentUser['username'], 0, 2)) : '';
?>
<!doctype html>
<html lang="en"<?= raw($htmlAttrs) ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($fullTitle) ?></title>
    <?php if ($metaDescription !== ''): ?>
        <meta name="description" content="<?= e($metaDescription) ?>">
    <?php endif; ?>
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <meta property="og:type" content="<?= e($ogType) ?>">
    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:title" content="<?= e($pageTitle ?? $siteName) ?>">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <?php if ($metaDescription !== ''): ?>
        <meta property="og:description" content="<?= e($metaDescription) ?>">
    <?php endif; ?>
    <?php if ($ogImage !== null): ?>
        <meta property="og:image" content="<?= e($ogImage) ?>">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:image" content="<?= e($ogImage) ?>">
    <?php else: ?>
        <meta name="twitter:card" content="summary">
    <?php endif; ?>
    <meta name="twitter:title" content="<?= e($pageTitle ?? $siteName) ?>">
    <?php if ($metaDescription !== ''): ?>
        <meta name="twitter:description" content="<?= e($metaDescription) ?>">
    <?php endif; ?>
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    <link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="<?= e($accentColor) ?>">
    <link rel="stylesheet" href="/assets/css/theme.css?v=6">
    <?php /* CSS integration (2026-07-21) — the header/nav below is restyled onto this; everything else on the page still runs on theme.css until its own turn comes. See docs/roadmap.md's CSS Integration entries. */ ?>
    <link rel="stylesheet" href="/assets/css/dashboard.css?v=3">
    <?php /* raw(), not e(): <style> is an HTML5 "raw text" element — entities like &quot; are NOT decoded inside it, so escaping a quoted font name (e.g. "Times New Roman") would emit literal &quot; characters and break the declaration. Safe here because $fontStackCss only ever comes from FontStacks::cssFor()'s own fixed, hardcoded OPTIONS map — never directly from user input. Only these two values are genuinely per-install-dynamic — everything else lives in theme.css, see docs/design-system.md. */ ?>
    <style>:root { --strat-accent: <?= e($accentColor) ?>; --strat-font: <?= raw($fontStackCss) ?>; }</style>
    <?php if ($darkMode === 'auto'): ?>
        <?php
        /**
         * Stamps data-theme on <html> from localStorage (a visitor's prior
         * manual toggle) or, failing that, matchMedia — BEFORE <body>
         * starts parsing, so the correct palette is already active on the
         * very first paint (no flash of the wrong theme). Must be a plain
         * synchronous inline <script> here in <head>, not deferred/async
         * and not moved to the external bbcode-toolbar.js file — either
         * of those would let the browser paint with the default (light)
         * palette first, then visibly flip.
         */
        ?>
        <script>
            (function () {
                var stored = localStorage.getItem('strat-theme');
                var theme = stored || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.setAttribute('data-theme', theme);
            })();
        </script>
    <?php endif; ?>
</head>
<body>
<header class="sc-header">
    <a class="sc-header-brand" href="<?= e(route('/')) ?>">
        <img src="/assets/images/icon-circle.png" alt="" style="height:1.6rem;width:auto;">
        <?= e($siteName) ?>
    </a>
    <nav class="sc-topbar-nav">
        <ul>
            <?php foreach ($primaryNav as $item): ?>
                <li>
                    <a href="<?= e(route($item['route'])) ?>" class="<?= !$item['external'] && $isActiveRoute($item['route']) ? 'active' : '' ?>" <?= $item['external'] ? 'target="_blank" rel="noopener"' : '' ?>>
                        <span aria-hidden="true"><?= $navIcons[$item['route']] ?? $fallbackNavIcon ?></span>
                        <span class="nav-label"><?= e($item['label']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
            <?php if ($moreNav !== []): ?>
                <li class="strat-header-dropdown">
                    <button type="button" data-dropdown-trigger="nav-more">
                        <span class="nav-label">More</span> <span aria-hidden="true">&#9662;</span>
                    </button>
                    <div class="strat-header-dropdown-panel" data-dropdown-panel="nav-more">
                        <?php foreach ($moreNav as $item): ?>
                            <a href="<?= e(route($item['route'])) ?>" <?= $item['external'] ? 'target="_blank" rel="noopener"' : '' ?>><?= e($item['label']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
    <div class="sc-topbar-actions">
        <?= $blocks->renderRegion('topbar_actions', $currentPath) ?>
        <?php if ($isLoggedIn && $currentUser !== null): ?>
            <div class="strat-header-dropdown">
                <button type="button" data-dropdown-trigger="profile-menu" aria-label="Account menu" style="background:none;border:none;padding:0.2rem;cursor:pointer;">
                    <span class="sc-avatar" style="display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:700;color:#fff;"><?= e($userInitials) ?></span>
                </button>
                <div class="strat-header-dropdown-panel" data-dropdown-panel="profile-menu">
                    <div style="padding:0.4rem 0.6rem;font-size:0.78rem;color:var(--strat-muted-text);">
                        Signed in as <strong><?= e((string) $currentUser['username']) ?></strong>
                    </div>
                    <a href="<?= e(route('/profile')) ?>">My Profile</a>
                    <a href="<?= e(route('/friends')) ?>">Friends</a>
                    <?php if ($isAdmin): ?>
                        <a href="<?= e(route('/admin')) ?>">Admin</a>
                    <?php endif; ?>
                    <form method="post" action="<?= e(route('/logout')) ?>">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit">Log out</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($guestNav as $item): ?>
                <a href="<?= e(route($item['route'])) ?>" class="sc-topbar-guest-link"><?= e($item['label']) ?></a>
            <?php endforeach; ?>
            <a href="<?= e(route('/login')) ?>" class="sc-topbar-cta">Log in</a>
        <?php endif; ?>
        <?php if ($darkMode === 'auto'): ?>
            <button type="button" class="strat-header-icon" id="strat-theme-toggle" aria-label="Toggle dark mode" title="Toggle dark mode">
                <span aria-hidden="true"><?= $themeToggleIcon ?></span>
            </button>
        <?php endif; ?>
    </div>
</header>
<div class="site-banner">
    <img class="site-banner-img" src="<?= e($headerBannerUrl) ?>" alt="<?= e($siteName) ?>">
</div>

<?= $blocks->renderRegion('header', $currentPath) ?>

<?php
/**
 * A region with a placement (e.g. ads.banner with no active campaign
 * right now) can still render '' — a sidebar "occupied" but visually
 * empty was reserving a fixed 200px grid track for nothing, the actual
 * cause of the page looking squeezed even after the width/min-width
 * fixes above, reported 2026-07-18. Collapsing an empty column's grid
 * track (rather than leaving a static 3-column template regardless of
 * content) means the main content area actually gets that space back
 * instead of it sitting empty either way.
 */
$sidebarLeftHtml = $blocks->renderRegion('sidebar_left', $currentPath);
$sidebarRightHtml = $blocks->renderRegion('sidebar_right', $currentPath);
$layoutColumns = trim((trim($sidebarLeftHtml) !== '' ? '200px ' : '') . '1fr' . (trim($sidebarRightHtml) !== '' ? ' 200px' : ''));
?>
<div class="layout" style="grid-template-columns: <?= e($layoutColumns) ?>;">
    <?php if (trim($sidebarLeftHtml) !== ''): ?>
        <aside class="sidebar-left"><?= $sidebarLeftHtml ?></aside>
    <?php endif; ?>
    <main><?= $content ?></main>
    <?php if (trim($sidebarRightHtml) !== ''): ?>
        <aside class="sidebar-right"><?= $sidebarRightHtml ?></aside>
    <?php endif; ?>
</div>

<footer>
    <?= $blocks->renderRegion('footer', $currentPath) ?>
    <p>
        Powered by
        <img src="/assets/images/icon-circle.png" alt="Stratum CMS" style="height:1.1em; width:auto; vertical-align:-0.2em; margin:0 0.2em;">
        Stratum CMS
    </p>
</footer>
<script src="/assets/js/bbcode-toolbar.js" defer></script>
<script>
(function () {
    // Single click handler for every topbar dropdown (nav overflow, search,
    // notifications aren't dropdowns but profile menu is) — vanilla JS, no
    // framework, same "no build step" posture this app holds everywhere.
    //
    // Panels are position: fixed (see theme.css) so they escape
    // .sc-topbar-nav's overflow-x: hidden — which meant top/right can no
    // longer be static CSS (fixed is relative to the viewport, not the
    // trigger), so this computes them from the trigger's real position
    // every time a panel opens.
    function closeAllPanels() {
        document.querySelectorAll('.strat-header-dropdown-panel.open').forEach(function (p) { p.classList.remove('open'); });
    }
    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-dropdown-trigger]');
        if (trigger) {
            var panel = document.querySelector('[data-dropdown-panel="' + trigger.getAttribute('data-dropdown-trigger') + '"]');
            var wasOpen = panel && panel.classList.contains('open');
            closeAllPanels();
            if (panel && !wasOpen) {
                var rect = trigger.getBoundingClientRect();
                panel.style.top = (rect.bottom + 8) + 'px';
                panel.style.right = (window.innerWidth - rect.right) + 'px';
                panel.classList.add('open');
            }
            e.stopPropagation();
            return;
        }
        if (!e.target.closest('.strat-header-dropdown-panel')) {
            closeAllPanels();
        }
    });
    // A fixed-position panel stays put on-screen while the page scrolls
    // underneath it, drifting away from its trigger — simplest correct
    // fix is just closing it, same as a click outside already does.
    window.addEventListener('scroll', closeAllPanels, true);
})();
</script>
<?php /* PWA service worker (Stage 9, 2026-07-19) — offline shell only, no push; see public/sw.js. */ ?>
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/sw.js').catch(function () {});
    });
}
</script>
<?php if ($darkMode === 'auto'): ?>
<script>
(function () {
    var toggle = document.getElementById('strat-theme-toggle');
    if (!toggle) {
        return;
    }
    toggle.addEventListener('click', function () {
        var current = document.documentElement.getAttribute('data-theme')
            || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        var next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('strat-theme', next);
    });
})();
</script>
<?php endif; ?>
</body>
</html>
