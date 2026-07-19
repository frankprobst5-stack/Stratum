<?php
/**
 * @var array<int, array{type: string, label: string, id: int, title: string, url: string, deleted_at: string}> $items
 * @var string $csrfToken
 */
?>
<h1>Trash</h1>
<p style="color:#888;">Soft-deleted content from across the site. Restoring puts it back exactly where it was — nothing here is ever permanently gone through this screen.</p>

<?php if ($items === []): ?>
    <p style="color:#888;">Nothing in the trash.</p>
<?php else: ?>
    <table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
        <thead>
            <tr style="text-align:left; border-bottom:1px solid #ddd;">
                <th>Type</th>
                <th>Title</th>
                <th>Deleted</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr style="border-bottom:1px solid #eee;">
                <td><?= e($item['label']) ?></td>
                <td><?= e($item['title']) ?> <small style="color:#888;">(<?= e($item['url']) ?> once restored)</small></td>
                <td><?= e($item['deleted_at']) ?></td>
                <td>
                    <form method="post" action="<?= e(route('/admin/trash/restore')) ?>">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="type" value="<?= e($item['type']) ?>">
                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                        <button type="submit">Restore</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
