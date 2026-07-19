<?php
/**
 * @var array<int, array{id: int, name: string, is_builtin: bool}> $roles
 * @var array<int, array{id: int, key: string, module_id: string, label: string}> $capabilities
 * @var array<string, bool> $grantSet keyed "roleId:capabilityId"
 * @var string $csrfToken
 */
?>
<h1>Roles &amp; Permissions</h1>
<p><a href="<?= e(route('/admin/roles/audit')) ?>">View permissions audit</a> — who actually holds each role, including scoped ones</p>

<form method="post" action="<?= e(route('/admin/roles')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

    <table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
        <thead>
            <tr style="text-align:left; border-bottom:1px solid #ddd;">
                <th>Role</th>
                <?php foreach ($capabilities as $capability): ?>
                    <th title="<?= e($capability['key']) ?>">
                        <?= e($capability['label']) ?><br>
                        <small style="font-weight:normal; color:#888;">(<?= e($capability['module_id']) ?>)</small>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($roles as $role): ?>
            <tr style="border-bottom:1px solid #eee;">
                <td><?= e($role['name']) ?><?= $role['is_builtin'] ? ' <small style="color:#888;">(built-in)</small>' : '' ?></td>
                <?php foreach ($capabilities as $capability): ?>
                    <td style="text-align:center;">
                        <input
                            type="checkbox"
                            name="grants[<?= (int) $role['id'] ?>][<?= (int) $capability['id'] ?>]"
                            value="1"
                            <?= isset($grantSet[$role['id'] . ':' . $capability['id']]) ? 'checked' : '' ?>
                        >
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p><button type="submit">Save permissions</button></p>
</form>

<h2>Add a custom role</h2>
<form method="post" action="<?= e(route('/admin/roles/create')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <input type="text" name="name" placeholder="Role name" required>
    <button type="submit">Create role</button>
</form>
