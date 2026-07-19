<?php
/**
 * @var array<int, array{id: int, name: string, is_builtin: bool}> $roles
 * @var string $csrfToken
 * @var string|null $error
 */
?>
<h1>Create User</h1>

<?php if ($error !== null): ?>
    <p style="color:#b00020;"><?= e($error) ?></p>
<?php endif; ?>

<form method="post" action="<?= e(route('/admin/users/create')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

    <p>
        <label for="username">Username</label><br>
        <input type="text" id="username" name="username" required>
    </p>

    <p>
        <label for="email">Email</label><br>
        <input type="email" id="email" name="email" required>
    </p>

    <p>
        <label for="password">Password</label><br>
        <input type="password" id="password" name="password" required minlength="12">
    </p>

    <p>
        Roles:<br>
        <?php foreach ($roles as $role): ?>
            <label style="margin-right:0.75rem;">
                <input type="checkbox" name="roles[<?= (int) $role['id'] ?>]" value="1">
                <?= e($role['name']) ?>
            </label>
        <?php endforeach; ?>
    </p>

    <button type="submit">Create</button>
</form>
