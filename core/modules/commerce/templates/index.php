<?php
/**
 * @var array<int, array<string, mixed>> $products each with 'download_title', 'download_description'
 */
?>
<h1>Shop</h1>

<?php if ($products === []): ?>
    <p class="strat-muted">Nothing for sale yet.</p>
<?php else: ?>
    <div class="strat-list">
        <?php foreach ($products as $product): ?>
            <div class="strat-list-row">
                <div class="strat-list-row-icon" aria-hidden="true">🛒</div>
                <div class="strat-list-row-main">
                    <div class="strat-list-row-title">
                        <a href="<?= e(route('/shop/products/' . $product['id'])) ?>"><?= e($product['download_title']) ?></a>
                    </div>
                    <?php if (!empty($product['download_description'])): ?>
                        <div class="strat-list-row-meta"><?= e($product['download_description']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="strat-list-row-stats">
                    <div><strong>$<?= e(number_format((float) $product['price'], 2)) ?></strong></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
