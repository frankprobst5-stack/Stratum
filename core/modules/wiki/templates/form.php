<?php
/**
 * @var array<string, mixed>|null $page null when creating
 * @var string $body current body text (prefilled on edit)
 * @var array<int, array{id: int, name: string, slug: string}> $categories
 * @var string $tags comma-separated, prefilled on edit
 * @var bool $showTags
 * @var string $csrfToken
 * @var string $formAction
 */
?>
<h1><?= $page === null ? 'New Wiki Page' : 'Edit: ' . e($page['title']) ?></h1>

<form method="post" action="<?= e(route($formAction)) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

    <?php if ($page === null): ?>
        <p>
            <label for="title">Title</label><br>
            <input type="text" id="title" name="title" required style="width:100%; max-width:500px;">
        </p>

        <p>
            <label for="category_id">Category</label><br>
            <select id="category_id" name="category_id">
                <option value="">— none —</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </p>
    <?php endif; ?>

    <?php if ($showTags): ?>
        <p>
            <label for="tags">Tags (comma separated)</label><br>
            <input type="text" id="tags" name="tags" value="<?= e($tags) ?>" style="width:100%; max-width:500px;" placeholder="e.g. howto, reference">
        </p>
    <?php endif; ?>

    <p>
        <label for="body">Body</label><br>
        <textarea id="body" name="body" rows="12" cols="60" required data-bbcode-toolbar><?= e($body) ?></textarea><br>
        <small style="color:#666;">Supports [b] [i] [u] [url] [quote] [code]</small>
    </p>

    <?php if ($page !== null): ?>
        <p>
            <label for="comment">Edit summary (optional)</label><br>
            <input type="text" id="comment" name="comment" style="width:100%; max-width:500px;" placeholder="What did you change?">
        </p>
    <?php endif; ?>

    <button type="submit">Save</button>
</form>
