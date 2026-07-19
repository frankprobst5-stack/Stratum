<?php
/**
 * @var array<int, array<string, mixed>> $articles
 * @var array<int, array{id: int, name: string, slug: string}> $categories
 * @var string $csrfToken
 */
?>
<h1>Articles</h1>

<p><a href="<?= e(route('/admin/articles/create')) ?>">+ New article</a></p>

<table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
    <thead>
        <tr style="text-align:left; border-bottom:1px solid #ddd;">
            <th>Title</th>
            <th>Status</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($articles as $article):
        $now = date('Y-m-d H:i:s');
        $isScheduled = !$article['is_published'] && $article['published_at'] !== null && $article['published_at'] > $now;
        $status = $article['is_published'] ? 'Published' : ($isScheduled ? 'Scheduled for ' . $article['published_at'] : 'Draft');
    ?>
        <tr style="border-bottom:1px solid #eee;">
            <td><?= e($article['title']) ?></td>
            <td><?= e($status) ?></td>
            <td>
                <a href="<?= e(route('/admin/articles/' . $article['id'] . '/edit')) ?>">Edit</a>
                &middot;
                <form method="post" action="<?= e(route('/admin/articles/' . $article['id'] . '/delete')) ?>" style="display:inline;">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button type="submit">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h2>Categories</h2>
<ul>
    <?php foreach ($categories as $category): ?>
        <li><?= e($category['name']) ?></li>
    <?php endforeach; ?>
</ul>

<form method="post" action="<?= e(route('/admin/articles/categories')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <input type="text" name="name" placeholder="Category name" required>
    <button type="submit">Add category</button>
</form>
