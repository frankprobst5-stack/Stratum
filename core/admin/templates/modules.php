<?php
/**
 * @var array<int, array{id: string, name: string, core: bool, enabled: bool, disableable: bool, custom: bool}> $modules
 * @var string $csrfToken
 * @var ?string $uploadError
 */
?>
<h1>Modules</h1>
<p><a href="<?= e(route('/admin/modules/dependencies')) ?>">View dependency graph</a></p>

<?php if ($uploadError !== null): ?>
    <p style="color:#b00020;"><?= e($uploadError) ?></p>
<?php endif; ?>

<table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
    <thead>
        <tr style="text-align:left; border-bottom:1px solid #ddd;">
            <th>Module</th>
            <th>Type</th>
            <th>Status</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($modules as $module): ?>
        <tr style="border-bottom:1px solid #eee;">
            <td><?= e($module['name']) ?> <code>(<?= e($module['id']) ?>)</code></td>
            <td><?= $module['core'] ? 'Core' : ($module['custom'] ? 'Addon (custom)' : 'Optional') ?></td>
            <td><?= $module['enabled'] ? 'Enabled' : 'Disabled' ?></td>
            <td>
                <?php if ($module['disableable']): ?>
                    <form method="post" action="<?= e(route('/admin/modules/' . $module['id'] . '/toggle')) ?>" style="display:inline;">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="enabled" value="<?= $module['enabled'] ? '0' : '1' ?>">
                        <button type="submit"><?= $module['enabled'] ? 'Disable' : 'Enable' ?></button>
                    </form>
                <?php else: ?>
                    <em>always on</em>
                <?php endif; ?>
                <?php if ($module['custom']): ?>
                    <form method="post" action="<?= e(route('/admin/modules/addons/' . $module['id'] . '/delete')) ?>" style="display:inline;">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit">Remove</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h2>Addons</h2>
<p style="color:#666;">
    An addon is a zip built to the same module shape every built-in module already uses.
    <a href="<?= e(route('/admin/modules/addons/starter')) ?>">Download the starter addon</a> for a working
    example to build from.
</p>
<form method="post" action="<?= e(route('/admin/modules/addons/upload')) ?>" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <input type="file" name="package" accept=".zip" required>
    <button type="submit">Upload addon</button>
</form>
