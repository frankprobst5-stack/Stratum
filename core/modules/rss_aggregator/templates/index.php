<?php
/** @var array<int, array<string, mixed>> $items */
?>
<h1>Feeds</h1>
<p style="color:#888;">Recent items from aggregated external feeds.</p>

<ul style="list-style:none;padding:0;">
    <?php foreach ($items as $item): ?>
        <li style="margin-bottom:1.5rem;border-bottom:1px solid #333;padding-bottom:1rem;">
            <div style="color:#888;font-size:0.85rem;">
                <?= e($item['source_name']) ?>
                <?php if ($item['published_at'] !== null): ?>
                    &middot; <?= e($item['published_at']) ?>
                <?php endif; ?>
            </div>
            <a href="<?= e($item['url']) ?>" target="_blank" rel="nofollow noopener"><strong><?= e($item['title']) ?></strong></a>
            <?php if (!empty($item['description'])): ?>
                <p><?= e($item['description']) ?></p>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
    <?php if ($items === []): ?>
        <li style="color:#888;">No feed items yet.</li>
    <?php endif; ?>
</ul>
