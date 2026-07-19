<?php
/**
 * @var array<string, mixed>|null $page null when creating
 * @var string $csrfToken
 * @var string $formAction
 */
?>
<h1><?= $page === null ? 'New Page' : 'Edit Page' ?></h1>

<form method="post" action="<?= e(route($formAction)) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

    <p>
        <label for="title">Title</label><br>
        <input type="text" id="title" name="title" value="<?= e($page['title'] ?? '') ?>" required style="width:100%; max-width:500px;">
    </p>

    <p>
        <label for="body">Body</label><br>
        <textarea id="body" name="body" rows="10" cols="60" required><?= raw($page['body'] ?? '') ?></textarea>
    </p>

    <p>
        <label>
            <input type="checkbox" name="is_published" value="1" <?= !empty($page['is_published']) ? 'checked' : '' ?>>
            Published
        </label>
    </p>

    <button type="submit">Save</button>
</form>

<script src="/assets/vendor/tinymce/tinymce.min.js"></script>
<script>
    tinymce.init({
        selector: '#body',
        license_key: 'gpl',
        base_url: '/assets/vendor/tinymce',
        suffix: '.min',
        plugins: 'lists link code autoresize',
        toolbar: 'undo redo | bold italic underline | bullist numlist | link | code',
        menubar: false,
        branding: false,
        min_height: 300,
        autoresize_bottom_margin: 20
    });
</script>
