<?php
/**
 * @var array<int, array{id: string, name: string, enabled: bool,
 *     requires: array<int, array{id: string, name: string, enabled: bool}>,
 *     requiredBy: array<int, array{id: string, name: string, enabled: bool}>}> $graph
 */
?>
<p><a href="<?= e(route('/admin/modules')) ?>">&larr; Modules</a></p>
<h1>Module Dependencies</h1>

<table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
    <thead>
        <tr style="text-align:left; border-bottom:1px solid #ddd;">
            <th>Module</th>
            <th>Requires</th>
            <th>Required by</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($graph as $module): ?>
        <tr style="border-bottom:1px solid #eee;">
            <td>
                <?= e($module['name']) ?>
                <?= $module['enabled'] ? '' : ' <small style="color:#c0392b;">(disabled)</small>' ?>
            </td>
            <td>
                <?php if ($module['requires'] === []): ?>
                    <span style="color:#888;">—</span>
                <?php else: ?>
                    <?php foreach ($module['requires'] as $dep): ?>
                        <span style="<?= $dep['enabled'] ? '' : 'color:#c0392b;' ?>">
                            <?= e($dep['name']) ?><?= $dep['enabled'] ? '' : ' (disabled!)' ?>
                        </span><br>
                    <?php endforeach; ?>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($module['requiredBy'] === []): ?>
                    <span style="color:#888;">—</span>
                <?php else: ?>
                    <?php foreach ($module['requiredBy'] as $dep): ?>
                        <?= e($dep['name']) ?><?= $dep['enabled'] ? '' : ' <small style="color:#888;">(disabled)</small>' ?><br>
                    <?php endforeach; ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
