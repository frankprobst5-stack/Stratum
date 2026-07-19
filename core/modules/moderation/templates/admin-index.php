<?php
/**
 * @var array<int, array<string, mixed>> $open each with 'reporterName', 'resolverName'
 * @var array<int, array<string, mixed>> $closed each with 'reporterName', 'resolverName'
 * @var string $csrfToken
 */
?>
<h1>Moderation Queue</h1>

<h2>Open reports (<?= count($open) ?>)</h2>

<?php if ($open === []): ?>
    <p style="color:#888;">No open reports. Nothing needs your attention.</p>
<?php endif; ?>

<?php foreach ($open as $report): ?>
    <div style="margin-bottom:1rem; padding:0.75rem; background:#f4f5f7; border-radius:6px;">
        <div>
            <a href="<?= e($report['content_url']) ?>"><strong><?= e($report['content_title']) ?></strong></a>
            <span style="color:#888; font-size:0.85rem;"> &middot; reported by <?= e($report['reporterName'] ?? 'Unknown') ?> &middot; <?= e($report['created_at']) ?></span>
        </div>
        <p style="margin:0.5rem 0; white-space:pre-wrap;"><?= e($report['reason']) ?></p>
        <form method="post" action="<?= e(route('/admin/moderation/reports/' . $report['id'] . '/resolve')) ?>" style="display:inline;">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <input type="text" name="note" placeholder="Note (optional)" maxlength="255">
            <button type="submit">Resolve</button>
        </form>
        <form method="post" action="<?= e(route('/admin/moderation/reports/' . $report['id'] . '/dismiss')) ?>" style="display:inline; margin-left:0.5rem;">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <button type="submit">Dismiss</button>
        </form>
    </div>
<?php endforeach; ?>

<h2>Recently closed</h2>

<?php if ($closed === []): ?>
    <p style="color:#888;">No closed reports yet.</p>
<?php else: ?>
    <table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
        <thead>
            <tr style="text-align:left; border-bottom:1px solid #ddd;">
                <th>Content</th>
                <th>Reported by</th>
                <th>Outcome</th>
                <th>Handled by</th>
                <th>Note</th>
                <th>When</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($closed as $report): ?>
            <tr style="border-bottom:1px solid #eee;">
                <td><a href="<?= e($report['content_url']) ?>"><?= e($report['content_title']) ?></a></td>
                <td><?= e($report['reporterName'] ?? 'Unknown') ?></td>
                <td><?= e($report['status']) ?></td>
                <td><?= e($report['resolverName'] ?? 'Unknown') ?></td>
                <td><?= e($report['resolution_note'] ?? '') ?></td>
                <td><?= e($report['resolved_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
