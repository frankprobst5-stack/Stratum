<?php
/**
 * @var array<int, array<string, mixed>> $entries
 * @var int $page
 * @var int $totalPages
 * @var int $total
 */
?>
<h1>Admin Action Log</h1>
<p style="color:#666;">
    Every successful admin-panel change (create/update/delete/toggle
    actions), who made it, and when. Read-only — this doesn't capture
    field-level detail, just who touched what page.
</p>
<p style="color:#666;"><?= $total ?> total entries.</p>

<?php if ($entries === []): ?>
    <p style="color:#888;">No admin actions logged yet.</p>
<?php else: ?>
    <table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
        <thead>
            <tr style="text-align:left; border-bottom:1px solid #ddd;">
                <th>Admin</th>
                <th>Action</th>
                <th>When</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($entries as $entry): ?>
            <tr style="border-bottom:1px solid #eee;">
                <td><?= e($entry['username']) ?></td>
                <td><code><?= e($entry['method']) ?> <?= e($entry['path']) ?></code></td>
                <td style="white-space:nowrap;"><?= e($entry['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p>
        Page <?= $page ?> of <?= $totalPages ?>
        <?php if ($page > 1): ?>
            &middot; <a href="<?= e(route('/admin/system/audit-log') . '?page=' . ($page - 1)) ?>">Previous</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
            &middot; <a href="<?= e(route('/admin/system/audit-log') . '?page=' . ($page + 1)) ?>">Next</a>
        <?php endif; ?>
    </p>
<?php endif; ?>
