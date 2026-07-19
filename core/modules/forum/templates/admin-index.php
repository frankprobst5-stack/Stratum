<?php
/**
 * @var array<int, array{id: int, name: string, slug: string}> $categories
 * @var array<int, array<string, mixed>> $boards
 * @var string $csrfToken
 */
?>
<h1>Forum — Categories &amp; Boards</h1>

<h2>Categories</h2>
<ul>
    <?php foreach ($categories as $category): ?>
        <li><?= e($category['name']) ?></li>
    <?php endforeach; ?>
    <?php if ($categories === []): ?>
        <li style="color:#888;">No categories yet.</li>
    <?php endif; ?>
</ul>

<form method="post" action="<?= e(route('/admin/forum/categories')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <input type="text" name="name" placeholder="Category name" required>
    <button type="submit">Add category</button>
</form>

<?php
$boardNames = [];
foreach ($boards as $b) {
    $boardNames[(int) $b['id']] = $b['name'];
}
?>
<h2>Boards</h2>
<table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
    <thead>
        <tr style="text-align:left; border-bottom:1px solid #ddd;">
            <th>Board</th>
            <th>Parent</th>
            <th>Slug</th>
            <th>Topics</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($boards as $board): ?>
        <tr style="border-bottom:1px solid #eee;">
            <td><?= e($board['name']) ?></td>
            <td><?= $board['parent_id'] !== null ? e($boardNames[(int) $board['parent_id']] ?? '(unknown)') : '—' ?></td>
            <td><code><?= e($board['slug']) ?></code></td>
            <td><?= (int) $board['topic_count'] ?></td>
            <td><a href="<?= e(route('/admin/forum/boards/' . $board['id'] . '/moderators')) ?>">Moderators</a></td>
        </tr>
    <?php endforeach; ?>
    <?php if ($boards === []): ?>
        <tr><td colspan="5" style="color:#888;">No boards yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<form method="post" action="<?= e(route('/admin/forum/boards')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <p>
        <label for="category_id">Category</label><br>
        <select id="category_id" name="category_id" required>
            <?php foreach ($categories as $category): ?>
                <option value="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </p>
    <p>
        <label for="name">Board name</label><br>
        <input type="text" id="name" name="name" required>
    </p>
    <p>
        <label for="description">Description</label><br>
        <input type="text" id="description" name="description">
    </p>
    <p>
        <label for="parent_id">Parent board (optional — makes this a sub-board)</label><br>
        <select id="parent_id" name="parent_id">
            <option value="">None — top-level board</option>
            <?php foreach ($boards as $board): ?>
                <option value="<?= (int) $board['id'] ?>"><?= e($board['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </p>
    <button type="submit">Add board</button>
</form>
