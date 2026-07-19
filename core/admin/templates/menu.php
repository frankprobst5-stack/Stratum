<?php
/**
 * @var array<int, array<string, mixed>> $items each row from nav_menu_items, plus a bool 'is_stale'
 * @var string $csrfToken
 */
?>
<h1>Menu Builder</h1>
<p style="color:#666;">
    Controls the site's top navigation — which items show directly in the bar
    ("Primary"), which fold into the "More" dropdown, their order, and their
    labels. Every module's nav item appears here automatically the first time
    it's enabled; disabling a module doesn't delete its row here (re-enabling
    it later keeps whatever placement/label you'd already given it), it's just
    hidden from the site and marked <em>stale</em> below until the module
    comes back.
</p>

<?php foreach ($items as $item): ?>
    <div style="display:flex; align-items:center; gap:0.75rem; padding:0.6rem 0.75rem; border:1px solid #eee; border-radius:6px; margin-bottom:0.4rem; <?= $item['is_stale'] ? 'opacity:0.5;' : '' ?>">
        <form method="post" action="<?= e(route('/admin/menu/' . $item['id'] . '/update')) ?>" style="display:flex; align-items:center; gap:0.5rem; flex:1; flex-wrap:wrap;">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="text" name="label" value="<?= e($item['label']) ?>" style="flex:1; min-width:8rem;">
            <code style="font-size:0.8rem; color:#666;"><?= e($item['route']) ?></code>
            <small style="color:#999;">(<?= e($item['source']) ?><?= $item['is_stale'] ? ', module disabled' : '' ?>)</small>
            <select name="placement">
                <option value="primary" <?= $item['placement'] === 'primary' ? 'selected' : '' ?>>Primary</option>
                <option value="more" <?= $item['placement'] === 'more' ? 'selected' : '' ?>>More dropdown</option>
                <option value="hidden" <?= $item['placement'] === 'hidden' ? 'selected' : '' ?>>Hidden</option>
            </select>
            <button type="submit">Save</button>
        </form>
        <form method="post" action="<?= e(route('/admin/menu/' . $item['id'] . '/move-up')) ?>" style="display:inline;">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <button type="submit" title="Move up">&uarr;</button>
        </form>
        <form method="post" action="<?= e(route('/admin/menu/' . $item['id'] . '/move-down')) ?>" style="display:inline;">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <button type="submit" title="Move down">&darr;</button>
        </form>
        <form method="post" action="<?= e(route('/admin/menu/' . $item['id'] . '/delete')) ?>" style="display:inline;">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <button type="submit" title="<?= $item['source'] === 'custom' ? 'Remove this link' : 'Reset to default' ?>"><?= $item['source'] === 'custom' ? 'Remove' : 'Reset' ?></button>
        </form>
    </div>
<?php endforeach; ?>
<?php if ($items === []): ?>
    <p style="color:#888;">No nav items yet.</p>
<?php endif; ?>

<h2>Add a custom link</h2>
<p style="color:#666;">
    An internal path (e.g. <code>/pages/about-us</code>) or a full external URL
    (e.g. <code>https://example.org</code> — opens in a new tab automatically).
</p>
<form method="post" action="<?= e(route('/admin/menu/create')) ?>" style="max-width:28rem;">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <p>
        <label style="display:block;font-size:0.85rem;color:#666;">Label
            <input type="text" name="label" required style="width:100%;">
        </label>
    </p>
    <p>
        <label style="display:block;font-size:0.85rem;color:#666;">URL or path
            <input type="text" name="route" required placeholder="/pages/about-us or https://example.org" style="width:100%;">
        </label>
    </p>
    <button type="submit">Add link</button>
</form>
