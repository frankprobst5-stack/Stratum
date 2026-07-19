<?php
/**
 * @var array<int, array{id: int, name: string, slug: string}> $categories
 * @var string $csrfToken
 */
?>
<h1>Classifieds</h1>

<ul>
    <?php foreach ($categories as $category): ?>
        <li><?= e($category['name']) ?></li>
    <?php endforeach; ?>
    <?php if ($categories === []): ?>
        <li style="color:#888;">No categories yet.</li>
    <?php endif; ?>
</ul>

<h2>Add a category</h2>
<form method="post" action="<?= e(route('/admin/classifieds/categories')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <input type="text" name="name" placeholder="Category name" required>
    <button type="submit">Add category</button>
</form>

<p style="color:#666;">Individual listings are moderated inline on their own page (delete/mark-sold buttons appear there for accounts with the manage capability).</p>
