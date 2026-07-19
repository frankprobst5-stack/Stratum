<?php
/**
 * @var array<int, array{id: int, name: string, slug: string}> $categories
 * @var string $csrfToken
 */
?>
<h1>Post a listing</h1>

<form method="post" action="<?= e(route('/classifieds/create')) ?>" enctype="multipart/form-data">
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
        <label for="price">Price (leave blank for "contact for price"/free)</label><br>
        <input type="text" id="price" name="price" placeholder="25.00">
    </p>
    <p>
        <label for="photo">Photo (optional)</label><br>
        <input type="file" id="photo" name="photo">
        <br><small style="color:#666;">Max 10MB. Allowed types: JPEG, PNG, GIF, WebP.</small>
    </p>
    <button type="submit">Post listing</button>
</form>
