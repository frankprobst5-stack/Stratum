<?php
/**
 * @var array<int, array<string, mixed>> $categories each with 'videos'
 * @var string $csrfToken
 */
?>
<h1>Videos</h1>

<?php foreach ($categories as $category): ?>
    <h2><?= e($category['name']) ?></h2>
    <ul>
        <?php foreach ($category['videos'] as $video): ?>
            <li>
                <a href="<?= e(route('/videos/' . $video['id'])) ?>"><?= e($video['title']) ?></a>
                <small style="color:#888;">(<?= e($video['source_type']) ?>)</small>
                <form method="post" action="<?= e(route('/admin/video/' . $video['id'] . '/delete')) ?>" style="display:inline;">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button type="submit">Delete</button>
                </form>
            </li>
        <?php endforeach; ?>
        <?php if ($category['videos'] === []): ?>
            <li style="color:#888;">No videos yet.</li>
        <?php endif; ?>
    </ul>
<?php endforeach; ?>
<?php if ($categories === []): ?>
    <p style="color:#888;">No categories yet.</p>
<?php endif; ?>

<h2>Add a category</h2>
<form method="post" action="<?= e(route('/admin/video/categories')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <input type="text" name="name" placeholder="Category name" required>
    <button type="submit">Add category</button>
</form>
