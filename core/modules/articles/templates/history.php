<?php
/**
 * @var array<string, mixed> $article
 * @var array<int, array<string, mixed>> $revisions newest first, each with 'authorName'
 */
?>
<p><a href="<?= e(route('/articles/' . $article['slug'])) ?>">&larr; <?= e($article['title']) ?></a></p>
<h1>History: <?= e($article['title']) ?></h1>

<table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
    <thead>
        <tr style="text-align:left; border-bottom:1px solid #ddd;">
            <th>When</th>
            <th>Author</th>
            <th>Summary</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($revisions as $revision): ?>
        <tr style="border-bottom:1px solid #eee;">
            <td>
                <a href="<?= e(route('/articles/' . $article['slug'] . '/history/' . $revision['id'])) ?>">
                    <?= e($revision['created_at']) ?>
                </a>
            </td>
            <td><?= e($revision['authorName']) ?></td>
            <td><?= e($revision['comment'] ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
