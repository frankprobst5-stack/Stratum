<?php
/**
 * @var array<int, array<string, mixed>> $categories each with 'videos'
 * @var bool $canUpload
 */
?>
<h1>Videos</h1>

<p>
    <?php if ($canUpload): ?><a href="<?= e(route('/videos/create')) ?>">Add a video</a> &middot; <?php endif; ?>
    <a href="<?= e(route('/videos/playlists')) ?>">Playlists</a>
</p>

<?php foreach ($categories as $category): ?>
    <h2><?= e($category['name']) ?></h2>
    <ul>
        <?php foreach ($category['videos'] as $video): ?>
            <li>
                <?php if ($video['source_type'] === 'youtube'): ?>
                    <a href="<?= e(route('/videos/' . $video['id'])) ?>">
                        <img src="<?= e('https://img.youtube.com/vi/' . $video['external_id'] . '/hqdefault.jpg') ?>" alt="" width="120">
                    </a>
                <?php endif; ?>
                <a href="<?= e(route('/videos/' . $video['id'])) ?>"><?= e($video['title']) ?></a>
                <small style="color:#888;">(<?= (int) $video['view_count'] ?> views)</small>
            </li>
        <?php endforeach; ?>
        <?php if ($category['videos'] === []): ?>
            <li style="color:#888;">No videos yet.</li>
        <?php endif; ?>
    </ul>
<?php endforeach; ?>
<?php if ($categories === []): ?>
    <p style="color:#888;">No categories yet.</p>
<?php endif; ?>
