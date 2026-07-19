<?php
/**
 * @var array<int, array<string, mixed>> $links
 * @var string $csrfToken
 */
?>
<h1>Affiliate Links</h1>

<table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
    <thead>
        <tr style="text-align:left; border-bottom:1px solid #ddd;">
            <th>Label</th>
            <th>URL</th>
            <th>Weight</th>
            <th>Clicks</th>
            <th>Active</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($links as $link): ?>
        <tr style="border-bottom:1px solid #eee;">
            <td><?= e($link['label']) ?></td>
            <td><a href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($link['url']) ?></a></td>
            <td><?= (int) $link['weight'] ?></td>
            <td><?= (int) $link['click_count'] ?></td>
            <td><?= $link['is_active'] ? 'Yes' : 'No' ?></td>
            <td>
                <form method="post" action="<?= e(route('/admin/affiliates/' . $link['id'] . '/toggle')) ?>" style="display:inline;">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button type="submit"><?= $link['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                </form>
                <form method="post" action="<?= e(route('/admin/affiliates/' . $link['id'] . '/delete')) ?>" style="display:inline;">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button type="submit">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if ($links === []): ?>
        <tr><td colspan="6" style="color:#888;">No affiliate links yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<form method="post" action="<?= e(route('/admin/affiliates')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <input type="text" name="label" placeholder="Label" required>
    <input type="url" name="url" placeholder="Destination URL" required style="width:20rem;">
    <input type="text" name="description" placeholder="Description (optional)">
    <input type="number" name="weight" placeholder="Weight" value="0" style="width:5rem;">
    <button type="submit">Add link</button>
</form>
