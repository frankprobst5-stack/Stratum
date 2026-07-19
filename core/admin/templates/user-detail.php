<?php
/**
 * @var array<string, mixed> $user
 * @var array<int, int> $roleIds
 * @var array<int, array{id: int, name: string, is_builtin: bool}> $roles
 * @var array<int, array{id: int, body: string, author_id: int, authorName: string, created_at: string}> $notes
 * @var array<int, array<string, mixed>> $memberBadges this member's current badges, each with 'awarded_at'
 * @var array<int, array<string, mixed>> $allBadges badges this member does NOT already hold
 * @var string $csrfToken
 * @var ?string $deleteError
 */
?>
<p><a href="<?= e(route('/admin/users')) ?>">&larr; Users</a></p>
<h1><?= e($user['username']) ?></h1>
<p style="color:#666;">
    <?= e($user['email']) ?>
    &middot; Joined <?= e($user['created_at']) ?>
</p>

<?php if ($deleteError !== null): ?>
    <p style="color:#b00020;"><?= e($deleteError) ?></p>
<?php endif; ?>

<h2>Roles</h2>
<form method="post" action="<?= e(route('/admin/users/' . $user['id'] . '/roles')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <?php foreach ($roles as $role): ?>
        <label style="margin-right:0.75rem; white-space:nowrap;">
            <input type="checkbox" name="roles[<?= (int) $role['id'] ?>]" value="1" <?= in_array($role['id'], $roleIds, true) ? 'checked' : '' ?>>
            <?= e($role['name']) ?>
        </label>
    <?php endforeach; ?>
    <button type="submit">Save roles</button>
</form>

<h2>Badges</h2>
<?php if ($memberBadges === []): ?>
    <p style="color:#888;">No badges yet.</p>
<?php else: ?>
    <ul>
        <?php foreach ($memberBadges as $badge): ?>
            <li style="margin-bottom:0.3rem;">
                <?php if (!empty($badge['icon_url'])): ?>
                    <img src="<?= e($badge['icon_url']) ?>" alt="" style="width:18px; height:18px; vertical-align:middle;">
                <?php endif; ?>
                <?= e($badge['name']) ?>
                <small style="color:#888;">(awarded <?= e($badge['awarded_at']) ?>)</small>
                <form method="post" action="<?= e(route('/admin/users/' . $user['id'] . '/badges/' . $badge['id'] . '/revoke')) ?>" style="display:inline;">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button type="submit" style="border:none; background:none; color:#888; text-decoration:underline; cursor:pointer; padding:0; font-size:0.85rem;">Revoke</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if ($allBadges !== []): ?>
    <form method="post" action="<?= e(route('/admin/users/' . $user['id'] . '/badges')) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <select name="badge_id" required>
            <?php foreach ($allBadges as $badge): ?>
                <option value="<?= (int) $badge['id'] ?>"><?= e($badge['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Award badge</button>
    </form>
<?php endif; ?>

<h2>Staff notes</h2>
<p style="color:#888; font-size:0.9rem;">Visible to admins only — reminders, handoff notes, ongoing-issue tracking for this member.</p>

<?php if ($notes === []): ?>
    <p style="color:#888;">No notes yet.</p>
<?php endif; ?>

<?php foreach ($notes as $note): ?>
    <div style="margin-bottom:0.75rem; padding:0.6rem 0.85rem; background:#f4f5f7; border-radius:6px;">
        <div style="white-space:pre-wrap;"><?= e($note['body']) ?></div>
        <p style="margin:0.4rem 0 0; color:#888; font-size:0.85rem;">
            <?= e($note['authorName']) ?> &middot; <?= e($note['created_at']) ?>
            &middot;
            <form method="post" action="<?= e(route('/admin/users/' . $user['id'] . '/notes/' . $note['id'] . '/delete')) ?>" style="display:inline;">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <button type="submit" style="border:none; background:none; color:#888; text-decoration:underline; cursor:pointer; padding:0; font-size:0.85rem;">Delete</button>
            </form>
        </p>
    </div>
<?php endforeach; ?>

<form method="post" action="<?= e(route('/admin/users/' . $user['id'] . '/notes')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <p>
        <textarea name="body" rows="3" cols="60" required placeholder="Add a note about this member..."></textarea>
    </p>
    <button type="submit">Add note</button>
</form>

<hr>

<h2>Danger zone</h2>
<form method="post" action="<?= e(route('/admin/users/' . $user['id'] . '/delete')) ?>" onsubmit="return confirm('Delete this account? They will be logged out and unable to log back in. Their content is not deleted.');">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <button type="submit" style="color:#b00020;">Delete this account</button>
</form>
