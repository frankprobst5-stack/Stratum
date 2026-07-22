<?php
/**
 * @var string $content
 * @var string $siteName
 * @var array<string, mixed>|null $currentUser
 * @var array<int, array{label: string, route: string, capability: string}> $moduleAdminNav each capability-filtered for the current admin
 * @var string $currentPath
 * @var string $csrfToken
 *
 * The admin panel's own chrome — distinct from the public site's
 * layout.php, per the confirmed "modernized e107" direction
 * (docs/roadmap.md, Stage 8): a dedicated top-bar + collapsible-left-
 * sidebar shell, sectioned nav (not one flat list — the admin nav is
 * module-driven and already past 20 entries). Rendered directly by
 * TemplateEngine::renderAdminLayout() — not subject to the theme
 * override chain, same reasoning the public layout.php itself isn't:
 * this *is* the admin chrome, not themeable content.
 *
 * Restyled 2026-07-21 onto the new dashboard design system
 * (public/assets/css/dashboard.css, `sc-` prefixed classes) — same
 * structure as before (this file's own layout logic is unchanged), new
 * visual system (real shadows, a grouped sidebar with section icons, the
 * user identity moved into the header per the actual design reference —
 * see docs/roadmap.md's Pre-Launch Hardening entry for the mockups this
 * was built against).
 */

$routeGroups = [
    '/admin/articles' => 'Content', '/admin/pages' => 'Content', '/admin/wiki' => 'Content',
    '/admin/downloads' => 'Content', '/admin/video' => 'Content', '/admin/links' => 'Content',
    '/admin/forum' => 'Community', '/admin/classifieds' => 'Community', '/admin/org_spaces' => 'Community',
    '/admin/membership' => 'Community', '/admin/moderation' => 'Community', '/admin/calendar' => 'Community',
    '/admin/chat' => 'Community',
    '/admin/dues' => 'Commerce', '/admin/donations' => 'Commerce',
    '/admin/ticker' => 'Site Tools', '/admin/rss' => 'Site Tools',
];

$sections = ['Content' => [], 'Community' => [], 'Commerce' => [], 'Site Tools' => [], 'System' => []];

foreach ($moduleAdminNav as $item) {
    $group = 'Site Tools';
    foreach ($routeGroups as $prefix => $groupName) {
        if (str_starts_with($item['route'], $prefix)) {
            $group = $groupName;
            break;
        }
    }
    $sections[$group][] = $item;
}

$sections['System'] = [
    ['label' => 'Modules', 'route' => '/admin/modules'],
    ['label' => 'Themes', 'route' => '/admin/themes'],
    ['label' => 'Block Placements', 'route' => '/admin/blocks'],
    ['label' => 'Menu Builder', 'route' => '/admin/menu'],
    ['label' => 'Site Settings', 'route' => '/admin/settings'],
    ['label' => 'Users', 'route' => '/admin/users'],
    ['label' => 'Badges', 'route' => '/admin/badges'],
    ['label' => 'Roles & Permissions', 'route' => '/admin/roles'],
    ['label' => 'System Update', 'route' => '/admin/system/update'],
    ['label' => 'System Health', 'route' => '/admin/system/health'],
    ['label' => 'Logs', 'route' => '/admin/system/logs'],
    ['label' => 'Backups', 'route' => '/admin/system/backups'],
    ['label' => 'Admin Action Log', 'route' => '/admin/system/audit-log'],
    ['label' => 'Page Cache', 'route' => '/admin/system/cache'],
    ['label' => 'Trash', 'route' => '/admin/trash'],
];

$sections = array_filter($sections, static fn (array $items): bool => $items !== []);

/** Cosmetic only, one per section — not a per-item icon set (~40 module
 * nav items, module-driven and open-ended, isn't worth hand-mapping one
 * by one for a chrome pass). */
$sectionIcons = [
    'Content' => "\u{1F4C4}", 'Community' => "\u{1F465}", 'Commerce' => "\u{1F4B3}",
    'Site Tools' => "\u{1F6E0}\u{FE0F}", 'System' => "\u{2699}\u{FE0F}",
];

$userInitials = $currentUser !== null ? strtoupper(substr((string) $currentUser['username'], 0, 2)) : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin — <?= e($siteName) ?></title>
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    <link rel="apple-touch-icon" href="/assets/images/icon-circle.png">
    <link rel="stylesheet" href="/assets/css/dashboard.css?v=2">
</head>
<body class="sc-root">
<header class="sc-header">
    <a class="sc-header-brand" href="<?= e(route('/admin')) ?>">
        <?= e($siteName) ?> <span style="color:#94a3b8;font-weight:500;">Admin</span>
    </a>
    <nav class="sc-header-nav">
        <a class="sc-header-link" href="<?= e(route('/')) ?>">View site &rarr;</a>
        <?php if ($currentUser !== null): ?>
            <div class="sc-header-user">
                <span class="sc-avatar" style="display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:700;color:#fff;"><?= e($userInitials) ?></span>
                <span>Welcome, <?= e((string) $currentUser['username']) ?></span>
            </div>
            <form method="post" action="<?= e(route('/logout')) ?>" style="margin:0;">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <button type="submit" class="sc-header-link" style="background:none;border:none;font:inherit;cursor:pointer;padding:0;">Log out</button>
            </form>
        <?php endif; ?>
    </nav>
</header>

<div class="sc-dashboard-layout">
    <nav class="sc-sidebar">
        <?php foreach ($sections as $sectionName => $items): ?>
            <div class="sc-sidebar-group">
                <div class="sc-sidebar-label"><?= $sectionIcons[$sectionName] ?? '' ?> <?= e($sectionName) ?></div>
                <?php foreach ($items as $item): ?>
                    <a class="sc-sidebar-item<?= str_starts_with($currentPath, $item['route']) ? ' active' : '' ?>" href="<?= e(route($item['route'])) ?>">
                        <?= e($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </nav>

    <main class="sc-container" style="flex:1;min-width:0;"><?= $content ?></main>
</div>
</body>
</html>
