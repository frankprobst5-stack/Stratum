<?php
/**
 * @var array<string, mixed> $org
 * @var array<string, mixed> $topic
 * @var array<int, array<string, mixed>> $posts each with 'authorName', 'renderedBody'
 * @var bool $canManage
 * @var string $csrfToken
 */
$base = '/organizations/' . $org['slug'];
?>
<p><a href="<?= e(route($base . '/forum')) ?>">&larr; <?= e($org['name']) ?> Forum</a></p>

<h1>
    <?php if ($topic['is_locked']): ?><strong>[Locked]</strong> <?php endif; ?>
    <?= e($topic['title']) ?>
</h1>

<?php if ($canManage): ?>
    <p>
        <form method="post" action="<?= e(route($base . '/forum/topics/' . $topic['id'] . '/' . ($topic['is_locked'] ? 'unlock' : 'lock'))) ?>" style="display:inline;">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <button type="submit"><?= $topic['is_locked'] ? 'Unlock' : 'Lock' ?></button>
        </form>
        <form method="post" action="<?= e(route($base . '/forum/topics/' . $topic['id'] . '/delete')) ?>" style="display:inline;">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <button type="submit">Delete topic</button>
        </form>
    </p>
<?php endif; ?>

<?php foreach ($posts as $post): ?>
    <div style="margin-bottom:1rem; padding:0.75rem; background:#f4f5f7; border-radius:6px;">
        <strong><?= e($post['authorName']) ?></strong>
        <span style="color:#888; font-size:0.85rem;"> &middot; <?= e($post['created_at']) ?></span>
        <div style="white-space:pre-wrap; margin:0.5rem 0 0;"><?= raw($post['renderedBody']) ?></div>
        <?php if ($canManage): ?>
            <form method="post" action="<?= e(route($base . '/forum/posts/' . $post['id'] . '/delete')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <button type="submit">Delete post</button>
            </form>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<?php if ($topic['is_locked'] && !$canManage): ?>
    <p style="color:#888;">This topic is locked.</p>
<?php else: ?>
    <h2>Reply</h2>
    <form method="post" action="<?= e(route($base . '/forum/topics/' . $topic['id'] . '/reply')) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <textarea name="body" rows="4" cols="60" required data-bbcode-toolbar></textarea><br>
        <small style="color:#666;">Supports [b] [i] [u] [url] [quote] [code]</small><br>
        <button type="submit">Post reply</button>
    </form>
<?php endif; ?>
