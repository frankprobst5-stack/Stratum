<?php
/**
 * @var array<int, array{content_type: string, label: string, verb: string, title: string, url: ?string, actor: ?string, created_at: string}> $items
 */
$grouped = [];
foreach ($items as $item) {
    $day = substr($item['created_at'], 0, 10);
    $grouped[$day][] = $item;
}
?>
<h1>Recent Activity</h1>

<?php if ($items === []): ?>
    <p style="color:#888;">Nothing has happened yet.</p>
<?php endif; ?>

<?php foreach ($grouped as $day => $dayItems): ?>
    <h2><?= e($day) ?></h2>
    <ul style="list-style:none;padding:0;">
        <?php foreach ($dayItems as $item): ?>
            <li style="margin-bottom:0.75rem;border-bottom:1px solid #333;padding-bottom:0.75rem;">
                <span style="color:#888;font-size:0.8rem;"><?= e(substr($item['created_at'], 11, 5)) ?></span>
                <span style="color:#888;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.03em;margin-left:0.5rem;"><?= e($item['label']) ?></span>
                <div>
                    <?php if ($item['content_type'] === 'member'): ?>
                        <strong><?= e($item['title']) ?></strong> <?= e($item['verb']) ?>
                    <?php else: ?>
                        <strong><?= e($item['actor'] ?? 'Unknown') ?></strong> <?= e($item['verb']) ?>:
                        <?php if ($item['url'] !== null): ?>
                            <a href="<?= e($item['url']) ?>"><?= e($item['title']) ?></a>
                        <?php else: ?>
                            <?= e($item['title']) ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endforeach; ?>
