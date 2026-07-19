<?php
/**
 * @var array<string, mixed> $video
 * @var array<int, array<string, mixed>> $comments each with 'authorName'
 * @var bool $canComment
 * @var bool $isLoggedIn
 * @var string $csrfToken
 */
?>
<p><a href="<?= e(route('/videos')) ?>">&larr; Videos</a></p>

<h1><?= e($video['title']) ?></h1>

<?php if ($video['source_type'] === 'youtube'): ?>
    <iframe
        width="640" height="360"
        src="<?= e('https://www.youtube.com/embed/' . $video['external_id']) ?>"
        title="<?= e($video['title']) ?>"
        frameborder="0"
        allowfullscreen
    ></iframe>
<?php elseif ($video['source_type'] === 'vimeo'): ?>
    <iframe
        width="640" height="360"
        src="<?= e('https://player.vimeo.com/video/' . $video['external_id']) ?>"
        title="<?= e($video['title']) ?>"
        frameborder="0"
        allowfullscreen
    ></iframe>
<?php else: ?>
    <video width="640" height="360" controls>
        <source src="<?= e(route('/videos/' . $video['id'] . '/stream')) ?>" type="<?= e($video['mime_type']) ?>">
    </video>
<?php endif; ?>

<p style="color:#888;"><?= (int) $video['view_count'] ?> views</p>

<?php if (!empty($video['description'])): ?>
    <div style="white-space:pre-wrap;"><?= e($video['description']) ?></div>
<?php endif; ?>

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
        <input type="hidden" name="commentable_type" value="video">
        <input type="hidden" name="commentable_id" value="<?= (int) $video['id'] ?>">
        <input type="hidden" name="redirect_to" value="<?= e(route('/videos/' . $video['id'])) ?>">
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
