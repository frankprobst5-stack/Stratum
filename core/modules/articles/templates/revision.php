<?php
/**
 * @var array<string, mixed> $article
 * @var array<string, mixed> $revision
 * @var string $renderedBody
 * @var string $authorName
 * @var bool $canManage
 * @var string $csrfToken
 */
?>
<p>
    <a href="<?= e(route('/articles/' . $article['slug'] . '/history')) ?>">&larr; History</a>
    &middot;
    <a href="<?= e(route('/articles/' . $article['slug'])) ?>">Current version</a>
</p>

<h1><?= e($article['title']) ?> <small style="color:#888; font-weight:normal;">(revision from <?= e($revision['created_at']) ?>)</small></h1>
<p style="color:#666; font-size:0.9rem;">
    by <?= e($authorName) ?>
    <?php if (!empty($revision['comment'])): ?>
        &middot; <?= e($revision['comment']) ?>
    <?php endif; ?>
</p>

<div style="white-space:pre-wrap;"><?= raw($renderedBody) ?></div>

<?php if ($canManage): ?>
    <form method="post" action="<?= e(route('/articles/' . $article['slug'] . '/history/' . $revision['id'] . '/restore')) ?>" style="margin-top:1.5rem;">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <button type="submit">Restore this revision</button>
    </form>
<?php endif; ?>
