<?php
/**
 * @var array<int, array{id: int, name: string, slug: string, count: int}> $tags
 */
?>
<h1>Tags</h1>

<?php if ($tags === []): ?>
    <p style="color:#888;">No tags yet.</p>
<?php else: ?>
    <p>
        <?php foreach ($tags as $tag): ?>
            <a href="<?= e(route('/tags/' . $tag['slug'])) ?>" style="display:inline-block; margin:0 0.5rem 0.5rem 0; padding:0.25rem 0.6rem; background:#eef1f7; border-radius:12px; text-decoration:none; color:#2f6fed;">
                <?= e($tag['name']) ?> <small style="color:#888;">(<?= $tag['count'] ?>)</small>
            </a>
        <?php endforeach; ?>
    </p>
<?php endif; ?>
