<?php
/**
 * @var array<int, array<string, mixed>> $pages each with 'categoryName'
 */
?>
<h1>Wiki</h1>

<p><a href="<?= e(route('/wiki/create')) ?>">+ New page</a></p>

<?php if ($pages === []): ?>
    <p>No wiki pages yet.</p>
<?php endif; ?>

<ul>
    <?php foreach ($pages as $page): ?>
        <li>
            <a href="<?= e(route('/wiki/' . $page['slug'])) ?>"><?= e($page['title']) ?></a>
            <?php if (!empty($page['categoryName'])): ?>
                <small style="color:#888;">(<?= e($page['categoryName']) ?>)</small>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
