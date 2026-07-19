<?php
/**
 * @var array<int, array<string, mixed>> $plans
 * @var array<int, array<string, mixed>> $pending each with 'plan_name', 'payerName'
 * @var array<int, array<string, mixed>> $paid each with 'plan_name', 'payerName'
 * @var array<int, array{id: int, key: string, module_id: string, label: string}> $capabilities
 * @var string $csrfToken
 */
?>
<h1>Dues</h1>

<h2>Plans</h2>
<table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
    <thead>
        <tr style="text-align:left; border-bottom:1px solid #ddd;">
            <th>Name</th>
            <th>Amount</th>
            <th>Period</th>
            <th>Status</th>
            <th>Premium</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($plans as $plan): ?>
        <tr style="border-bottom:1px solid #eee;">
            <td><?= e($plan['name']) ?></td>
            <td><?= e($plan['currency_code']) ?> <?= e(number_format((float) $plan['amount'], 2)) ?></td>
            <td><?= e(str_replace('_', ' ', $plan['period'])) ?></td>
            <td><?= $plan['is_active'] ? 'Active' : 'Inactive' ?></td>
            <td>
                <?php if ($plan['is_premium']): ?>
                    <span style="color:#0a7d2c;">Grants <code><?= e($plan['grants_capability_key'] ?? '—') ?></code></span>
                <?php else: ?>
                    <span style="color:#888;">—</span>
                <?php endif; ?>
            </td>
            <td>
                <form method="post" action="<?= e(route('/admin/dues/plans/' . $plan['id'] . '/toggle')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button type="submit"><?= $plan['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if ($plans === []): ?>
        <tr><td colspan="6" style="color:#888;">No plans yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<h3>Create a plan</h3>
<form method="post" action="<?= e(route('/admin/dues/plans')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <p>
        <label for="name">Name</label><br>
        <input type="text" id="name" name="name" required>
    </p>
    <p>
        <label for="description">Description</label><br>
        <textarea id="description" name="description" rows="3" cols="50"></textarea>
    </p>
    <p>
        <label for="amount">Amount</label><br>
        <input type="text" id="amount" name="amount" required placeholder="50.00">
        <input type="text" name="currency_code" value="USD" size="4" maxlength="3">
    </p>
    <p>
        <label for="period">Period</label><br>
        <select id="period" name="period">
            <option value="one_time">One-time</option>
            <option value="monthly">Monthly</option>
            <option value="annual">Annual</option>
        </select>
    </p>
    <p>
        <label for="payment_url">Payment link</label><br>
        <input type="text" id="payment_url" name="payment_url" required placeholder="https://cash.app/$yourclub/50">
    </p>
    <p>
        <label><input type="checkbox" name="is_premium" value="1" onchange="document.getElementById('grants_capability_row').style.display = this.checked ? 'block' : 'none';"> Premium membership</label><br>
        <small style="color:#666;">While a member's payment for this plan is current, they automatically get the capability picked below — and it's automatically taken away once their payment lapses.</small>
    </p>
    <p id="grants_capability_row" style="display:none;">
        <label for="grants_capability_key">Grants capability</label><br>
        <select id="grants_capability_key" name="grants_capability_key">
            <option value="">— none —</option>
            <?php foreach ($capabilities as $capability): ?>
                <option value="<?= e($capability['key']) ?>"><?= e($capability['label']) ?> (<?= e($capability['key']) ?>)</option>
            <?php endforeach; ?>
        </select>
    </p>
    <button type="submit">Create plan</button>
</form>

<h2>Pending payments (<?= count($pending) ?>)</h2>
<ul>
    <?php foreach ($pending as $payment): ?>
        <li>
            <?= e($payment['payerName']) ?> — <?= e($payment['plan_name']) ?>
            <small style="color:#888;">(submitted <?= e($payment['created_at']) ?>)</small>
            <form method="post" action="<?= e(route('/admin/dues/payments/' . $payment['id'] . '/confirm')) ?>" style="display:inline;">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <input type="text" name="amount_paid" placeholder="Amount paid" size="8" required>
                <input type="text" name="period_label" placeholder="e.g. 2026" size="8">
                <input type="text" name="notes" placeholder="Notes">
                <button type="submit">Confirm paid</button>
            </form>
        </li>
    <?php endforeach; ?>
    <?php if ($pending === []): ?>
        <li style="color:#888;">Nothing pending.</li>
    <?php endif; ?>
</ul>

<h2>Confirmed payments (<?= count($paid) ?>)</h2>
<ul>
    <?php foreach ($paid as $payment): ?>
        <li>
            <?= e($payment['payerName']) ?> — <?= e($payment['plan_name']) ?>
            — <?= e(number_format((float) $payment['amount_paid'], 2)) ?>
            <?php if (!empty($payment['period_label'])): ?>(<?= e($payment['period_label']) ?>)<?php endif; ?>
            <small style="color:#888;">confirmed <?= e($payment['confirmed_at']) ?></small>
        </li>
    <?php endforeach; ?>
    <?php if ($paid === []): ?>
        <li style="color:#888;">No confirmed payments yet.</li>
    <?php endif; ?>
</ul>
