<?php
/**
 * @var array<int, array<string, mixed>> $pages each with 'categoryName'
 */
?>
<h1>Wiki</h1>

<p><a href="<?= e(route('/wiki/create')) ?>">+ New page</a></p>

<?php if ($pages === []): ?>
    <p class="strat-muted">No wiki pages yet.</p>
<?php else: ?>
    <div class="strat-list">
        <?php foreach ($pages as $page): ?>
            <div class="strat-list-row">
                <div class="strat-list-row-icon" aria-hidden="true">📄</div>
                <div class="strat-list-row-main">
                    <div class="strat-list-row-title">
                        <a href="<?= e(route('/wiki/' . $page['slug'])) ?>"><?= e($page['title']) ?></a>
                    </div>
                    <?php if (!empty($page['categoryName'])): ?>
                        <div class="strat-list-row-meta"><?= e($page['categoryName']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
