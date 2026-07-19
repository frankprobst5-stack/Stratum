<?php
/**
 * @var array<int, array{filename: string, size: int, createdAt: int}> $backups
 * @var string $csrfToken
 * @var bool $created
 * @var ?string $error
 */
$formatBytes = static function (int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    $value = (float) $bytes;
    while ($value >= 1024 && $i < count($units) - 1) {
        $value /= 1024;
        $i++;
    }

    return number_format($value, 1) . ' ' . $units[$i];
};
?>
<h1>Backups</h1>
<p style="color:#666;">
    A full database dump — every table, every row. Uploaded files
    (gallery photos, downloads, etc.) aren't included; back those up
    separately via your host's own file backup tools.
</p>

<?php if ($created): ?>
    <p style="color:#0a7d2c;">Backup created.</p>
<?php endif; ?>
<?php if ($error !== null): ?>
    <p style="color:#c0392b;">Backup failed: <?= e($error) ?></p>
<?php endif; ?>

<form method="post" action="<?= e(route('/admin/system/backups/create')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <button type="submit">Create backup now</button>
</form>

<h2>Existing backups</h2>
<?php if ($backups === []): ?>
    <p style="color:#888;">No backups yet.</p>
<?php else: ?>
    <table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
        <thead>
            <tr style="text-align:left; border-bottom:1px solid #ddd;">
                <th>File</th>
                <th>Size</th>
                <th>Created</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($backups as $backup): ?>
            <tr style="border-bottom:1px solid #eee;">
                <td><?= e($backup['filename']) ?></td>
                <td><?= $formatBytes($backup['size']) ?></td>
                <td><?= e(date('Y-m-d H:i:s', $backup['createdAt'])) ?></td>
                <td>
                    <a href="<?= e(route('/admin/system/backups/' . $backup['filename'] . '/download')) ?>">Download</a>
                    &middot;
                    <form method="post" action="<?= e(route('/admin/system/backups/' . $backup['filename'] . '/delete')) ?>" style="display:inline;" onsubmit="return confirm('Delete this backup?');">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit" style="border:none; background:none; color:#888; text-decoration:underline; cursor:pointer; padding:0; font-size:0.85rem;">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
