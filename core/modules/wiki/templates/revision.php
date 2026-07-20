<?php
/**
 * @var array<string, mixed> $page
 * @var array<string, mixed> $revision
 * @var string $renderedBody
 * @var string $authorName
 * @var bool $canEdit
 * @var string $csrfToken
 */
?>
<p>
    <a href="<?= e(route('/wiki/' . $page['slug'] . '/history')) ?>">&larr; History</a>
    &middot;
    <a href="<?= e(route('/wiki/' . $page['slug'])) ?>">Current version</a>
</p>

<h1><?= e($page['title']) ?> <small class="strat-muted" style="font-weight:normal;">(revision from <?= e($revision['created_at']) ?>)</small></h1>
<p class="strat-muted">
    by <?= e($authorName) ?>
    <?php if (!empty($revision['comment'])): ?>
        &middot; <?= e($revision['comment']) ?>
    <?php endif; ?>
</p>

<div style="white-space:pre-wrap;"><?= raw($renderedBody) ?></div>

<?php if ($canEdit): ?>
    <form method="post" action="<?= e(route('/wiki/' . $page['slug'] . '/history/' . $revision['id'] . '/restore')) ?>" style="margin-top:1.5rem;">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <button type="submit">Restore this revision</button>
    </form>
<?php endif; ?>
