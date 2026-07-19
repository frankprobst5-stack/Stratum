<?php
/**
 * @var array<int, array<string, mixed>> $sponsors
 * @var string $csrfToken
 */
?>
<h1>Sponsor Blocks</h1>
<p style="color:#888;">Active sponsors render together as a logo strip in the site footer.</p>

<table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
    <thead>
        <tr style="text-align:left; border-bottom:1px solid #ddd;">
            <th>Name</th>
            <th>Logo</th>
            <th>Link</th>
            <th>Weight</th>
            <th>Clicks</th>
            <th>Active</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($sponsors as $sponsor): ?>
        <tr style="border-bottom:1px solid #eee;">
            <td><?= e($sponsor['name']) ?></td>
            <td><img src="<?= e($sponsor['logo_url']) ?>" alt="" style="max-height:32px;"></td>
            <td><a href="<?= e($sponsor['link_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($sponsor['link_url']) ?></a></td>
            <td><?= (int) $sponsor['weight'] ?></td>
            <td><?= (int) $sponsor['click_count'] ?></td>
            <td><?= $sponsor['is_active'] ? 'Yes' : 'No' ?></td>
            <td>
                <form method="post" action="<?= e(route('/admin/sponsors/' . $sponsor['id'] . '/toggle')) ?>" style="display:inline;">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button type="submit"><?= $sponsor['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                </form>
                <form method="post" action="<?= e(route('/admin/sponsors/' . $sponsor['id'] . '/delete')) ?>" style="display:inline;">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button type="submit">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if ($sponsors === []): ?>
        <tr><td colspan="7" style="color:#888;">No sponsors yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<form method="post" action="<?= e(route('/admin/sponsors')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <input type="text" name="name" placeholder="Sponsor name" required>
    <input type="url" name="logo_url" placeholder="Logo image URL" required style="width:18rem;">
    <input type="url" name="link_url" placeholder="Destination URL" required style="width:18rem;">
    <input type="number" name="weight" placeholder="Weight" value="0" style="width:5rem;">
    <button type="submit">Add sponsor</button>
</form>
