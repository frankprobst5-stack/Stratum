<?php
/**
 * @var array<int, array{id: int, name: string, slug: string}> $categories
 * @var string $csrfToken
 */
?>
<h1>Upload a file</h1>

<form method="post" action="<?= e(route('/downloads/create')) ?>" enctype="multipart/form-data">
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
        <input type="text" id="title" name="title" required>
    </p>
    <p>
        <label for="description">Description</label><br>
        <textarea id="description" name="description" rows="3" cols="50"></textarea>
    </p>
    <p>
        <label for="file">File</label><br>
        <input type="file" id="file" name="file" required>
        <br><small style="color:#666;">Max 10MB. Allowed types: images, PDF, plain text, zip.</small>
    </p>
    <button type="submit">Upload</button>
</form>
