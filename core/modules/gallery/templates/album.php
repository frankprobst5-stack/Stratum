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

<div style="display:flex; flex-wrap:wrap; gap:0.75rem;">
    <?php foreach ($photos as $photo): ?>
        <a href="<?= e(route('/gallery/photos/' . $photo['id'])) ?>">
            <img src="<?= e(route('/gallery/photos/' . $photo['id'] . '/thumbnail')) ?>" alt="<?= e($photo['caption'] ?? '') ?>" width="150">
        </a>
    <?php endforeach; ?>
    <?php if ($photos === []): ?>
        <p style="color:#888;">No photos yet.</p>
    <?php endif; ?>
</div>

<?php if ($canUpload): ?>
    <h3>Add more photos</h3>
    <form method="post" action="<?= e(route('/gallery/albums/' . $album['id'] . '/photos')) ?>" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="file" name="photos[]" multiple required>
        <button type="submit">Upload</button>
    </form>
<?php endif; ?>
