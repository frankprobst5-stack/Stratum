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

    <p class="strat-muted">
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
                <a class="strat-pill" data-tone="accent" href="<?= e(route('/tags/' . $tag['slug'])) ?>"><?= e($tag['name']) ?></a>
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
    <div class="strat-inline-box">
        <div class="strat-inline-box-meta"><strong><?= e($comment['authorName']) ?></strong> <span class="strat-muted">&middot; <?= e($comment['created_at']) ?></span></div>
        <p class="strat-inline-box-body"><?= e($comment['body']) ?></p>
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
    <p class="strat-muted">You don't have permission to comment.</p>
<?php else: ?>
    <p><a href="<?= e(route('/login')) ?>">Log in</a> to comment.</p>
<?php endif; ?>
