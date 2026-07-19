<?php
/**
 * @var array<int, array<string, mixed>> $rooms every room; private rooms additionally carry a 'members' array
 * @var string $csrfToken
 */
?>
<h1>Chat</h1>
<p style="color:#666;">
    Admin-created rooms are permanent and can be public or private. Member-
    created rooms are always public and disappear on their own once
    everyone's left — you can still remove one early if needed. You can
    delete any room here, admin- or member-created.
</p>

<?php foreach ($rooms as $room): ?>
    <div style="border:1px solid #eee; border-radius:6px; padding:0.75rem; margin-bottom:0.6rem;">
        <form method="post" action="<?= e(route('/admin/chat/' . $room['id'] . '/update')) ?>" style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="text" name="name" value="<?= e($room['name']) ?>" style="flex:1; min-width:8rem;">
            <input type="text" name="topic" value="<?= e($room['topic'] ?? '') ?>" placeholder="Topic" style="flex:1; min-width:8rem;">
            <?php if ($room['source'] === 'admin'): ?>
                <select name="visibility">
                    <option value="public" <?= $room['visibility'] === 'public' ? 'selected' : '' ?>>Public</option>
                    <option value="private" <?= $room['visibility'] === 'private' ? 'selected' : '' ?>>Private</option>
                </select>
            <?php else: ?>
                <small style="color:#999;">Member room &middot; always public</small>
            <?php endif; ?>
            <small style="color:#999;"><?= (int) $room['member_count'] ?> member<?= (int) $room['member_count'] === 1 ? '' : 's' ?></small>
            <button type="submit">Save</button>
        </form>
        <form method="post" action="<?= e(route('/admin/chat/' . $room['id'] . '/delete')) ?>" style="display:inline; margin-top:0.4rem;">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <button type="submit">Delete room</button>
        </form>

        <?php if ($room['visibility'] === 'private'): ?>
            <div style="margin-top:0.6rem; padding-top:0.6rem; border-top:1px solid #f0f0f0;">
                <strong style="font-size:0.85rem;">Members (invite-only — add them here)</strong>
                <ul style="margin:0.4rem 0;">
                    <?php foreach ($room['members'] as $member): ?>
                        <li>
                            <?= e($member['username']) ?>
                            <form method="post" action="<?= e(route('/admin/chat/' . $room['id'] . '/members/' . $member['id'] . '/remove')) ?>" style="display:inline;">
                                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                <button type="submit" style="font-size:0.75rem;">Remove</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                    <?php if ($room['members'] === []): ?>
                        <li style="color:#999;">No members yet.</li>
                    <?php endif; ?>
                </ul>
                <form method="post" action="<?= e(route('/admin/chat/' . $room['id'] . '/members/add')) ?>" style="display:flex; gap:0.4rem;">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <input type="text" name="username" placeholder="Add member by username" style="flex:1;">
                    <button type="submit">Add</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
<?php if ($rooms === []): ?>
    <p style="color:#888;">No chat rooms yet.</p>
<?php endif; ?>

<h2>Create an admin room</h2>
<form method="post" action="<?= e(route('/admin/chat/create')) ?>" style="max-width:28rem;">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <p>
        <label style="display:block;font-size:0.85rem;color:#666;">Room name
            <input type="text" name="name" required style="width:100%;">
        </label>
    </p>
    <p>
        <label style="display:block;font-size:0.85rem;color:#666;">Topic (optional)
            <input type="text" name="topic" style="width:100%;">
        </label>
    </p>
    <p>
        <label style="display:block;font-size:0.85rem;color:#666;">Visibility
            <select name="visibility">
                <option value="public">Public</option>
                <option value="private">Private</option>
            </select>
        </label>
    </p>
    <button type="submit">Create room</button>
</form>
