<?php
/**
 * @var array<int, array{id: string, name: string, version: string, description: string, custom: bool, active: bool}> $themes
 * @var string $csrfToken
 * @var ?string $uploadError
 */
$builtInThemes = array_filter($themes, static fn (array $t): bool => !$t['custom']);
?>
<h1>Themes</h1>

<?php if ($uploadError !== null): ?>
    <p style="color:#b00020;"><?= e($uploadError) ?></p>
<?php endif; ?>

<table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
    <thead>
        <tr style="text-align:left; border-bottom:1px solid #ddd;">
            <th>Theme</th>
            <th>Type</th>
            <th>Status</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($themes as $theme): ?>
        <tr style="border-bottom:1px solid #eee;">
            <td>
                <?= e($theme['name']) ?> <code>(<?= e($theme['id']) ?>)</code>
                <?php if ($theme['description'] !== ''): ?>
                    <br><small style="color:#666;"><?= e($theme['description']) ?></small>
                <?php endif; ?>
            </td>
            <td><?= $theme['custom'] ? 'Custom' : 'Built-in' ?></td>
            <td><?= $theme['active'] ? '<strong>Active</strong>' : '—' ?></td>
            <td>
                <?php if (!$theme['active']): ?>
                    <form method="post" action="<?= e(route('/admin/themes/' . $theme['id'] . '/activate')) ?>" style="display:inline;">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit">Activate</button>
                    </form>
                <?php endif; ?>
                <?php if ($theme['custom'] && !$theme['active']): ?>
                    <form method="post" action="<?= e(route('/admin/themes/' . $theme['id'] . '/delete')) ?>" style="display:inline;">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit">Remove</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h2>Create a child theme</h2>
<p style="color:#666;">
    A child theme starts out identical to whichever built-in theme you base it on —
    no files to write by hand. Activate it, then customize it over time by adding
    <code>overrides/{module}/{template}.php</code> files (or a full <code>templates/layout.php</code>)
    directly on the server; anything you don't override keeps inheriting from the parent.
</p>
<form method="post" action="<?= e(route('/admin/themes/create-child')) ?>" style="max-width:28rem;">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <p>
        <label style="display:block;font-size:0.85rem;color:#666;">Base on
            <select name="parent_id" required style="width:100%;">
                <?php foreach ($builtInThemes as $theme): ?>
                    <option value="<?= e($theme['id']) ?>"><?= e($theme['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </p>
    <p>
        <label style="display:block;font-size:0.85rem;color:#666;">Theme id (lowercase, no spaces)
            <input type="text" name="id" required pattern="[a-z][a-z0-9_-]*" placeholder="my_club_theme" style="width:100%;">
        </label>
    </p>
    <p>
        <label style="display:block;font-size:0.85rem;color:#666;">Display name
            <input type="text" name="name" placeholder="My Club Theme" style="width:100%;">
        </label>
    </p>
    <p>
        <label style="display:block;font-size:0.85rem;color:#666;">Description
            <input type="text" name="description" style="width:100%;">
        </label>
    </p>
    <button type="submit">Create child theme</button>
</form>

<h2>Upload a theme</h2>
<p style="color:#666;">
    A theme is a zip with a <code>theme.json</code> at its root, plus a <code>templates/layout.php</code> —
    unless <code>theme.json</code> declares a <code>"parent"</code> (a built-in theme's id), in which case
    <code>templates/layout.php</code> is optional and the parent's is inherited.
    <a href="<?= e(route('/admin/themes/starter')) ?>">download the starter theme</a> for a working example to build from.
</p>
<form method="post" action="<?= e(route('/admin/themes/upload')) ?>" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <input type="file" name="package" accept=".zip" required>
    <button type="submit">Upload theme</button>
</form>
