<?php
/**
 * @var array<int, array<string, mixed>> $categories each with 'files' (each with 'currentVersion')
 * @var bool $canUpload
 */
?>
<h1>Downloads</h1>

<?php if ($canUpload): ?>
    <p><a href="<?= e(route('/downloads/create')) ?>">Upload a file</a></p>
<?php endif; ?>

<?php foreach ($categories as $category): ?>
    <h2><?= e($category['name']) ?></h2>
    <div class="strat-list">
        <?php foreach ($category['files'] as $file): ?>
            <?php $version = $file['currentVersion']; ?>
            <div class="strat-list-row">
                <div class="strat-list-row-icon" aria-hidden="true">&#128196;</div>
                <div class="strat-list-row-main">
                    <div class="strat-list-row-title">
                        <a href="<?= e(route('/downloads/files/' . $file['id'])) ?>"><?= e($file['title']) ?></a>
                        <?php if ($version !== null && $version['scan_status'] === 'clean'): ?>
                            <span class="strat-pill" data-tone="success">Scanned clean</span>
                        <?php elseif ($version !== null && $version['scan_status'] === 'infected'): ?>
                            <span class="strat-pill" data-tone="danger">Blocked</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($version !== null): ?>
                        <div class="strat-list-row-meta"><?= number_format((int) $version['size'] / 1024, 1) ?> KB</div>
                    <?php endif; ?>
                </div>
                <div class="strat-list-row-stats">
                    <div><strong><?= (int) $file['download_count'] ?></strong> downloads</div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if ($category['files'] === []): ?>
            <p class="strat-muted">No files yet.</p>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
<?php if ($categories === []): ?>
    <p class="strat-muted">No categories yet.</p>
<?php endif; ?>
