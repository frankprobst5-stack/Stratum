<?php
/**
 * @var array<int, array<string, mixed>> $sources
 * @var string $csrfToken
 */
?>
<h1>RSS Feed Sources</h1>
<p style="color:#888;">Aggregated items appear at <a href="<?= e(route('/feeds')) ?>">/feeds</a>. The site's own articles are exported at <a href="<?= e(route('/feed.xml')) ?>">/feed.xml</a>.</p>

<table border="1" cellpadding="6" cellspacing="0" style="width:100%;border-collapse:collapse;">
    <thead>
        <tr>
            <th>Name</th>
            <th>Feed URL</th>
            <th>Last fetched</th>
            <th>Last error</th>
            <th>Enabled</th>
            <th>Auto-publish as articles</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($sources as $s): ?>
            <tr>
                <td><?= e($s['name']) ?></td>
                <td><?= e($s['feed_url']) ?></td>
                <td><?= e((string) ($s['last_fetched_at'] ?? 'Never')) ?></td>
                <td style="color:#c8362b;"><?= e((string) ($s['last_fetch_error'] ?? '')) ?></td>
                <td><?= $s['is_enabled'] ? 'Yes' : 'No' ?></td>
                <td>
                    <?= $s['auto_publish'] ? 'On' : 'Off' ?>
                    <form method="post" action="<?= e(route('/admin/rss/sources/' . $s['id'] . '/auto-publish')) ?>" style="display:inline;">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit"><?= $s['auto_publish'] ? 'Turn off' : 'Turn on' ?></button>
                    </form>
                </td>
                <td>
                    <form method="post" action="<?= e(route('/admin/rss/sources/' . $s['id'] . '/refresh')) ?>" style="display:inline;">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit">Refresh now</button>
                    </form>
                    <form method="post" action="<?= e(route('/admin/rss/sources/' . $s['id'] . '/toggle')) ?>" style="display:inline;">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit"><?= $s['is_enabled'] ? 'Disable' : 'Enable' ?></button>
                    </form>
                    <form method="post" action="<?= e(route('/admin/rss/sources/' . $s['id'] . '/delete')) ?>" style="display:inline;">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($sources === []): ?>
            <tr><td colspan="7" style="color:#888;">No feed sources yet.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<h2>Add feed source</h2>
<form method="post" action="<?= e(route('/admin/rss/sources')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <p>
        <label for="name">Name</label><br>
        <input type="text" id="name" name="name" required style="width:100%;max-width:30rem;">
    </p>
    <p>
        <label for="feed_url">Feed URL</label><br>
        <input type="text" id="feed_url" name="feed_url" required style="width:100%;max-width:30rem;" placeholder="https://example.com/feed.xml">
    </p>
    <button type="submit">Add source</button>
</form>
