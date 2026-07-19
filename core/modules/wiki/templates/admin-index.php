<?php
/**
 * @var array<int, array{id: int, name: string, slug: string}> $categories
 * @var array<int, array<string, mixed>> $pages
 * @var string $csrfToken
 */
?>
<h1>Wiki — Categories &amp; Pages</h1>

<h2>Categories</h2>
<ul>
    <?php foreach ($categories as $category): ?>
        <li><?= e($category['name']) ?></li>
    <?php endforeach; ?>
    <?php if ($categories === []): ?>
        <li style="color:#888;">No categories yet.</li>
    <?php endif; ?>
</ul>

<form method="post" action="<?= e(route('/admin/wiki/categories')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <input type="text" name="name" placeholder="Category name" required>
    <button type="submit">Add category</button>
</form>

<h2>Pages</h2>
<table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
    <thead>
        <tr style="text-align:left; border-bottom:1px solid #ddd;">
            <th>Title</th>
            <th>Slug</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($pages as $page): ?>
        <tr style="border-bottom:1px solid #eee;">
            <td><a href="<?= e(route('/wiki/' . $page['slug'])) ?>"><?= e($page['title']) ?></a></td>
            <td><code><?= e($page['slug']) ?></code></td>
            <td>
                <form method="post" action="<?= e(route('/admin/wiki/pages/' . $page['id'] . '/delete')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button type="submit">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if ($pages === []): ?>
        <tr><td colspan="3" style="color:#888;">No pages yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
