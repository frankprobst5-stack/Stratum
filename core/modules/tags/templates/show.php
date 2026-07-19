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
    <p style="color:#888;">Nothing tagged with this yet.</p>
<?php else: ?>
    <ul>
        <?php foreach ($items as $item): ?>
            <li style="margin-bottom:0.5rem;">
                <small style="color:#888;">[<?= e($typeLabels[$item['type']] ?? $item['type']) ?>]</small>
                <a href="<?= e(route($item['url'])) ?>"><?= e($item['title']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
