<?php
/**
 * @var array<string, mixed> $tag
 * @var array<int, array{type: string, title: string, url: string}> $items
 */
$typeLabels = [
    'article' => 'Article',
    'wiki_page' => 'Wiki',
    'forum_topic' => 'Forum',
    'forum_post' => 'Forum reply',
];
?>
<p><a href="<?= e(route('/tags')) ?>">&larr; Tags</a></p>
<h1>Tagged &ldquo;<?= e($tag['name']) ?>&rdquo;</h1>

<?php if ($items === []): ?>
    <p class="strat-muted">Nothing tagged with this yet.</p>
<?php else: ?>
    <div class="strat-list">
        <?php foreach ($items as $item): ?>
            <div class="strat-list-row">
                <div class="strat-list-row-main">
                    <div class="strat-list-row-title">
                        <a href="<?= e(route($item['url'])) ?>"><?= e($item['title']) ?></a>
                    </div>
                </div>
                <span class="strat-pill" data-tone="neutral"><?= e($typeLabels[$item['type']] ?? $item['type']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
