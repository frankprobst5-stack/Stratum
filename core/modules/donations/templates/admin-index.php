<?php
/**
 * @var array<int, array<string, mixed>> $campaigns each with 'raised'
 * @var array<int, array<string, mixed>> $pending each with 'campaign_title', 'contributorName'
 * @var array<int, array<string, mixed>> $confirmed each with 'campaign_title', 'contributorName'
 * @var string $csrfToken
 */
?>
<h1>Donations</h1>

<h2>Campaigns</h2>
<table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
    <thead>
        <tr style="text-align:left; border-bottom:1px solid #ddd;">
            <th>Title</th>
            <th>Raised / Goal</th>
            <th>Status</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($campaigns as $campaign): ?>
        <tr style="border-bottom:1px solid #eee;">
            <td><?= e($campaign['title']) ?></td>
            <td><?= e($campaign['currency_code']) ?> <?= e(number_format((float) $campaign['raised'], 2)) ?> / <?= e(number_format((float) $campaign['goal_amount'], 2)) ?></td>
            <td><?= $campaign['is_active'] ? 'Active' : 'Inactive' ?></td>
            <td>
                <form method="post" action="<?= e(route('/admin/donations/campaigns/' . $campaign['id'] . '/toggle')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button type="submit"><?= $campaign['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if ($campaigns === []): ?>
        <tr><td colspan="4" style="color:#888;">No campaigns yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<h3>Create a campaign</h3>
<form method="post" action="<?= e(route('/admin/donations/campaigns')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <p>
        <label for="title">Title</label><br>
        <input type="text" id="title" name="title" required>
    </p>
    <p>
        <label for="description">Description</label><br>
        <textarea id="description" name="description" rows="3" cols="50"></textarea>
    </p>
    <p>
        <label for="goal_amount">Goal amount</label><br>
        <input type="text" id="goal_amount" name="goal_amount" required placeholder="1000.00">
        <input type="text" name="currency_code" value="USD" size="4" maxlength="3">
    </p>
    <p>
        <label for="payment_url">Payment link</label><br>
        <input type="text" id="payment_url" name="payment_url" required placeholder="https://paypal.me/yourclub">
    </p>
    <button type="submit">Create campaign</button>
</form>

<h2>Pending contributions (<?= count($pending) ?>)</h2>
<ul>
    <?php foreach ($pending as $contribution): ?>
        <li>
            <?= e($contribution['contributorName']) ?> — <?= e($contribution['campaign_title']) ?>
            <small style="color:#888;">(submitted <?= e($contribution['created_at']) ?>)</small>
            <form method="post" action="<?= e(route('/admin/donations/contributions/' . $contribution['id'] . '/confirm')) ?>" style="display:inline;">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <input type="text" name="amount" placeholder="Amount" size="8" required>
                <input type="text" name="notes" placeholder="Notes">
                <button type="submit">Confirm received</button>
            </form>
        </li>
    <?php endforeach; ?>
    <?php if ($pending === []): ?>
        <li style="color:#888;">Nothing pending.</li>
    <?php endif; ?>
</ul>

<h2>Confirmed contributions (<?= count($confirmed) ?>)</h2>
<ul>
    <?php foreach ($confirmed as $contribution): ?>
        <li>
            <?= e($contribution['contributorName']) ?> — <?= e($contribution['campaign_title']) ?>
            — <?= e(number_format((float) $contribution['amount'], 2)) ?>
            <small style="color:#888;">confirmed <?= e($contribution['confirmed_at']) ?></small>
        </li>
    <?php endforeach; ?>
    <?php if ($confirmed === []): ?>
        <li style="color:#888;">No confirmed contributions yet.</li>
    <?php endif; ?>
</ul>

<h3>Record a contribution directly</h3>
<p style="color:#666;">For cash/check contributions, or from someone who isn't a site member — records it as already confirmed.</p>
<form method="post" action="<?= e(route('/admin/donations/contributions')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <p>
        <label for="campaign_id">Campaign</label><br>
        <select id="campaign_id" name="campaign_id" required>
            <?php foreach ($campaigns as $campaign): ?>
                <option value="<?= (int) $campaign['id'] ?>"><?= e($campaign['title']) ?></option>
            <?php endforeach; ?>
        </select>
    </p>
    <p>
        <label for="username">Existing member's username</label><br>
        <input type="text" id="username" name="username" placeholder="leave blank if not a member">
    </p>
    <p>
        <label for="donor_name">Or donor name (no account)</label><br>
        <input type="text" id="donor_name" name="donor_name" placeholder="leave blank if using username above">
    </p>
    <p>
        <label for="direct_amount">Amount</label><br>
        <input type="text" id="direct_amount" name="amount" required placeholder="25.00">
    </p>
    <p>
        <label for="direct_notes">Notes</label><br>
        <input type="text" id="direct_notes" name="notes">
    </p>
    <button type="submit">Record contribution</button>
</form>
