<?php
/**
 * @var array<int, array<string, mixed>> $categories each with 'files'
 * @var string $csrfToken
 */
?>
<h1>Downloads</h1>

<?php foreach ($categories as $category): ?>
    <h2><?= e($category['name']) ?></h2>
    <ul>
        <?php foreach ($category['files'] as $file): ?>
            <li>
                <a href="<?= e(route('/downloads/files/' . $file['id'])) ?>"><?= e($file['title']) ?></a>
                <form method="post" action="<?= e(route('/admin/downloads/files/' . $file['id'] . '/delete')) ?>" style="display:inline;">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button type="submit">Delete</button>
                </form>
            </li>
        <?php endforeach; ?>
        <?php if ($category['files'] === []): ?>
            <li style="color:#888;">No files yet.</li>
        <?php endif; ?>
    </ul>
<?php endforeach; ?>
<?php if ($categories === []): ?>
    <p style="color:#888;">No categories yet.</p>
<?php endif; ?>

<h2>Add a category</h2>
<form method="post" action="<?= e(route('/admin/downloads/categories')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <input type="text" name="name" placeholder="Category name" required>
    <button type="submit">Add category</button>
</form>
