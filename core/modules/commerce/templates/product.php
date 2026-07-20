<?php
/**
 * @var array<string, mixed> $product with 'download_title', 'download_description'
 * @var bool $hasPurchased
 * @var bool $hasPending
 * @var bool $canPurchase
 * @var bool $isLoggedIn
 * @var string $csrfToken
 */
?>
<p><a href="<?= e(route('/shop')) ?>">&larr; Shop</a></p>

<h1><?= e($product['download_title']) ?></h1>

<?php if (!empty($product['download_description'])): ?>
    <div style="white-space:pre-wrap;"><?= e($product['download_description']) ?></div>
<?php endif; ?>

<p><strong>$<?= e(number_format((float) $product['price'], 2)) ?></strong></p>

<?php if ($hasPurchased): ?>
    <p><a href="<?= e(route('/shop/products/' . $product['id'] . '/download')) ?>">Download &rarr;</a></p>
<?php elseif ($hasPending): ?>
    <p class="strat-muted">You've indicated you're purchasing this — an admin will confirm once payment is received, and your download link will appear here.</p>
<?php elseif ($canPurchase): ?>
    <p><a href="<?= e($product['payment_url']) ?>" target="_blank" rel="noopener">Pay via Cash App &rarr;</a></p>
    <form method="post" action="<?= e(route('/shop/products/' . $product['id'] . '/purchase')) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <button type="submit">I've paid</button>
    </form>
<?php else: ?>
    <p><a href="<?= e(route('/login')) ?>">Log in</a> to purchase.</p>
<?php endif; ?>
