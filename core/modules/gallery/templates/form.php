<?php
/**
 * @var string $csrfToken
 */
?>
<h1>Create an album</h1>

<form method="post" action="<?= e(route('/gallery/create')) ?>" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <p>
        <label for="title">Album title</label><br>
        <input type="text" id="title" name="title" required>
    </p>
    <p>
        <label for="description">Description</label><br>
        <textarea id="description" name="description" rows="3" cols="50"></textarea>
    </p>
    <p>
        <label for="photos">Photos</label><br>
        <input type="file" id="photos" name="photos[]" multiple required>
        <br><small style="color:#666;">Max 10MB each. Allowed types: JPEG, PNG, GIF, WebP.</small>
    </p>
    <button type="submit">Create album</button>
</form>
