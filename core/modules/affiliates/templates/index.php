<?php
/** @var array<int, array<string, mixed>> $links */
?>
<h1>Our Partners</h1>

<?php if ($links === []): ?>
    <p style="color:#888;">No partner links yet.</p>
<?php else: ?>
    <ul>
        <?php foreach ($links as $link): ?>
            <li style="margin-bottom:0.5rem;">
                <a href="<?= e(route('/affiliates/' . $link['id'] . '/visit')) ?>" target="_blank" rel="noopener sponsored">
                    <?= e($link['label']) ?>
                </a>
                <?php if (!empty($link['description'])): ?>
                    <br><small style="color:#666;"><?= e($link['description']) ?></small>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
