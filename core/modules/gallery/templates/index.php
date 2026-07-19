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

<ul>
    <?php foreach ($albums as $album): ?>
        <li style="margin-bottom:1rem;">
            <?php if ($album['coverPhoto'] !== null): ?>
                <a href="<?= e(route('/gallery/albums/' . $album['id'])) ?>">
                    <img src="<?= e(route('/gallery/photos/' . $album['coverPhoto']['id'] . '/thumbnail')) ?>" alt="" width="150">
                </a>
            <?php endif; ?>
            <br>
            <a href="<?= e(route('/gallery/albums/' . $album['id'])) ?>"><?= e($album['title']) ?></a>
            <small style="color:#888;">(<?= (int) $album['photoCount'] ?> photos)</small>
        </li>
    <?php endforeach; ?>
    <?php if ($albums === []): ?>
        <li style="color:#888;">No albums yet.</li>
    <?php endif; ?>
</ul>
