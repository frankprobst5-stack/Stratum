<?php
/**
 * @var array<int, array{label: string, ok: bool}> $checks
 * @var array{freeBytes: int, totalBytes: int, freePercent: float} $diskSpace
 * @var array<string, string> $phpLimits
 * @var ?string $lastCronRun
 * @var int $recentErrorCount
 * @var string $phpVersion
 * @var string $mysqlVersion
 */
// A closure, not a top-level function declaration — this template's own
// content could in principle be rendered more than once in a process
// lifetime (the PHP built-in dev server aside), and a bare `function`
// here would fatal on redeclare the same way forum's board-nesting
// helper already hit and avoided this exact issue elsewhere in this app.
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
<h1>System Health</h1>

<h2>Environment</h2>
<table border="0" cellpadding="6" style="border-collapse:collapse;">
    <?php foreach ($checks as $check): ?>
        <tr>
            <td><?= $check['ok'] ? '<span style="color:#0a7d2c;">✓</span>' : '<span style="color:#c0392b;">✗</span>' ?></td>
            <td><?= e($check['label']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<h2>Disk space</h2>
<p>
    <?= $formatBytes($diskSpace['freeBytes']) ?> free of <?= $formatBytes($diskSpace['totalBytes']) ?>
    (<?= $diskSpace['freePercent'] ?>% free)
</p>

<h2>PHP limits</h2>
<table border="0" cellpadding="6" style="border-collapse:collapse;">
    <?php foreach ($phpLimits as $key => $value): ?>
        <tr>
            <td><?= e($key) ?></td>
            <td><?= e($value) ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<h2>Cron</h2>
<p>
    <?php if ($lastCronRun !== null): ?>
        Last run: <?= e($lastCronRun) ?>
    <?php else: ?>
        <span style="color:#c0392b;">Cron has never run.</span>
        <small style="color:#888;">See <code>docs/</code> for the crontab line to add on real hosting.</small>
    <?php endif; ?>
</p>

<h2>Errors</h2>
<p>
    <?= $recentErrorCount ?> error<?= $recentErrorCount === 1 ? '' : 's' ?> logged in the last 24 hours.
    <?php if ($recentErrorCount > 0): ?>
        <a href="<?= e(route('/admin/system/logs')) ?>">View log</a>
    <?php endif; ?>
</p>

<h2>Versions</h2>
<p>PHP <?= e($phpVersion) ?> &middot; MySQL <?= e($mysqlVersion) ?></p>
