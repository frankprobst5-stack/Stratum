<?php
/**
 * @var array<int, array<string, mixed>> $issues published issues, newest first
 */
?>
<h1>Newsletter</h1>

<?php if ($issues === []): ?>
    <p class="strat-muted">No issues published yet.</p>
<?php else: ?>
    <div class="strat-list">
        <?php foreach ($issues as $issue): ?>
            <div class="strat-list-row">
                <div class="strat-list-row-icon" aria-hidden="true">📰</div>
                <div class="strat-list-row-main">
                    <div class="strat-list-row-title">
                        <a href="<?= e(route('/newsletter/' . $issue['slug'])) ?>"><?= e($issue['title']) ?></a>
                    </div>
                    <div class="strat-list-row-meta"><?= e($issue['published_at']) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
