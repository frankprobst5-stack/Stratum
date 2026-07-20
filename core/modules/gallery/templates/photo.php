<?php
/**
 * @var array<string, mixed> $photo
 * @var array{camera: string, takenAt: ?string}|null $exif
 * @var array<int, array<string, mixed>> $comments each with 'authorName'
 * @var int $likeCount
 * @var bool $hasLiked
 * @var bool $canComment
 * @var bool $canManage
 * @var bool $isLoggedIn
 * @var string $csrfToken
 */
?>
<p><a href="<?= e(route('/gallery/albums/' . $photo['album_id'])) ?>">&larr; Back to album</a></p>

<img src="<?= e(route('/gallery/photos/' . $photo['id'] . '/image')) ?>" alt="<?= e($photo['caption'] ?? '') ?>" style="max-width:100%;">

<?php if (!empty($photo['caption'])): ?>
    <p><?= e($photo['caption']) ?></p>
<?php endif; ?>

<?php if ($exif !== null): ?>
    <p class="strat-muted">
        <?php if ($exif['camera'] !== ''): ?>Camera: <?= e($exif['camera']) ?><?php endif; ?>
        <?php if ($exif['takenAt'] !== null): ?> &middot; Taken: <?= e($exif['takenAt']) ?><?php endif; ?>
    </p>
<?php endif; ?>

<?php if ($canManage): ?>
    <form method="post" action="<?= e(route('/gallery/photos/' . $photo['id'] . '/delete')) ?>" style="display:inline;">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <button type="submit">Delete photo</button>
    </form>
<?php endif; ?>

<?php if ($isLoggedIn): ?>
    <form method="post" action="<?= e(route('/gallery/photos/' . $photo['id'] . '/like')) ?>" style="display:inline;">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <button type="submit"><?= $hasLiked ? 'Unlike' : 'Like' ?></button>
    </form>
<?php endif; ?>
<span><?= (int) $likeCount ?> likes</span>

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
        <input type="hidden" name="commentable_type" value="gallery_photo">
        <input type="hidden" name="commentable_id" value="<?= (int) $photo['id'] ?>">
        <input type="hidden" name="redirect_to" value="<?= e(route('/gallery/photos/' . $photo['id'])) ?>">
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
