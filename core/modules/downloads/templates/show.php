<?php
/**
 * @var array<string, mixed> $file
 * @var array<int, array<string, mixed>> $versions newest first, each with 'uploaderName'
 * @var array<int, array{id: int, label: string, url: string}> $mirrors
 * @var bool $canUpload
 * @var bool $canManage
 * @var bool $isLoggedIn
 * @var bool $showRatings
 * @var bool $canRate
 * @var array{average: float, count: int}|null $ratingSummary
 * @var ?int $myRating
 * @var string $csrfToken
 */
$currentVersion = $versions[0];
?>
<p><a href="<?= e(route('/downloads')) ?>">&larr; Downloads</a></p>

<h1><?= e($file['title']) ?></h1>

<?php if (!empty($file['description'])): ?>
    <div style="white-space:pre-wrap;"><?= e($file['description']) ?></div>
<?php endif; ?>

<?php if ($showRatings): ?>
    <div style="margin:0.5rem 0;">
        <?php if ($ratingSummary['count'] > 0): ?>
            <strong><?= number_format($ratingSummary['average'], 1) ?></strong> / 5
            <small class="strat-muted">(<?= $ratingSummary['count'] ?> rating<?= $ratingSummary['count'] === 1 ? '' : 's' ?>)</small>
        <?php else: ?>
            <small class="strat-muted">No ratings yet.</small>
        <?php endif; ?>
        <?php if ($canRate): ?>
            <form method="post" action="<?= e(route('/ratings')) ?>" style="display:inline-block; margin-left:0.5rem;">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="ratable_type" value="download">
                <input type="hidden" name="ratable_id" value="<?= (int) $file['id'] ?>">
                <input type="hidden" name="redirect_to" value="<?= e(route('/downloads/files/' . $file['id'])) ?>">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <button type="submit" name="score" value="<?= $i ?>" style="border:none; background:none; cursor:pointer; font-size:1.1rem;" title="Rate <?= $i ?>">
                        <?= $myRating !== null && $i <= $myRating ? '&#9733;' : '&#9734;' ?>
                    </button>
                <?php endfor; ?>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<p>
    <?php if ($currentVersion['scan_status'] === 'infected'): ?>
        <span class="strat-pill" data-tone="danger">This file failed a virus scan and cannot be downloaded.</span>
    <?php else: ?>
        <a class="strat-card-cta" style="display:inline-block;" href="<?= e(route('/downloads/files/' . $file['id'] . '/download')) ?>">
            Download current version (<?= number_format((int) $currentVersion['size'] / 1024, 1) ?> KB)
        </a>
    <?php endif; ?>
    &middot; <?= (int) $file['download_count'] ?> downloads
    <?php if ($currentVersion['scan_status'] === 'clean'): ?>
        <span class="strat-pill" data-tone="success">Virus scan: clean</span>
    <?php elseif ($currentVersion['scan_status'] === 'unavailable' && $canManage): ?>
        <span class="strat-pill" data-tone="neutral">Virus scan unavailable on this server</span>
    <?php endif; ?>
</p>

<?php if ($mirrors !== [] || $canManage): ?>
    <h3>Mirrors</h3>
    <?php if ($mirrors === []): ?>
        <p class="strat-muted">No alternate mirrors listed.</p>
    <?php else: ?>
        <div class="strat-list">
            <?php foreach ($mirrors as $mirror): ?>
                <div class="strat-list-row">
                    <div class="strat-list-row-icon" aria-hidden="true">&#128279;</div>
                    <div class="strat-list-row-main">
                        <div class="strat-list-row-title">
                            <a href="<?= e($mirror['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($mirror['label']) ?></a>
                        </div>
                    </div>
                    <?php if ($canManage): ?>
                        <form method="post" action="<?= e(route('/downloads/files/' . $file['id'] . '/mirrors/' . $mirror['id'] . '/delete')) ?>">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                            <button type="submit" style="border:none; background:none; color:var(--strat-muted-text); text-decoration:underline; cursor:pointer; padding:0; font-size:0.85rem;">Remove</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($canManage): ?>
        <form method="post" action="<?= e(route('/downloads/files/' . $file['id'] . '/mirrors')) ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="text" name="label" placeholder="Label (e.g. Google Drive mirror)" required>
            <input type="url" name="url" placeholder="https://..." required>
            <button type="submit">Add mirror</button>
        </form>
    <?php endif; ?>
<?php endif; ?>

<?php if ($canUpload): ?>
    <h3>Upload a new version</h3>
    <form method="post" action="<?= e(route('/downloads/files/' . $file['id'] . '/versions')) ?>" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="file" name="file" required>
        <button type="submit">Upload new version</button>
    </form>
<?php elseif ($isLoggedIn): ?>
    <p class="strat-muted">You don't have permission to upload new versions.</p>
<?php else: ?>
    <p><a href="<?= e(route('/login')) ?>">Log in</a> to upload a new version.</p>
<?php endif; ?>

<h2>Version history</h2>
<div class="strat-list">
    <?php foreach ($versions as $version): ?>
        <div class="strat-list-row">
            <div class="strat-list-row-icon" aria-hidden="true">&#128196;</div>
            <div class="strat-list-row-main">
                <div class="strat-list-row-title">
                    v<?= (int) $version['version_number'] ?>
                    <?php if ($version['scan_status'] === 'infected'): ?>
                        <span class="strat-pill" data-tone="danger">Blocked — failed virus scan</span>
                        <?= e($version['original_name']) ?>
                    <?php else: ?>
                        <a href="<?= e(route('/downloads/files/' . $file['id'] . '/versions/' . $version['id'] . '/download')) ?>">
                            <?= e($version['original_name']) ?>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="strat-list-row-meta">
                    <?= number_format((int) $version['size'] / 1024, 1) ?> KB &middot;
                    <?= e($version['uploaderName']) ?> &middot;
                    <?= e($version['created_at']) ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
