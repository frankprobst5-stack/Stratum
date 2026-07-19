<?php
/**
 * @var array<int, array<string, mixed>> $campaigns each with 'raised'
 */
?>
<h1>Donations</h1>

<ul>
    <?php foreach ($campaigns as $campaign): ?>
        <?php $percent = (float) $campaign['goal_amount'] > 0 ? min(100, (int) round(((float) $campaign['raised'] / (float) $campaign['goal_amount']) * 100)) : 0; ?>
        <li style="margin-bottom:1rem;">
            <a href="<?= e(route('/donations/campaigns/' . $campaign['id'])) ?>"><?= e($campaign['title']) ?></a>
            <br>
            <div style="background:#eee; border-radius:4px; width:200px; height:10px;">
                <div style="background:#2f6fed; border-radius:4px; width:<?= $percent ?>%; height:10px;"></div>
            </div>
            <small style="color:#888;">
                <?= e($campaign['currency_code']) ?> <?= e(number_format((float) $campaign['raised'], 2)) ?>
                raised of <?= e(number_format((float) $campaign['goal_amount'], 2)) ?> goal (<?= $percent ?>%)
            </small>
        </li>
    <?php endforeach; ?>
    <?php if ($campaigns === []): ?>
        <li style="color:#888;">No donation campaigns yet.</li>
    <?php endif; ?>
</ul>
