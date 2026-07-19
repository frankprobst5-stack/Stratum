<?php
/**
 * @var array<int, array{id: int, name: string, slug: string}> $categories
 * @var array<int, array<string, mixed>> $links each with 'categoryName', 'submitterName'
 * @var string $csrfToken
 */
?>
<h1>Link Directory — Categories &amp; Links</h1>

<h2>Categories</h2>
<ul>
    <?php foreach ($categories as $category): ?>
        <li><?= e($category['name']) ?></li>
    <?php endforeach; ?>
    <?php if ($categories === []): ?>
        <li style="color:#888;">No categories yet.</li>
    <?php endif; ?>
</ul>

<form method="post" action="<?= e(route('/admin/links/categories')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <input type="text" name="name" placeholder="Category name" required>
    <button type="submit">Add category</button>
</form>

<h2>Links</h2>
<table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
    <thead>
        <tr style="text-align:left; border-bottom:1px solid #ddd;">
            <th>Title</th>
            <th>Category</th>
            <th>Submitted by</th>
            <th>Clicks</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($links as $link): ?>
        <tr style="border-bottom:1px solid #eee;">
            <td><a href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($link['title']) ?></a></td>
            <td><?= e($link['categoryName']) ?></td>
            <td><?= e($link['submitterName']) ?></td>
            <td><?= (int) $link['click_count'] ?></td>
            <td>
                <form method="post" action="<?= e(route('/admin/links/' . $link['id'] . '/delete')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button type="submit">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if ($links === []): ?>
        <tr><td colspan="5" style="color:#888;">No links yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
