<?php
/**
 * @var bool $enabled
 * @var int $ttlSeconds
 * @var array{fileCount: int, totalBytes: int} $stats
 * @var string $csrfToken
 * @var bool $cleared
 */
$formatBytes = static function (int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    $value = (float) $bytes;
    while ($value >= 1024 && $i < count($units) - 1) {
        $value /= 1024;
        $i++;
    }

    return number_format($value, 1) . ' ' . $units[$i];
};
?>
<h1>Page Cache</h1>
<p style="color:#666;">
    Caches the full rendered HTML of public pages for logged-out
    visitors only — never for members or admins, and never a page
    containing a form (anything with a CSRF token is refused
    automatically, since a cached copy would be served to many
    different visitors). Enabled/disabled via <code>PAGE_CACHE_ENABLED</code>
    in <code>.env</code>, not this page — a server-level setting, not a
    per-request one.
</p>

<?php if ($cleared): ?>
    <p style="color:#0a7d2c;">Cache cleared.</p>
<?php endif; ?>

<p>
    Status: <strong><?= $enabled ? 'Enabled' : 'Disabled' ?></strong>
    <?php if ($enabled): ?>
        &middot; TTL: <?= $ttlSeconds ?> seconds
    <?php endif; ?>
</p>

<p>
    <?= $stats['fileCount'] ?> cached page<?= $stats['fileCount'] === 1 ? '' : 's' ?>
    (<?= $formatBytes($stats['totalBytes']) ?>)
</p>

<form method="post" action="<?= e(route('/admin/system/cache/clear')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <button type="submit">Clear cache</button>
</form>
