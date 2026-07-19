<?php
/**
 * @var array<int, array{id: int, name: string, slug: string}> $categories
 * @var string $csrfToken
 */
?>
<h1>Add a video</h1>

<form method="post" action="<?= e(route('/videos/create')) ?>" enctype="multipart/form-data">
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
        <label for="url">YouTube or Vimeo URL</label><br>
        <input type="text" id="url" name="url" placeholder="https://www.youtube.com/watch?v=...">
    </p>
    <p style="text-align:center; color:#888;">— or —</p>
    <p>
        <label for="file">Upload a video file</label><br>
        <input type="file" id="file" name="file">
        <br><small style="color:#666;">Max 50MB. Allowed types: MP4, WebM.</small>
    </p>
    <button type="submit">Add video</button>
</form>
