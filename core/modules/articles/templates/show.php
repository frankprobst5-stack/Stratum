<?php
/**
 * @var array<string, mixed> $article with 'authorName', 'renderedBody'
 * @var array<int, array<string, mixed>> $comments each with 'authorName'
 * @var bool $canComment
 * @var bool $isLoggedIn
 * @var bool $showBookmark
 * @var bool $isBookmarked
 * @var bool $showRatings
 * @var bool $canRate
 * @var array{average: float, count: int}|null $ratingSummary
 * @var ?int $myRating
 * @var bool $canManage
 * @var array<int, array{name: string, slug: string}> $tags
 * @var string $csrfToken
 */
?>
<article>
    <h1><?= e($article['title']) ?></h1>
    <p style="color:#666; font-size:0.9rem;">
        by <?= e($article['authorName']) ?> &middot; <?= e($article['published_at']) ?>
        &middot; <a href="<?= e(route('/articles/' . $article['slug'] . '/history')) ?>">History</a>
        <?php if ($canManage): ?>
            &middot; <a href="<?= e(route('/admin/articles/' . $article['id'] . '/edit')) ?>">Edit</a>
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
            <input type="hidden" name="bookmarkable_type" value="article">
            <input type="hidden" name="bookmarkable_id" value="<?= (int) $article['id'] ?>">
            <input type="hidden" name="redirect_to" value="<?= e(route('/articles/' . $article['slug'])) ?>">
            <button type="submit"><?= $isBookmarked ? '&#9733; Bookmarked' : '&#9734; Bookmark' ?></button>
        </form>
    <?php endif; ?>
    <?php if ($showRatings): ?>
        <div style="margin-bottom:0.75rem;">
            <?php if ($ratingSummary['count'] > 0): ?>
                <strong><?= number_format($ratingSummary['average'], 1) ?></strong> / 5
                <small style="color:#888;">(<?= $ratingSummary['count'] ?> rating<?= $ratingSummary['count'] === 1 ? '' : 's' ?>)</small>
            <?php else: ?>
                <small style="color:#888;">No ratings yet.</small>
            <?php endif; ?>
            <?php if ($canRate): ?>
                <form method="post" action="<?= e(route('/ratings')) ?>" style="display:inline-block; margin-left:0.5rem;">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="ratable_type" value="article">
                    <input type="hidden" name="ratable_id" value="<?= (int) $article['id'] ?>">
                    <input type="hidden" name="redirect_to" value="<?= e(route('/articles/' . $article['slug'])) ?>">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <button type="submit" name="score" value="<?= $i ?>" style="border:none; background:none; cursor:pointer; font-size:1.1rem;" title="Rate <?= $i ?>">
                            <?= $myRating !== null && $i <= $myRating ? '&#9733;' : '&#9734;' ?>
                        </button>
                    <?php endfor; ?>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <div style="white-space:pre-wrap;"><?= raw($article['renderedBody']) ?></div>
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
        <input type="hidden" name="commentable_type" value="article">
        <input type="hidden" name="commentable_id" value="<?= (int) $article['id'] ?>">
        <input type="hidden" name="redirect_to" value="<?= e(route('/articles/' . $article['slug'])) ?>">
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
