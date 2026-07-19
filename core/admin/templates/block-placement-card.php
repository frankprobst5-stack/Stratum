<?php
/**
 * One placement's card — block type, its real settings form (generated
 * from ConfigurableBlock::configFields(), not raw JSON), and action
 * buttons. Rendered both for the main /admin/blocks page load (existing
 * placements) and as the AJAX response body when a block is dropped
 * from the palette into a region — one template, not duplicated in JS.
 *
 * @var array<string, mixed> $placement
 * @var array<int, array<string, mixed>> $configFields empty if the block takes no config
 * @var array<string, mixed> $currentConfig
 * @var string $csrfToken
 */
use Stratum\Core\BlockConfigForm;
?>
<div class="strat-placement-card" draggable="true" data-placement-id="<?= (int) $placement['id'] ?>" style="border:1px solid #ddd;border-radius:6px;padding:0.75rem;margin-bottom:0.5rem;background:#fff;cursor:grab;">
    <div style="display:flex;justify-content:space-between;align-items:center;">
        <strong style="font-size:0.85rem;"><?= e($placement['block_type']) ?></strong>
        <span style="font-size:0.75rem;color:#888;"><?= $placement['is_enabled'] ? 'Enabled' : 'Disabled' ?></span>
    </div>

    <form method="post" action="<?= e(route('/admin/blocks/' . $placement['id'] . '/config')) ?>" style="margin-top:0.5rem;">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <p style="margin:0.5rem 0;">
            <label style="display:block;font-size:0.8rem;color:#666;margin-bottom:0.2rem;">Page scope</label>
            <input type="text" name="page_scope" value="<?= e($placement['page_scope']) ?>" style="width:100%;max-width:28rem;" placeholder="site_wide">
        </p>
        <?php if ($configFields !== []): ?>
            <?= BlockConfigForm::render($configFields, $currentConfig) ?>
        <?php endif; ?>
        <button type="submit" style="font-size:0.8rem;">Save settings</button>
    </form>

    <div style="margin-top:0.5rem;white-space:nowrap;">
        <form method="post" action="<?= e(route('/admin/blocks/' . $placement['id'] . '/move-up')) ?>" style="display:inline;">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <button type="submit" title="Move up">&uarr;</button>
        </form>
        <form method="post" action="<?= e(route('/admin/blocks/' . $placement['id'] . '/move-down')) ?>" style="display:inline;">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <button type="submit" title="Move down">&darr;</button>
        </form>
        <form method="post" action="<?= e(route('/admin/blocks/' . $placement['id'] . '/toggle')) ?>" style="display:inline;">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <button type="submit"><?= $placement['is_enabled'] ? 'Disable' : 'Enable' ?></button>
        </form>
        <form method="post" action="<?= e(route('/admin/blocks/' . $placement['id'] . '/delete')) ?>" style="display:inline;">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <button type="submit">Delete</button>
        </form>
    </div>
</div>
