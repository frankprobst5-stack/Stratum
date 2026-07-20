<?php
/**
 * @var array<string, mixed> $issue
 * @var array<int, array<string, mixed>> $pages ordered by position
 * @var string $csrfToken
 */
?>
<p><a href="<?= e(route('/admin/newsletter')) ?>">&larr; Newsletter</a></p>
<h1>Pages: <?= e($issue['title']) ?></h1>

<div class="strat-list">
    <?php foreach ($pages as $index => $page): ?>
        <div class="strat-list-row">
            <div class="strat-list-row-main">
                <div class="strat-list-row-title"><?= (int) $page['position'] ?>. <?= e($page['title']) ?></div>
            </div>
            <form method="post" action="<?= e(route('/admin/newsletter/pages/' . $page['id'] . '/up')) ?>" style="display:inline;">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <button type="submit" <?= $index === 0 ? 'disabled' : '' ?>>&uarr;</button>
            </form>
            <form method="post" action="<?= e(route('/admin/newsletter/pages/' . $page['id'] . '/down')) ?>" style="display:inline;">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <button type="submit" <?= $index === count($pages) - 1 ? 'disabled' : '' ?>>&darr;</button>
            </form>
            <form method="post" action="<?= e(route('/admin/newsletter/pages/' . $page['id'] . '/delete')) ?>" style="display:inline;">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <button type="submit">Delete</button>
            </form>
        </div>
        <details style="margin:0 0 0.75rem;">
            <summary style="cursor:pointer; color:var(--strat-muted-text); font-size:0.85rem;">Edit</summary>
            <form method="post" action="<?= e(route('/admin/newsletter/pages/' . $page['id'])) ?>" style="margin-top:0.5rem;">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <p>
                    <label>Title<br>
                        <input type="text" name="title" value="<?= e($page['title']) ?>" required style="width:100%; max-width:500px;">
                    </label>
                </p>
                <p>
                    <label>Body<br>
                        <textarea name="body" rows="8" cols="60" data-bbcode-toolbar><?= e($page['body']) ?></textarea><br>
                        <small class="strat-muted">Supports [b] [i] [u] [url] [quote] [code]</small>
                    </label>
                </p>
                <button type="submit">Save</button>
            </form>
        </details>
    <?php endforeach; ?>
    <?php if ($pages === []): ?>
        <p class="strat-muted">No pages yet.</p>
    <?php endif; ?>
</div>

<h3>Add a page</h3>
<form method="post" action="<?= e(route('/admin/newsletter/' . $issue['id'] . '/pages')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <p>
        <label for="title">Title</label><br>
        <input type="text" id="title" name="title" required style="width:100%; max-width:500px;">
    </p>
    <p>
        <label for="body">Body</label><br>
        <textarea id="body" name="body" rows="8" cols="60" data-bbcode-toolbar></textarea><br>
        <small class="strat-muted">Supports [b] [i] [u] [url] [quote] [code]</small>
    </p>
    <button type="submit">Add page</button>
</form>
