<?php
/**
 * @var array<int, array<string, mixed>> $users each with 'roleIds' (int[])
 * @var array<int, array{id: int, name: string, is_builtin: bool}> $roles
 * @var array<int, array{id: int, username: string, email: string, deleted_at: string}> $deletedUsers
 * @var string $csrfToken
 */
?>
<h1>Users</h1>

<p>
    <a href="<?= e(route('/admin/users/create')) ?>">+ Create user</a>
    &middot;
    <a href="<?= e(route('/admin/users/merge')) ?>">Merge accounts</a>
</p>

<table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
    <thead>
        <tr style="text-align:left; border-bottom:1px solid #ddd;">
            <th>Username</th>
            <th>Email</th>
            <th>Roles</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $user): ?>
        <tr style="border-bottom:1px solid #eee;">
            <td><a href="<?= e(route('/admin/users/' . $user['id'])) ?>"><?= e($user['username']) ?></a></td>
            <td><?= e($user['email']) ?></td>
            <td>
                <form method="post" action="<?= e(route('/admin/users/' . $user['id'] . '/roles')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <?php foreach ($roles as $role): ?>
                        <label style="margin-right:0.75rem; white-space:nowrap;">
                            <input
                                type="checkbox"
                                name="roles[<?= (int) $role['id'] ?>]"
                                value="1"
                                <?= in_array($role['id'], $user['roleIds'], true) ? 'checked' : '' ?>
                            >
                            <?= e($role['name']) ?>
                        </label>
                    <?php endforeach; ?>
                    <button type="submit">Save</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php if ($deletedUsers !== []): ?>
    <h2>Deleted accounts</h2>
    <table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
        <thead>
            <tr style="text-align:left; border-bottom:1px solid #ddd;">
                <th>Username</th>
                <th>Email</th>
                <th>Deleted</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($deletedUsers as $user): ?>
            <tr style="border-bottom:1px solid #eee;">
                <td><?= e($user['username']) ?></td>
                <td><?= e($user['email']) ?></td>
                <td><?= e($user['deleted_at']) ?></td>
                <td>
                    <form method="post" action="<?= e(route('/admin/users/' . $user['id'] . '/restore')) ?>">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit">Restore</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
