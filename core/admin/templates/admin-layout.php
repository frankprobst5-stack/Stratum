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
 * module-driven and already past 20 entries), a bottom-pinned user
 * identity card. Rendered directly by TemplateEngine::renderAdminLayout()
 * — not subject to the theme override chain, same reasoning the public
 * layout.php itself isn't: this *is* the admin chrome, not themeable
 * content.
 */

// Presentational grouping only — which section a module's admin screen
// belongs under. Lives here (not a module.json field) deliberately: this
// is the first real consumer of "group my nav item," and promoting it to
// a manifest schema field before a second consumer needs the same thing
// would be designing ahead of demand, the same discipline this app
// applies everywhere else. If a second consumer ever needs this, that's
// the signal to promote it.
// Keyed on the literal admin route prefix rather than an assumed
// module-id segment — a module's admin route doesn't always match its
// module id (e.g. rss_aggregator's route is /admin/rss, not
// /admin/rss_aggregator), so extracting an id from the route and looking
// it back up by id was fragile. Matching the route prefix directly avoids
// that assumption entirely.
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
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin — <?= e($siteName) ?></title>
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    <link rel="apple-touch-icon" href="/assets/images/icon-circle.png">
    <style>
        :root { --accent: #2f6fed; --border: #e3e5ea; --text-dim: #6b7280; }
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; margin: 0; background: #f4f5f7; color: #1a1a1a; }
        a { color: inherit; }
        .admin-shell { display: grid; grid-template-columns: 220px 1fr; min-height: 100vh; }
        .admin-topbar {
            grid-column: 1 / -1; display: flex; align-items: center; justify-content: space-between;
            padding: 0.75rem 1.5rem; background: #fff; border-bottom: 1px solid var(--border);
        }
        .admin-topbar .brand { font-weight: 700; font-size: 1.05rem; text-decoration: none; }
        .admin-topbar .brand span { color: var(--accent); }
        .admin-topbar-actions { display: flex; align-items: center; gap: 1rem; font-size: 0.9rem; }
        .admin-topbar-actions a { text-decoration: none; color: var(--text-dim); }
        .admin-topbar-actions a:hover { color: var(--accent); }
        .admin-sidebar { background: #fff; border-right: 1px solid var(--border); display: flex; flex-direction: column; justify-content: space-between; }
        .admin-nav-groups { padding: 1rem 0; overflow-y: auto; }
        .admin-nav-section { margin-bottom: 1.25rem; }
        .admin-nav-section h3 {
            font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-dim);
            margin: 0 0 0.4rem; padding: 0 1.25rem;
        }
        .admin-nav-section ul { list-style: none; margin: 0; padding: 0; }
        .admin-nav-section a {
            display: block; padding: 0.45rem 1.25rem; text-decoration: none; font-size: 0.9rem; color: #333;
            border-left: 3px solid transparent;
        }
        .admin-nav-section a:hover { background: #f4f5f7; }
        .admin-nav-section a.active { border-left-color: var(--accent); background: #eef3fe; color: var(--accent); font-weight: 600; }
        .admin-user-card { padding: 1rem 1.25rem; border-top: 1px solid var(--border); font-size: 0.85rem; }
        .admin-user-card .name { font-weight: 600; }
        .admin-user-card form { margin-top: 0.4rem; }
        .admin-user-card button { font-size: 0.8rem; background: none; border: none; padding: 0; color: var(--text-dim); cursor: pointer; text-decoration: underline; }
        .admin-main { padding: 1.5rem 2rem; max-width: 1200px; }
        .admin-panel-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin-top: 1rem; }
        .admin-panel {
            background: #fff; border-radius: 10px; padding: 1.1rem 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
        }
        .admin-panel h2 { font-size: 0.95rem; margin: 0 0 0.75rem; display: flex; align-items: center; justify-content: space-between; }
        .admin-panel h2 a { font-size: 0.8rem; font-weight: 400; color: var(--accent); text-decoration: none; }
        .admin-panel ul { list-style: none; margin: 0; padding: 0; font-size: 0.88rem; }
        .admin-panel li { padding: 0.4rem 0; border-bottom: 1px solid #f0f1f3; }
        .admin-panel li:last-child { border-bottom: none; }
        .admin-panel .muted { color: var(--text-dim); }
        .admin-stat { display: flex; justify-content: space-between; font-size: 0.88rem; padding: 0.3rem 0; }
        .admin-stat .value { font-weight: 600; }
    </style>
</head>
<body>
<div class="admin-shell">
    <header class="admin-topbar">
        <a class="brand" href="<?= e(route('/admin')) ?>"><?= e($siteName) ?> <span>Admin</span></a>
        <div class="admin-topbar-actions">
            <a href="<?= e(route('/')) ?>">View site &rarr;</a>
        </div>
    </header>

    <nav class="admin-sidebar">
        <div class="admin-nav-groups">
            <?php foreach ($sections as $sectionName => $items): ?>
                <div class="admin-nav-section">
                    <h3><?= e($sectionName) ?></h3>
                    <ul>
                        <?php foreach ($items as $item): ?>
                            <li><a href="<?= e(route($item['route'])) ?>" class="<?= str_starts_with($currentPath, $item['route']) ? 'active' : '' ?>"><?= e($item['label']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="admin-user-card">
            <div class="name"><?= e($currentUser['username'] ?? 'Admin') ?></div>
            <form method="post" action="<?= e(route('/logout')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <button type="submit">Log out</button>
            </form>
        </div>
    </nav>

    <main class="admin-main"><?= $content ?></main>
</div>
</body>
</html>
