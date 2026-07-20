<?php
/**
 * @var array<int, array{id: int, name: string, slug: string, count: int}> $tags
 */
?>
<h1>Tags</h1>

<?php if ($tags === []): ?>
    <p class="strat-muted">No tags yet.</p>
<?php else: ?>
    <p>
        <?php foreach ($tags as $tag): ?>
            <a class="strat-pill" data-tone="accent" href="<?= e(route('/tags/' . $tag['slug'])) ?>"><?= e($tag['name']) ?> (<?= $tag['count'] ?>)</a>
        <?php endforeach; ?>
    </p>
<?php endif; ?>
