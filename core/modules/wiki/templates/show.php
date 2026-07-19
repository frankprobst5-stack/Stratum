<?php
/**
 * @var array<string, mixed> $page
 * @var array<string, mixed>|null $revision
 * @var string $renderedBody
 * @var string $authorName
 * @var array<int, array<string, mixed>> $comments each with 'authorName'
 * @var bool $canComment
 * @var bool $canEdit
 * @var bool $isLoggedIn
 * @var bool $showBookmark
 * @var bool $isBookmarked
 * @var array<int, array{name: string, slug: string}> $tags
 * @var string $csrfToken
 */
?>
<article>
    <h1><?= e($page['title']) ?></h1>

    <p style="color:#666; font-size:0.9rem;">
        <?php if ($revision !== null): ?>
            last edited by <?= e($authorName) ?> &middot; <?= e($revision['created_at']) ?> &middot;
        <?php endif; ?>
        <a href="<?= e(route('/wiki/' . $page['slug'] . '/history')) ?>">History</a>
        <?php if ($canEdit): ?>
            &middot; <a href="<?= e(route('/wiki/' . $page['slug'] . '/edit')) ?>">Edit</a>
        <?php endif; ?>
    </p>

    <?php if ($tags !== []): ?>
        <p>
            <?php foreach ($tags as $tag): ?>
                <a href="<?= e(route('/tags/' . $tag['slug'])) ?>" style="display:inline-block; margin:0 0.4rem 0.4rem 0; padding:0.2rem 0.55rem; background:#eef1f7; border-radius:12px; text-decoration:none; color:#2f6fed; font-size:0.85rem;"><?= e($tag['name']) ?></a>
            <?php endforeach; ?>
        </p>
    <?php endif; ?>

    <?php if ($showBookmark): ?>
        <form method="post" action="<?= e(route('/bookmarks/toggle')) ?>" style="margin-bottom:0.75rem;">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="bookmarkable_type" value="wiki_page">
            <input type="hidden" name="bookmarkable_id" value="<?= (int) $page['id'] ?>">
            <input type="hidden" name="redirect_to" value="<?= e(route('/wiki/' . $page['slug'])) ?>">
            <button type="submit"><?= $isBookmarked ? '&#9733; Bookmarked' : '&#9734; Bookmark' ?></button>
        </form>
    <?php endif; ?>

    <div style="white-space:pre-wrap;"><?= raw($renderedBody) ?></div>
</article>

<hr>

<h2>Comments (<?= count($comments) ?>)</h2>

<?php foreach ($comments as $comment): ?>
    <div style="margin-bottom:1rem; padding:0.75rem; background:#f4f5f7; border-radius:6px;">
        <strong><?= e($comment['authorName']) ?></strong>
        <span style="color:#888; font-size:0.85rem;"> &middot; <?= e($comment['created_at']) ?></span>
        <p style="white-space:pre-wrap; margin:0.5rem 0 0;"><?= e($comment['body']) ?></p>
    </div>
<?php endforeach; ?>

<?php if ($canComment): ?>
    <form method="post" action="<?= e(route('/comments')) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="commentable_type" value="wiki_page">
        <input type="hidden" name="commentable_id" value="<?= (int) $page['id'] ?>">
        <input type="hidden" name="redirect_to" value="<?= e(route('/wiki/' . $page['slug'])) ?>">
        <p>
            <label for="body">Add a comment</label><br>
            <textarea id="body" name="body" rows="3" cols="50" required></textarea>
        </p>
        <button type="submit">Post comment</button>
    </form>
<?php elseif ($isLoggedIn): ?>
    <p style="color:#888;">You don't have permission to comment.</p>
<?php else: ?>
    <p><a href="<?= e(route('/login')) ?>">Log in</a> to comment.</p>
<?php endif; ?>
