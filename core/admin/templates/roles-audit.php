<?php
/**
 * @var array<int, array{id: int, name: string, is_builtin: bool, members: array<int, string>}> $siteWideRoles
 * @var array<int, array{id: int, name: string, is_builtin: bool, members: array<int, string>}> $scopedRoles
 */
?>
<p><a href="<?= e(route('/admin/roles')) ?>">&larr; Roles &amp; Permissions</a></p>
<h1>Permissions Audit</h1>
<p style="color:#666;">Who actually holds each role right now — the role &times; capability grid on the Roles &amp; Permissions page shows what each role can do, not who's in it.</p>

<h2>Site-wide roles</h2>
<table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
    <thead>
        <tr style="text-align:left; border-bottom:1px solid #ddd;">
            <th>Role</th>
            <th>Members</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($siteWideRoles as $role): ?>
        <tr style="border-bottom:1px solid #eee;">
            <td><?= e($role['name']) ?><?= $role['is_builtin'] ? '' : ' <small style="color:#888;">(custom)</small>' ?></td>
            <td>
                <?php if ($role['members'] === []): ?>
                    <span style="color:#888;">No members</span>
                <?php else: ?>
                    <?= e(implode(', ', $role['members'])) ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h2>Scoped roles</h2>
<p style="color:#666; font-size:0.9rem;">
    Auto-created per-object roles (one board's moderators, one chapter's
    officers, etc.) — these don't appear in the main matrix above since
    they only ever grant their capability within their own object, but
    they're real role assignments and worth seeing in one place.
</p>
<?php if ($scopedRoles === []): ?>
    <p style="color:#888;">None yet.</p>
<?php else: ?>
    <table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
        <thead>
            <tr style="text-align:left; border-bottom:1px solid #ddd;">
                <th>Role</th>
                <th>Members</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($scopedRoles as $role): ?>
            <tr style="border-bottom:1px solid #eee;">
                <td><?= e($role['name']) ?></td>
                <td>
                    <?php if ($role['members'] === []): ?>
                        <span style="color:#888;">No members</span>
                    <?php else: ?>
                        <?= e(implode(', ', $role['members'])) ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
