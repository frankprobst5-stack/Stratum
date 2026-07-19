<?php
/**
 * @var array<string, mixed>|null $article null when creating
 * @var string $body current body text (prefilled on edit, from the latest revision)
 * @var array<int, array{id: int, name: string, slug: string}> $categories
 * @var string $tags comma-separated, prefilled on edit
 * @var bool $showTags
 * @var string $csrfToken
 * @var string $formAction
 */
$now = date('Y-m-d H:i:s');
$isPublished = !empty($article['is_published']);
$publishedAt = $article['published_at'] ?? null;
$isScheduled = !$isPublished && $publishedAt !== null && $publishedAt > $now;
$currentAction = $isPublished ? 'now' : ($isScheduled ? 'schedule' : 'draft');
// datetime-local wants 'Y-m-d\TH:i', not MySQL's 'Y-m-d H:i:s'.
$scheduledAtValue = $isScheduled ? substr(str_replace(' ', 'T', $publishedAt), 0, 16) : '';
?>
<h1><?= $article === null ? 'New Article' : 'Edit Article' ?></h1>

<form method="post" action="<?= e(route($formAction)) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

    <p>
        <label for="title">Title</label><br>
        <input type="text" id="title" name="title" value="<?= e($article['title'] ?? '') ?>" required style="width:100%; max-width:500px;">
    </p>

    <p>
        <label for="category_id">Category</label><br>
        <select id="category_id" name="category_id">
            <option value="">— none —</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= (int) $category['id'] ?>" <?= (int) ($article['category_id'] ?? 0) === $category['id'] ? 'selected' : '' ?>>
                    <?= e($category['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>

    <p>
        <label for="excerpt">Excerpt</label><br>
        <input type="text" id="excerpt" name="excerpt" value="<?= e($article['excerpt'] ?? '') ?>" style="width:100%; max-width:500px;">
    </p>

    <p>
        <label for="featured_image_url">Featured image URL</label><br>
        <input type="url" id="featured_image_url" name="featured_image_url" value="<?= e($article['featured_image_url'] ?? '') ?>" style="width:100%; max-width:500px;" placeholder="https://...">
        <br><small style="color:#666;">Used by the front-page "Latest Content" block when this article's category is featured there.</small>
    </p>

    <?php if ($showTags): ?>
        <p>
            <label for="tags">Tags (comma separated)</label><br>
            <input type="text" id="tags" name="tags" value="<?= e($tags) ?>" style="width:100%; max-width:500px;" placeholder="e.g. announcement, events">
        </p>
    <?php endif; ?>

    <p>
        <label for="body">Body</label><br>
        <textarea id="body" name="body" rows="10" cols="60" required data-bbcode-toolbar><?= e($body) ?></textarea><br>
        <small style="color:#666;">Supports [b] [i] [u] [url] [quote] [code]</small>
    </p>

    <?php if ($article !== null): ?>
        <p>
            <label for="comment">Edit summary (optional)</label><br>
            <input type="text" id="comment" name="comment" style="width:100%; max-width:500px;" placeholder="What did you change?">
            <br><small style="color:#666;"><a href="<?= e(route('/articles/' . $article['slug'] . '/history')) ?>">View revision history</a></small>
        </p>
    <?php endif; ?>

    <p>
        <label><input type="radio" name="publish_action" value="draft" <?= $currentAction === 'draft' ? 'checked' : '' ?>> Save as draft</label><br>
        <label><input type="radio" name="publish_action" value="now" <?= $currentAction === 'now' ? 'checked' : '' ?>> Publish now</label><br>
        <label><input type="radio" name="publish_action" value="schedule" <?= $currentAction === 'schedule' ? 'checked' : '' ?>> Schedule for
            <input type="datetime-local" name="scheduled_at" value="<?= e($scheduledAtValue) ?>">
        </label>
        <small style="color:#666;">(only used if "Schedule for" is selected)</small>
        <?php if ($isPublished): ?>
            <br><small style="color:#666;">Published <?= e($publishedAt) ?>.</small>
        <?php endif; ?>
    </p>

    <button type="submit">Save</button>
</form>
