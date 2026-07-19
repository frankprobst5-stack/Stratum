<?php
/**
 * @var array<int, array<string, mixed>> $playlists
 * @var bool $canManage
 */
?>
<p><a href="<?= e(route('/videos')) ?>">&larr; Videos</a></p>
<h1>Playlists</h1>

<?php if ($canManage): ?>
    <p><a href="<?= e(route('/videos/playlists/create')) ?>">+ New playlist</a></p>
<?php endif; ?>

<?php if ($playlists === []): ?>
    <p style="color:#888;">No playlists yet.</p>
<?php else: ?>
    <ul>
        <?php foreach ($playlists as $playlist): ?>
            <li style="margin-bottom:0.5rem;">
                <a href="<?= e(route('/videos/playlists/' . $playlist['slug'])) ?>"><?= e($playlist['title']) ?></a>
                <?php if (!empty($playlist['description'])): ?>
                    <br><small style="color:#666;"><?= e($playlist['description']) ?></small>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
