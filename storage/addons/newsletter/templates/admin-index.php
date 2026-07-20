<?php
/**
 * @var array<int, array<string, mixed>> $issues every issue, published or not
 * @var string $csrfToken
 */
?>
<h1>Newsletter</h1>

<table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
    <thead>
        <tr style="text-align:left; border-bottom:1px solid #ddd;">
            <th>Title</th>
            <th>Status</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($issues as $issue): ?>
        <tr style="border-bottom:1px solid #eee;">
            <td><?= e($issue['title']) ?></td>
            <td><?= $issue['is_published'] ? 'Published' : 'Draft' ?></td>
            <td>
                <a href="<?= e(route('/admin/newsletter/' . $issue['id'] . '/pages')) ?>">Manage pages</a>
                <form method="post" action="<?= e(route('/admin/newsletter/issues/' . $issue['id'] . '/toggle')) ?>" style="display:inline;">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button type="submit"><?= $issue['is_published'] ? 'Unpublish' : 'Publish' ?></button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if ($issues === []): ?>
        <tr><td colspan="3" class="strat-muted">No issues yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<h3>New issue</h3>
<form method="post" action="<?= e(route('/admin/newsletter/issues')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <p>
        <label for="title">Title</label><br>
        <input type="text" id="title" name="title" required>
    </p>
    <button type="submit">Create issue</button>
</form>
