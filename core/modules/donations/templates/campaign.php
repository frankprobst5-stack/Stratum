<?php
/**
 * @var array<string, mixed> $campaign
 * @var string $raised
 * @var int $progressPercent
 * @var array<int, array<string, mixed>> $myContributions newest first
 * @var bool $hasPending
 * @var bool $canContribute
 * @var bool $isLoggedIn
 * @var string $csrfToken
 */
?>
<p><a href="<?= e(route('/donations')) ?>">&larr; Donations</a></p>

<h1><?= e($campaign['title']) ?></h1>

<?php if (!empty($campaign['description'])): ?>
    <div style="white-space:pre-wrap;"><?= e($campaign['description']) ?></div>
<?php endif; ?>

<div style="background:#eee; border-radius:4px; width:300px; height:14px;">
    <div style="background:#2f6fed; border-radius:4px; width:<?= (int) $progressPercent ?>%; height:14px;"></div>
</div>
<p>
    <strong><?= e($campaign['currency_code']) ?> <?= e(number_format((float) $raised, 2)) ?></strong>
    raised of <?= e(number_format((float) $campaign['goal_amount'], 2)) ?> goal
    (<?= (int) $progressPercent ?>%)
</p>

<p><a href="<?= e($campaign['payment_url']) ?>" target="_blank" rel="noopener">Donate via linked payment page &rarr;</a></p>

<?php if ($canContribute): ?>
    <?php if ($hasPending): ?>
        <p style="color:#888;">You've indicated you're donating — an admin will confirm once the contribution is received.</p>
    <?php else: ?>
        <form method="post" action="<?= e(route('/donations/campaigns/' . $campaign['id'] . '/contribute')) ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <button type="submit">I'm donating</button>
        </form>
    <?php endif; ?>
<?php elseif ($isLoggedIn): ?>
    <p style="color:#888;">You don't have permission to record a donation here.</p>
<?php else: ?>
    <p><a href="<?= e(route('/login')) ?>">Log in</a> to record that you're donating.</p>
<?php endif; ?>

<?php if ($myContributions !== []): ?>
    <h2>Your contribution history for this campaign</h2>
    <ul>
        <?php foreach ($myContributions as $contribution): ?>
            <li>
                <?= e(ucfirst($contribution['status'])) ?>
                <?php if ($contribution['status'] === 'confirmed'): ?>
                    — <?= e($campaign['currency_code']) ?> <?= e(number_format((float) $contribution['amount'], 2)) ?>
                    on <?= e($contribution['confirmed_at']) ?>
                <?php else: ?>
                    — submitted <?= e($contribution['created_at']) ?>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
