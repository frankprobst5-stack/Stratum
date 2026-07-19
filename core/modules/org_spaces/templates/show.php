<?php
/**
 * @var array<string, mixed> $org
 * @var string|null $renderedDescription
 * @var array<int, array<string, mixed>> $officers each with 'username', 'title'
 * @var array<int, array<string, mixed>> $roster each with 'username', 'title', 'is_officer', 'user_id'
 * @var bool $canSeeRoster
 * @var array<int, array<string, mixed>> $announcements each with 'renderedBody', 'authorName'
 * @var bool $canManage
 * @var bool $isLoggedIn
 * @var string $csrfToken
 */
$base = '/organizations/' . $org['slug'];
?>
<p><a href="<?= e(route('/organizations')) ?>">&larr; Organizations</a></p>

<h1><?= e($org['name']) ?></h1>

<?php if ($canSeeRoster): ?>
    <p>
        <a href="<?= e(route($base . '/forum')) ?>">Forum</a>
        &middot; <a href="<?= e(route($base . '/calendar')) ?>">Calendar</a>
        &middot; <a href="<?= e(route($base . '/files')) ?>">Files</a>
        &middot; <a href="<?= e(route($base . '/gallery')) ?>">Gallery</a>
    </p>
<?php endif; ?>

<?php if ($renderedDescription !== null): ?>
    <div style="white-space:pre-wrap;"><?= raw($renderedDescription) ?></div>
<?php endif; ?>

<h2>Officers</h2>
<?php if ($officers !== []): ?>
    <ul>
        <?php foreach ($officers as $officer): ?>
            <li><?= e($officer['username']) ?><?php if (!empty($officer['title'])): ?> — <?= e($officer['title']) ?><?php endif; ?></li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p style="color:#888;">No officers listed yet.</p>
<?php endif; ?>

<h2>Roster</h2>
<?php if ($canSeeRoster): ?>
    <?php if ($roster !== []): ?>
        <ul>
            <?php foreach ($roster as $member): ?>
                <li>
                    <?= e($member['username']) ?><?php if (!empty($member['title'])): ?> — <?= e($member['title']) ?><?php endif; ?>
                    <?php if ($member['is_officer']): ?> <strong>(officer)</strong><?php endif; ?>
                    <?php if ($canManage): ?>
                        <form method="post" action="<?= e(route($base . '/members/' . $member['user_id'] . '/update')) ?>" style="display:inline;">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                            <input type="text" name="title" value="<?= e($member['title'] ?? '') ?>" placeholder="Title" size="12">
                            <label><input type="checkbox" name="is_officer" value="1" <?= $member['is_officer'] ? 'checked' : '' ?>> Officer</label>
                            <button type="submit">Update</button>
                        </form>
                        <form method="post" action="<?= e(route($base . '/members/' . $member['user_id'] . '/remove')) ?>" style="display:inline;">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                            <button type="submit">Remove</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p style="color:#888;">No members yet.</p>
    <?php endif; ?>
<?php else: ?>
    <p style="color:#888;">Roster is visible to members of this organization.</p>
<?php endif; ?>

<?php if ($canManage): ?>
    <h3>Add a member</h3>
    <form method="post" action="<?= e(route($base . '/members')) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <p>
            <label for="username">Username</label><br>
            <input type="text" id="username" name="username" required>
        </p>
        <p>
            <label for="title">Title (optional)</label><br>
            <input type="text" id="title" name="title">
        </p>
        <p>
            <label><input type="checkbox" name="is_officer" value="1"> Officer</label>
        </p>
        <button type="submit">Add member</button>
    </form>
<?php endif; ?>

<hr>

<h2>Announcements</h2>

<?php foreach ($announcements as $announcement): ?>
    <div style="margin-bottom:1rem; padding:0.75rem; background:#f4f5f7; border-radius:6px;">
        <strong><?= e($announcement['title']) ?></strong>
        <span style="color:#888; font-size:0.85rem;"> &middot; <?= e($announcement['authorName']) ?> &middot; <?= e($announcement['created_at']) ?></span>
        <div style="white-space:pre-wrap; margin:0.5rem 0 0;"><?= raw($announcement['renderedBody']) ?></div>
        <?php if ($canManage): ?>
            <form method="post" action="<?= e(route($base . '/announcements/' . $announcement['id'] . '/delete')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <button type="submit">Delete</button>
            </form>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
<?php if ($announcements === []): ?>
    <p style="color:#888;">No announcements yet.</p>
<?php endif; ?>

<?php if ($canManage): ?>
    <h3>Post an announcement</h3>
    <form method="post" action="<?= e(route($base . '/announcements')) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <p>
            <label for="ann-title">Title</label><br>
            <input type="text" id="ann-title" name="title" required>
        </p>
        <p>
            <textarea name="body" rows="4" cols="60" required data-bbcode-toolbar></textarea><br>
            <small style="color:#666;">Supports [b] [i] [u] [url] [quote] [code]</small>
        </p>
        <button type="submit">Post announcement</button>
    </form>
<?php endif; ?>
