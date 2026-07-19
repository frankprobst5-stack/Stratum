<?php
/**
 * @var array<int, array<string, mixed>> $advertisers
 * @var array<int, array<string, mixed>> $campaigns each with 'advertiser_name'
 * @var array<int, array<string, mixed>> $banners each with 'campaign_name', 'advertiser_name', 'ctr'
 * @var array<int, string> $zones
 * @var string $csrfToken
 */
?>
<h1>Advertising</h1>

<h2>Advertisers</h2>
<table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
    <thead>
        <tr style="text-align:left; border-bottom:1px solid #ddd;">
            <th>Name</th>
            <th>Contact</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($advertisers as $advertiser): ?>
        <tr style="border-bottom:1px solid #eee;">
            <td><?= e($advertiser['name']) ?></td>
            <td>
                <?= e($advertiser['contact_name'] ?? '') ?>
                <?php if (!empty($advertiser['contact_email'])): ?>
                    &lt;<?= e($advertiser['contact_email']) ?>&gt;
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if ($advertisers === []): ?>
        <tr><td colspan="2" style="color:#888;">No advertisers yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<form method="post" action="<?= e(route('/admin/ads/advertisers')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <input type="text" name="name" placeholder="Advertiser name" required>
    <input type="text" name="contact_name" placeholder="Contact name">
    <input type="email" name="contact_email" placeholder="Contact email">
    <button type="submit">Add advertiser</button>
</form>

<h2>Campaigns</h2>
<table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
    <thead>
        <tr style="text-align:left; border-bottom:1px solid #ddd;">
            <th>Name</th>
            <th>Advertiser</th>
            <th>Window</th>
            <th>Active</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($campaigns as $campaign): ?>
        <tr style="border-bottom:1px solid #eee;">
            <td><?= e($campaign['name']) ?></td>
            <td><?= e($campaign['advertiser_name']) ?></td>
            <td>
                <?= e($campaign['starts_at'] ?? 'any time') ?>
                &rarr;
                <?= e($campaign['ends_at'] ?? 'no end') ?>
            </td>
            <td><?= $campaign['is_active'] ? 'Yes' : 'No' ?></td>
            <td>
                <form method="post" action="<?= e(route('/admin/ads/campaigns/' . $campaign['id'] . '/toggle')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button type="submit"><?= $campaign['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if ($campaigns === []): ?>
        <tr><td colspan="5" style="color:#888;">No campaigns yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<form method="post" action="<?= e(route('/admin/ads/campaigns')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <select name="advertiser_id" required>
        <option value="">Advertiser&hellip;</option>
        <?php foreach ($advertisers as $advertiser): ?>
            <option value="<?= (int) $advertiser['id'] ?>"><?= e($advertiser['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="text" name="name" placeholder="Campaign name" required>
    <label>Starts <input type="date" name="starts_at"></label>
    <label>Ends <input type="date" name="ends_at"></label>
    <button type="submit">Add campaign</button>
</form>

<h2>Banners</h2>
<table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
    <thead>
        <tr style="text-align:left; border-bottom:1px solid #ddd;">
            <th>Zone</th>
            <th>Campaign</th>
            <th>Advertiser</th>
            <th>Impressions</th>
            <th>Clicks</th>
            <th>CTR</th>
            <th>Active</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($banners as $banner): ?>
        <tr style="border-bottom:1px solid #eee;">
            <td><?= e($banner['zone']) ?></td>
            <td><?= e($banner['campaign_name']) ?></td>
            <td><?= e($banner['advertiser_name']) ?></td>
            <td><?= (int) $banner['impression_count'] ?></td>
            <td><?= (int) $banner['click_count'] ?></td>
            <td><?= number_format($banner['ctr'], 2) ?>%</td>
            <td><?= $banner['is_active'] ? 'Yes' : 'No' ?></td>
            <td>
                <form method="post" action="<?= e(route('/admin/ads/banners/' . $banner['id'] . '/toggle')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button type="submit"><?= $banner['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if ($banners === []): ?>
        <tr><td colspan="8" style="color:#888;">No banners yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<form method="post" action="<?= e(route('/admin/ads/banners')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <select name="campaign_id" required>
        <option value="">Campaign&hellip;</option>
        <?php foreach ($campaigns as $campaign): ?>
            <option value="<?= (int) $campaign['id'] ?>"><?= e($campaign['name']) ?> (<?= e($campaign['advertiser_name']) ?>)</option>
        <?php endforeach; ?>
    </select>
    <select name="zone" required>
        <option value="">Zone&hellip;</option>
        <?php foreach ($zones as $zone): ?>
            <option value="<?= e($zone) ?>"><?= e($zone) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="url" name="image_url" placeholder="Image URL" required style="width:20rem;">
    <input type="url" name="link_url" placeholder="Destination URL" required style="width:20rem;">
    <input type="text" name="alt_text" placeholder="Alt text">
    <button type="submit">Add banner</button>
</form>
