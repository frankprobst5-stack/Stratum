<?php
/**
 * @var array<string, mixed> $board
 * @var array<int, array<string, mixed>> $moderators each with 'id', 'username'
 * @var string $csrfToken
 */
$base = '/admin/forum/boards/' . $board['id'] . '/moderators';
?>
<p><a href="<?= e(route('/admin/forum')) ?>">&larr; Forum admin</a></p>

<h1>Moderators — <?= e($board['name']) ?></h1>

<ul>
    <?php foreach ($moderators as $moderator): ?>
        <li>
            <?= e($moderator['username']) ?>
            <form method="post" action="<?= e(route($base . '/' . $moderator['id'] . '/remove')) ?>" style="display:inline;">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <button type="submit">Remove</button>
            </form>
        </li>
    <?php endforeach; ?>
    <?php if ($moderators === []): ?>
        <li style="color:#888;">No board-specific moderators yet — site-wide moderators still apply here.</li>
    <?php endif; ?>
</ul>

<h2>Add a moderator</h2>
<form method="post" action="<?= e(route($base)) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <label for="username">Username</label><br>
    <input type="text" id="username" name="username" required>
    <button type="submit">Add moderator</button>
</form>
