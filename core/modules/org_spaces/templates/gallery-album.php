<?php
/**
 * @var array<string, mixed> $org
 * @var array<string, mixed> $album
 * @var array<int, array<string, mixed>> $photos
 * @var bool $canManage
 * @var string $csrfToken
 */
$base = '/organizations/' . $org['slug'];
?>
<p><a href="<?= e(route($base . '/gallery')) ?>">&larr; <?= e($org['name']) ?> Gallery</a></p>

<h1><?= e($album['title']) ?></h1>
<?php if (!empty($album['description'])): ?>
    <p><?= e($album['description']) ?></p>
<?php endif; ?>

<?php if ($canManage): ?>
    <form method="post" action="<?= e(route($base . '/gallery/albums/' . $album['id'] . '/delete')) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <button type="submit">Delete album</button>
    </form>
<?php endif; ?>

<?php if ($photos === []): ?>
    <p style="color:#888;">No photos in this album.</p>
<?php else: ?>
    <div style="display:flex; flex-wrap:wrap; gap:0.75rem;">
        <?php foreach ($photos as $photo): ?>
            <div style="width:160px;">
                <a href="<?= e(route($base . '/gallery/photos/' . $photo['id'] . '/image')) ?>">
                    <img src="<?= e(route($base . '/gallery/photos/' . $photo['id'] . '/thumbnail')) ?>" alt="" style="width:100%; border-radius:4px;">
                </a>
                <?php if ($canManage): ?>
                    <form method="post" action="<?= e(route($base . '/gallery/photos/' . $photo['id'] . '/delete')) ?>">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit">Delete</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
