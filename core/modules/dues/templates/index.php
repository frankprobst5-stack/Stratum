<?php
/**
 * @var array<int, array<string, mixed>> $plans
 */
?>
<h1>Dues</h1>

<ul>
    <?php foreach ($plans as $plan): ?>
        <li>
            <a href="<?= e(route('/dues/plans/' . $plan['id'])) ?>"><?= e($plan['name']) ?></a>
            — <?= e($plan['currency_code']) ?> <?= e(number_format((float) $plan['amount'], 2)) ?>
            / <?= e(str_replace('_', ' ', $plan['period'])) ?>
        </li>
    <?php endforeach; ?>
    <?php if ($plans === []): ?>
        <li style="color:#888;">No dues plans yet.</li>
    <?php endif; ?>
</ul>
