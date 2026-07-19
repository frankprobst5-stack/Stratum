<?php
/**
 * @var array<int, array<string, mixed>> $categories each with 'listings'
 * @var bool $canPost
 */
?>
<h1>Classifieds</h1>

<?php if ($canPost): ?>
    <p><a href="<?= e(route('/classifieds/create')) ?>">Post a listing</a></p>
<?php endif; ?>

<?php foreach ($categories as $category): ?>
    <h2><?= e($category['name']) ?></h2>
    <ul>
        <?php foreach ($category['listings'] as $listing): ?>
            <li style="margin-bottom:0.5rem;">
                <?php if ($listing['thumbnail_filename'] !== null): ?>
                    <a href="<?= e(route('/classifieds/listings/' . $listing['id'])) ?>">
                        <img src="<?= e(route('/classifieds/listings/' . $listing['id'] . '/thumbnail')) ?>" alt="" width="80">
                    </a>
                <?php endif; ?>
                <a href="<?= e(route('/classifieds/listings/' . $listing['id'])) ?>"><?= e($listing['title']) ?></a>
                <?php if ($listing['price'] !== null): ?>
                    — <?= e(number_format((float) $listing['price'], 2)) ?>
                <?php endif; ?>
                <?php if ($listing['status'] === 'sold'): ?>
                    <strong>(sold)</strong>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
        <?php if ($category['listings'] === []): ?>
            <li style="color:#888;">No listings yet.</li>
        <?php endif; ?>
    </ul>
<?php endforeach; ?>
<?php if ($categories === []): ?>
    <p style="color:#888;">No categories yet.</p>
<?php endif; ?>
