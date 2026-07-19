<?php
/**
 * @var array<int, array{id: int, name: string, slug: string}> $categories
 * @var string $csrfToken
 */
?>
<p><a href="<?= e(route('/links')) ?>">&larr; Link Directory</a></p>
<h1>Submit a link</h1>

<form method="post" action="<?= e(route('/links/submit')) ?>">
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
        <label for="title">Title</label><br>
        <input type="text" id="title" name="title" required style="width:100%; max-width:500px;">
    </p>
    <p>
        <label for="url">URL</label><br>
        <input type="url" id="url" name="url" required placeholder="https://example.org" style="width:100%; max-width:500px;">
    </p>
    <p>
        <label for="description">Description (optional)</label><br>
        <textarea id="description" name="description" rows="3" cols="50"></textarea>
    </p>
    <button type="submit">Submit link</button>
</form>
