<?php
/**
 * @var array<int, array<string, mixed>> $users
 * @var string $csrfToken
 * @var string|null $error
 */
?>
<p><a href="<?= e(route('/admin/users')) ?>">&larr; Users</a></p>
<h1>Merge Accounts</h1>
<p style="color:#666; max-width:600px;">
    Combines two duplicate accounts into one: every post, page, event,
    upload, friend/follow, badge, role, and reputation point the "merge
    away" account has ever earned moves to the "keep" account, then the
    merge-away account is deleted the same way a normal account deletion
    works. This cannot be undone.
</p>

<?php if ($error !== null): ?>
    <p style="color:#b00020;"><?= e($error) ?></p>
<?php endif; ?>

<form method="post" action="<?= e(route('/admin/users/merge')) ?>" onsubmit="return confirm('Merge these accounts? This cannot be undone.');">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

    <p>
        <label for="source_id">Merge away (duplicate account)</label><br>
        <select id="source_id" name="source_id" required>
            <option value="">— Select —</option>
            <?php foreach ($users as $user): ?>
                <option value="<?= (int) $user['id'] ?>"><?= e($user['username']) ?> (<?= e($user['email']) ?>)</option>
            <?php endforeach; ?>
        </select>
    </p>

    <p>
        <label for="target_id">Keep (canonical account)</label><br>
        <select id="target_id" name="target_id" required>
            <option value="">— Select —</option>
            <?php foreach ($users as $user): ?>
                <option value="<?= (int) $user['id'] ?>"><?= e($user['username']) ?> (<?= e($user['email']) ?>)</option>
            <?php endforeach; ?>
        </select>
    </p>

    <button type="submit">Merge accounts</button>
</form>
