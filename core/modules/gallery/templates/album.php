<?php
/**
 * @var array<string, mixed> $album
 * @var array<int, array<string, mixed>> $photos
 * @var bool $canUpload
 * @var bool $canManage
 * @var string $csrfToken
 */
?>
<p><a href="<?= e(route('/gallery')) ?>">&larr; Gallery</a></p>

<h1><?= e($album['title']) ?></h1>

<?php if (!empty($album['description'])): ?>
    <div style="white-space:pre-wrap;"><?= e($album['description']) ?></div>
<?php endif; ?>

<?php if ($canManage): ?>
    <form method="post" action="<?= e(route('/gallery/albums/' . $album['id'] . '/delete')) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <button type="submit">Delete album</button>
    </form>
<?php endif; ?>

<?php if ($photos === []): ?>
    <p class="strat-muted">No photos yet.</p>
<?php else: ?>
    <div class="strat-photo-grid">
        <?php foreach ($photos as $photo): ?>
            <a class="strat-photo-tile" href="<?= e(route('/gallery/photos/' . $photo['id'])) ?>">
                <img src="<?= e(route('/gallery/photos/' . $photo['id'] . '/thumbnail')) ?>" alt="<?= e($photo['caption'] ?? '') ?>">
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($canUpload): ?>
    <h3>Add more photos</h3>
    <form method="post" action="<?= e(route('/gallery/albums/' . $album['id'] . '/photos')) ?>" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="file" name="photos[]" multiple required>
        <button type="submit">Upload</button>
    </form>
<?php endif; ?>
