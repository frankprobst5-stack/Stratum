<?php
/**
 * @var array<int, array<string, mixed>> $products each with 'download_title'
 * @var array<int, array<string, mixed>> $availableFiles downloads_files not yet sold as a product
 * @var array<int, array<string, mixed>> $pending each with 'download_title', 'purchaserName'
 * @var array<int, array<string, mixed>> $confirmed each with 'download_title', 'purchaserName'
 * @var string $csrfToken
 */
?>
<h1>Shop</h1>

<h2>Products</h2>
<table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
    <thead>
        <tr style="text-align:left; border-bottom:1px solid #ddd;">
            <th>Download</th>
            <th>Price</th>
            <th>Status</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($products as $product): ?>
        <tr style="border-bottom:1px solid #eee;">
            <td><?= e($product['download_title']) ?></td>
            <td>$<?= e(number_format((float) $product['price'], 2)) ?></td>
            <td><?= $product['is_active'] ? 'Active' : 'Inactive' ?></td>
            <td>
                <form method="post" action="<?= e(route('/admin/commerce/products/' . $product['id'] . '/toggle')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button type="submit"><?= $product['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if ($products === []): ?>
        <tr><td colspan="4" class="strat-muted">No products yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<h3>Sell a download</h3>
<?php if ($availableFiles === []): ?>
    <p class="strat-muted">Every existing download already has a product, or there are no downloads to sell yet — add one via the Downloads module first.</p>
<?php else: ?>
    <form method="post" action="<?= e(route('/admin/commerce/products')) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <p>
            <label for="download_file_id">Download</label><br>
            <select id="download_file_id" name="download_file_id" required>
                <?php foreach ($availableFiles as $file): ?>
                    <option value="<?= (int) $file['id'] ?>"><?= e($file['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="price">Price</label><br>
            <input type="text" id="price" name="price" required placeholder="9.99">
        </p>
        <p>
            <label for="payment_url">Cash App payment link</label><br>
            <input type="text" id="payment_url" name="payment_url" required placeholder="https://cash.app/$yourclub">
        </p>
        <button type="submit">Create product</button>
    </form>
<?php endif; ?>

<h2>Pending purchases (<?= count($pending) ?>)</h2>
<div class="strat-list">
    <?php foreach ($pending as $purchase): ?>
        <div class="strat-list-row">
            <div class="strat-list-row-main">
                <div class="strat-list-row-title"><?= e($purchase['purchaserName']) ?> &mdash; <?= e($purchase['download_title']) ?></div>
                <div class="strat-list-row-meta">submitted <?= e($purchase['created_at']) ?></div>
            </div>
            <form method="post" action="<?= e(route('/admin/commerce/purchases/' . $purchase['id'] . '/confirm')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <input type="text" name="amount" placeholder="Amount" size="8" required>
                <input type="text" name="notes" placeholder="Notes">
                <button type="submit">Confirm received</button>
            </form>
        </div>
    <?php endforeach; ?>
    <?php if ($pending === []): ?>
        <p class="strat-muted">Nothing pending.</p>
    <?php endif; ?>
</div>

<h2>Confirmed purchases (<?= count($confirmed) ?>)</h2>
<div class="strat-list">
    <?php foreach ($confirmed as $purchase): ?>
        <div class="strat-list-row">
            <div class="strat-list-row-main">
                <div class="strat-list-row-title"><?= e($purchase['purchaserName']) ?> &mdash; <?= e($purchase['download_title']) ?></div>
                <div class="strat-list-row-meta">$<?= e(number_format((float) $purchase['amount'], 2)) ?> &middot; confirmed <?= e($purchase['confirmed_at']) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if ($confirmed === []): ?>
        <p class="strat-muted">No confirmed purchases yet.</p>
    <?php endif; ?>
</div>
