<?php
/**
 * @var array<string, mixed> $plan
 * @var array<int, array<string, mixed>> $myPayments newest first
 * @var bool $hasPending
 * @var array<string, mixed>|null $currentPremiumPayment
 * @var bool $canPay
 * @var bool $isLoggedIn
 * @var string $csrfToken
 */
?>
<p><a href="<?= e(route('/dues')) ?>">&larr; Dues</a></p>

<h1><?= e($plan['name']) ?></h1>

<?php if (!empty($plan['description'])): ?>
    <div style="white-space:pre-wrap;"><?= e($plan['description']) ?></div>
<?php endif; ?>

<p>
    <strong><?= e($plan['currency_code']) ?> <?= e(number_format((float) $plan['amount'], 2)) ?></strong>
    / <?= e(str_replace('_', ' ', $plan['period'])) ?>
</p>

<?php if ($plan['is_premium']): ?>
    <?php if ($currentPremiumPayment !== null): ?>
        <p style="color:#0a7d2c;">
            <strong>You're a premium member</strong>
            <?php if ($currentPremiumPayment['expires_at'] !== null): ?>
                until <?= e($currentPremiumPayment['expires_at']) ?>
            <?php else: ?>
                — no expiration
            <?php endif; ?>
        </p>
    <?php elseif ($isLoggedIn): ?>
        <p style="color:#888;">This is a premium plan — pay to unlock its benefits.</p>
    <?php endif; ?>
<?php endif; ?>

<p><a href="<?= e($plan['payment_url']) ?>" target="_blank" rel="noopener">Pay via linked payment page &rarr;</a></p>

<?php if ($canPay): ?>
    <?php if ($hasPending): ?>
        <p style="color:#888;">You've indicated you're paying this — an admin will confirm once payment is received.</p>
    <?php else: ?>
        <form method="post" action="<?= e(route('/dues/plans/' . $plan['id'] . '/pay')) ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <button type="submit">I'm paying this</button>
        </form>
    <?php endif; ?>
<?php elseif ($isLoggedIn): ?>
    <p style="color:#888;">You don't have permission to record a payment here.</p>
<?php else: ?>
    <p><a href="<?= e(route('/login')) ?>">Log in</a> to record that you're paying this.</p>
<?php endif; ?>

<?php if ($myPayments !== []): ?>
    <h2>Your payment history for this plan</h2>
    <ul>
        <?php foreach ($myPayments as $payment): ?>
            <li>
                <?= e(ucfirst($payment['status'])) ?>
                <?php if ($payment['status'] === 'paid'): ?>
                    — <?= e($plan['currency_code']) ?> <?= e(number_format((float) $payment['amount_paid'], 2)) ?>
                    <?php if (!empty($payment['period_label'])): ?>(<?= e($payment['period_label']) ?>)<?php endif; ?>
                    on <?= e($payment['confirmed_at']) ?>
                <?php else: ?>
                    — submitted <?= e($payment['created_at']) ?>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
