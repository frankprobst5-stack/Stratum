<?php
/**
 * @var string $content
 * @var string $siteName
 * @var array<int, array{label: string, route: string}> $nav
 * @var array<int, array{label: string, route: string}> $guestNav
 * @var string $currentPath
 * @var bool $isLoggedIn
 * @var bool $isAdmin
 * @var string $csrfToken
 * @var \Stratum\Core\BlockRegistry $blocks
 * @var ?string $pageTitle
 * @var string $metaDescription
 * @var string $canonicalUrl
 * @var string $ogType
 * @var ?string $ogImage
 *
 * Starter theme layout — every variable above is provided by
 * App::renderPage(), the same set themes/default/templates/layout.php
 * receives. This copy is deliberately close to the default theme (same
 * structure, different accent color) so it's obvious at a glance what
 * changed; feel free to restructure completely.
 */
$fullTitle = $pageTitle !== null ? "{$pageTitle} — {$siteName}" : $siteName;
?>
<!doctype html>
<html lang="en">
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
    <?php if ($ogImage !== null): ?>
        <meta property="og:image" content="<?= e($ogImage) ?>">
    <?php endif; ?>
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; background: #f7f5f2; color: #1a1a1a; }
        header { background: #5b3a29; color: #fff; padding: 1rem 1.5rem; display: flex; align-items: center; justify-content: space-between; }
        header a { color: #fff; text-decoration: none; }
        header .brand { font-weight: 700; font-size: 1.1rem; }
        nav ul { list-style: none; display: flex; gap: 1.25rem; margin: 0; padding: 0; }
        .layout { display: grid; grid-template-columns: 200px 1fr 200px; gap: 1.5rem; padding: 1.5rem; max-width: 1200px; margin: 0 auto; }
        .layout main { background: #fff; border-radius: 8px; padding: 1.5rem; min-height: 200px; border: 1px solid #e8e2da; }
        .layout aside { min-height: 100px; }
        footer { text-align: center; padding: 1.5rem; color: #666; font-size: 0.85rem; }
    </style>
</head>
<body>
<header>
    <a class="brand" href="<?= e(route('/')) ?>"><?= e($siteName) ?></a>
    <nav>
        <ul>
            <?php foreach ($nav as $item): ?>
                <li><a href="<?= e(route($item['route'])) ?>"><?= e($item['label']) ?></a></li>
            <?php endforeach; ?>
            <?php if ($isAdmin): ?>
                <li><a href="<?= e(route('/admin')) ?>">Admin</a></li>
            <?php endif; ?>
            <?php if ($isLoggedIn): ?>
                <li><a href="<?= e(route('/profile')) ?>">My Profile</a></li>
                <li>
                    <form method="post" action="<?= e(route('/logout')) ?>">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit">Log out</button>
                    </form>
                </li>
            <?php else: ?>
                <?php foreach ($guestNav as $item): ?>
                    <li><a href="<?= e(route($item['route'])) ?>"><?= e($item['label']) ?></a></li>
                <?php endforeach; ?>
                <li><a href="<?= e(route('/login')) ?>">Log in</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>

<?= $blocks->renderRegion('header', $currentPath) ?>

<div class="layout">
    <aside class="sidebar-left"><?= $blocks->renderRegion('sidebar_left', $currentPath) ?></aside>
    <main><?= $content ?></main>
    <aside class="sidebar-right"><?= $blocks->renderRegion('sidebar_right', $currentPath) ?></aside>
</div>

<footer>
    <?= $blocks->renderRegion('footer', $currentPath) ?>
    <p><?= e($siteName) ?> — running the "My Theme" starter theme</p>
</footer>
</body>
</html>
