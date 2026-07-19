<?php
/**
 * @var string $currentVersion
 * @var string $maxUploadSize
 * @var string $updateCheckUrl
 * @var array{success: bool, updateAvailable: bool, latestVersion: ?string, notes: ?string, downloadUrl: ?string, error: ?string}|null $checkResult
 * @var string $csrfToken
 * @var array{success: bool, message: string, steps: array<int, array{label: string, ok: bool}>}|null $result
 */
?>
<h1>System Update</h1>

<p>Current version: <strong><?= e($currentVersion) ?></strong></p>
<p style="color:#888; font-size:0.85rem;">This server accepts uploads up to <?= e($maxUploadSize) ?>.</p>

<h2>Check for updates</h2>
<p style="color:#666; font-size:0.85rem;">
    Stratum doesn't phone home to any central server — enter the URL of a small
    JSON manifest (wherever your update packages are announced) and check it
    manually whenever you like.
</p>

<?php if ($checkResult !== null): ?>
    <?php if (!$checkResult['success']): ?>
        <p style="color:#c0392b;">Couldn't check for updates: <?= e($checkResult['error']) ?></p>
    <?php elseif ($checkResult['updateAvailable']): ?>
        <p style="color:#0a7d2c;">
            <strong>Update available: v<?= e($checkResult['latestVersion']) ?></strong>
            <?php if ($checkResult['notes'] !== null): ?><br><?= nl2br(e($checkResult['notes'])) ?><?php endif; ?>
            <?php if ($checkResult['downloadUrl'] !== null): ?>
                <br><a href="<?= e($checkResult['downloadUrl']) ?>" target="_blank" rel="noopener noreferrer">Download the update package</a>
            <?php endif; ?>
        </p>
    <?php else: ?>
        <p style="color:#666;">You're up to date (latest available: v<?= e($checkResult['latestVersion']) ?>).</p>
    <?php endif; ?>
<?php endif; ?>

<form method="post" action="<?= e(route('/admin/system/update/check')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <p>
        <label for="update_check_url">Manifest URL</label><br>
        <input type="text" id="update_check_url" name="update_check_url" value="<?= e($updateCheckUrl) ?>" size="60" placeholder="https://example.org/stratum-latest.json">
    </p>
    <button type="submit">Check for updates</button>
</form>

<hr>

<?php if ($result !== null): ?>
    <div style="padding:0.75rem 1rem; border-radius:6px; margin-bottom:1rem; background:<?= $result['success'] ? '#e6f4ea' : '#fdecea' ?>; border:1px solid <?= $result['success'] ? '#b7dfc0' : '#f5c6cb' ?>; color:<?= $result['success'] ? '#0a7d2c' : '#611a15' ?>;">
        <strong><?= $result['success'] ? 'Success' : 'Update failed' ?></strong>
        <p><?= e($result['message']) ?></p>
        <?php if ($result['steps'] !== []): ?>
            <ul>
                <?php foreach ($result['steps'] as $step): ?>
                    <li><?= $step['ok'] ? '&#10003;' : '&#10007;' ?> <?= e($step['label']) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(route('/admin/system/update')) ?>" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <p>
        <label for="package">Update package (.zip, signed by the Stratum update source)</label><br>
        <input type="file" id="package" name="package" accept=".zip" required>
    </p>
    <p style="color:#666; font-size:0.85rem;">
        This updates your site's application files and database schema. Your uploaded
        files, member data, and site settings are never touched by this process.
        A backup of the files being changed is kept automatically.
    </p>
    <button type="submit">Apply update</button>
</form>
