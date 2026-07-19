<?php
/**
 * @var array<int, array<string, mixed>> $entries
 * @var ?string $level
 * @var int $page
 * @var int $totalPages
 * @var int $total
 * @var string $csrfToken
 */
?>
<p><a href="<?= e(route('/admin/system/health')) ?>">&larr; System Health</a></p>
<h1>Logs</h1>
<p style="color:#666;"><?= $total ?> total entries.</p>

<p>
    <a href="<?= e(route('/admin/system/logs')) ?>" style="<?= $level === null ? 'font-weight:bold;' : '' ?>">All</a>
    &middot;
    <a href="<?= e(route('/admin/system/logs') . '?level=error') ?>" style="<?= $level === 'error' ? 'font-weight:bold;' : '' ?>">Errors</a>
    &middot;
    <a href="<?= e(route('/admin/system/logs') . '?level=info') ?>" style="<?= $level === 'info' ? 'font-weight:bold;' : '' ?>">Info</a>
</p>

<?php if ($entries === []): ?>
    <p style="color:#888;">No log entries.</p>
<?php else: ?>
    <table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
        <thead>
            <tr style="text-align:left; border-bottom:1px solid #ddd;">
                <th>Level</th>
                <th>Message</th>
                <th>Context</th>
                <th>When</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($entries as $entry): ?>
            <tr style="border-bottom:1px solid #eee;">
                <td style="color:<?= $entry['level'] === 'error' ? '#c0392b' : '#666' ?>;"><?= e($entry['level']) ?></td>
                <td><?= e($entry['message']) ?></td>
                <td style="max-width:300px; overflow-wrap:break-word; color:#888; font-size:0.85rem;"><?= e((string) ($entry['context'] ?? '')) ?></td>
                <td style="white-space:nowrap;"><?= e($entry['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p>
        Page <?= $page ?> of <?= $totalPages ?>
        <?php if ($page > 1): ?>
            &middot; <a href="<?= e(route('/admin/system/logs') . '?page=' . ($page - 1) . ($level !== null ? '&level=' . $level : '')) ?>">Previous</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
            &middot; <a href="<?= e(route('/admin/system/logs') . '?page=' . ($page + 1) . ($level !== null ? '&level=' . $level : '')) ?>">Next</a>
        <?php endif; ?>
    </p>
<?php endif; ?>

<?php if ($total > 0): ?>
    <form method="post" action="<?= e(route('/admin/system/logs/clear')) ?>" onsubmit="return confirm('Clear all log entries? This cannot be undone.');">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <button type="submit">Clear all logs</button>
    </form>
<?php endif; ?>
