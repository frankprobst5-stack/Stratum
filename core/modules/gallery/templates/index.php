<?php
/**
 * @var array<int, array<string, mixed>> $albums each with 'photoCount', 'coverPhoto'
 * @var bool $canUpload
 */
?>
<h1>Gallery</h1>

<?php if ($canUpload): ?>
    <p><a href="<?= e(route('/gallery/create')) ?>">Create an album</a></p>
<?php endif; ?>

<?php if ($albums === []): ?>
    <p class="strat-muted">No albums yet.</p>
<?php else: ?>
    <div class="strat-photo-grid">
        <?php foreach ($albums as $album): ?>
            <a class="strat-photo-tile" href="<?= e(route('/gallery/albums/' . $album['id'])) ?>">
                <?php if ($album['coverPhoto'] !== null): ?>
                    <img src="<?= e(route('/gallery/photos/' . $album['coverPhoto']['id'] . '/thumbnail')) ?>" alt="">
                <?php endif; ?>
                <div class="strat-photo-tile-caption">
                    <strong><?= e($album['title']) ?></strong>
                    <span class="strat-muted"><?= (int) $album['photoCount'] ?> photos</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
