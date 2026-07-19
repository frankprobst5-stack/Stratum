<?php
/**
 * @var array<string, mixed> $listing
 * @var string $sellerName
 * @var bool $canManage
 * @var string $csrfToken
 */
?>
<p><a href="<?= e(route('/classifieds')) ?>">&larr; Classifieds</a></p>

<h1>
    <?= e($listing['title']) ?>
    <?php if ($listing['status'] === 'sold'): ?><strong>(sold)</strong><?php endif; ?>
</h1>

<?php if ($listing['filename'] !== null): ?>
    <img src="<?= e(route('/classifieds/listings/' . $listing['id'] . '/image')) ?>" alt="" style="max-width:100%;">
<?php endif; ?>

<?php if ($listing['price'] !== null): ?>
    <p><strong><?= e(number_format((float) $listing['price'], 2)) ?></strong></p>
<?php endif; ?>

<?php if (!empty($listing['description'])): ?>
    <div style="white-space:pre-wrap;"><?= e($listing['description']) ?></div>
<?php endif; ?>

<p style="color:#888;">Posted by <?= e($sellerName) ?></p>

<?php if ($canManage): ?>
    <?php if ($listing['status'] !== 'sold'): ?>
        <form method="post" action="<?= e(route('/classifieds/listings/' . $listing['id'] . '/sold')) ?>" style="display:inline;">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <button type="submit">Mark sold</button>
        </form>
    <?php endif; ?>
    <form method="post" action="<?= e(route('/classifieds/listings/' . $listing['id'] . '/delete')) ?>" style="display:inline;">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <button type="submit">Delete listing</button>
    </form>
<?php endif; ?>
