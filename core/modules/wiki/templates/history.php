<?php
/**
 * @var array<string, mixed> $page
 * @var array<int, array<string, mixed>> $revisions newest first, each with 'authorName'
 */
?>
<p><a href="<?= e(route('/wiki/' . $page['slug'])) ?>">&larr; <?= e($page['title']) ?></a></p>
<h1>History: <?= e($page['title']) ?></h1>

<div class="strat-list">
    <?php foreach ($revisions as $revision): ?>
        <div class="strat-list-row">
            <div class="strat-list-row-icon" aria-hidden="true">🕓</div>
            <div class="strat-list-row-main">
                <div class="strat-list-row-title">
                    <a href="<?= e(route('/wiki/' . $page['slug'] . '/history/' . $revision['id'])) ?>"><?= e($revision['created_at']) ?></a>
                </div>
                <div class="strat-list-row-meta">
                    by <?= e($revision['authorName']) ?><?php if (!empty($revision['comment'])): ?> &middot; <?= e($revision['comment']) ?><?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
