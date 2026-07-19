<?php
/**
 * @var array<int, array<string, mixed>> $pages
 * @var string $csrfToken
 */
?>
<h1>Pages</h1>

<p><a href="<?= e(route('/admin/pages/create')) ?>">+ New page</a></p>

<table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
    <thead>
        <tr style="text-align:left; border-bottom:1px solid #ddd;">
            <th>Title</th>
            <th>Slug</th>
            <th>Status</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($pages as $page): ?>
        <tr style="border-bottom:1px solid #eee;">
            <td><?= e($page['title']) ?></td>
            <td><code><?= e($page['slug']) ?></code></td>
            <td><?= $page['is_published'] ? 'Published' : 'Draft' ?></td>
            <td>
                <a href="<?= e(route('/admin/pages/' . $page['id'] . '/edit')) ?>">Edit</a>
                &middot;
                <form method="post" action="<?= e(route('/admin/pages/' . $page['id'] . '/delete')) ?>" style="display:inline;">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button type="submit">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
