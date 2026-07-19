<?php
/**
 * @var array<string, mixed> $playlist
 * @var array<int, array<string, mixed>> $items each a video row plus 'item_id'/'position'
 * @var array<int, array<string, mixed>> $allVideos
 * @var bool $canManage
 * @var string $csrfToken
 */
$itemVideoIds = array_column($items, 'id');
$addableVideos = array_filter($allVideos, static fn (array $v): bool => !in_array((int) $v['id'], $itemVideoIds, true));
?>
<p><a href="<?= e(route('/videos/playlists')) ?>">&larr; Playlists</a></p>
<h1><?= e($playlist['title']) ?></h1>
<?php if (!empty($playlist['description'])): ?>
    <p style="color:#666;"><?= nl2br(e($playlist['description'])) ?></p>
<?php endif; ?>

<?php if ($canManage): ?>
    <form method="post" action="<?= e(route('/videos/playlists/' . $playlist['id'] . '/delete')) ?>" onsubmit="return confirm('Delete this playlist? The videos themselves are not deleted.');">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <button type="submit" style="color:#b00020;">Delete playlist</button>
    </form>
<?php endif; ?>

<?php if ($items === []): ?>
    <p style="color:#888;">No videos in this playlist yet.</p>
<?php else: ?>
    <ol>
        <?php foreach ($items as $i => $item): ?>
            <li style="margin-bottom:0.5rem;">
                <a href="<?= e(route('/videos/' . $item['id'])) ?>"><?= e($item['title']) ?></a>
                <small style="color:#888;">(<?= (int) $item['view_count'] ?> views)</small>
                <?php if ($canManage): ?>
                    <?php if ($i > 0): ?>
                        <form method="post" action="<?= e(route('/videos/playlists/' . $playlist['id'] . '/videos/' . $item['item_id'] . '/move')) ?>" style="display:inline;">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="direction" value="up">
                            <button type="submit" style="border:none; background:none; color:#888; cursor:pointer; padding:0;">↑</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($i < count($items) - 1): ?>
                        <form method="post" action="<?= e(route('/videos/playlists/' . $playlist['id'] . '/videos/' . $item['item_id'] . '/move')) ?>" style="display:inline;">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="direction" value="down">
                            <button type="submit" style="border:none; background:none; color:#888; cursor:pointer; padding:0;">↓</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="<?= e(route('/videos/playlists/' . $playlist['id'] . '/videos/' . $item['item_id'] . '/remove')) ?>" style="display:inline;">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit" style="border:none; background:none; color:#888; text-decoration:underline; cursor:pointer; padding:0; font-size:0.85rem;">Remove</button>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>

<?php if ($canManage && $addableVideos !== []): ?>
    <h3>Add a video</h3>
    <form method="post" action="<?= e(route('/videos/playlists/' . $playlist['id'] . '/videos')) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <select name="video_id" required>
            <?php foreach ($addableVideos as $video): ?>
                <option value="<?= (int) $video['id'] ?>"><?= e($video['title']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Add</button>
    </form>
<?php endif; ?>
