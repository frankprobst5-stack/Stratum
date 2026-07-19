<?php
/**
 * @var array<int, array<string, mixed>> $orgs
 */
?>
<h1>Organizations</h1>

<ul>
    <?php foreach ($orgs as $org): ?>
        <li><a href="<?= e(route('/organizations/' . $org['slug'])) ?>"><?= e($org['name']) ?></a></li>
    <?php endforeach; ?>
    <?php if ($orgs === []): ?>
        <li style="color:#888;">No organizations yet.</li>
    <?php endif; ?>
</ul>
