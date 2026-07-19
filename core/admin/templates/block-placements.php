<?php
/**
 * @var array<int, array{id: int, key: string, label: string}> $regions
 * @var array<string, array<int, string>> $renderedCardsByRegion region key => pre-rendered card HTML strings
 * @var array<int, string> $blockTypes every registered block type
 * @var string $csrfToken
 */
?>
<h1>Block Placements</h1>
<p style="color:#666;">
    Drag a block from the palette into a region to place it there, or
    drag an existing card into a different region to move it. Each
    card's own settings (and page scope) are edited right there, with
    real fields — not raw JSON. The &uarr;/&darr; buttons still fine-tune
    order within a region precisely; dragging always drops at the end of
    a region's list.
</p>

<div id="strat-block-palette" style="display:flex;flex-wrap:wrap;gap:0.4rem;padding:0.75rem;background:#f4f5f7;border-radius:6px;margin-bottom:1.5rem;">
    <?php foreach ($blockTypes as $type): ?>
        <div class="strat-palette-item" draggable="true" data-block-type="<?= e($type) ?>"
             style="padding:0.35rem 0.6rem;background:#fff;border:1px solid #ddd;border-radius:4px;font-size:0.8rem;cursor:grab;">
            <?= e($type) ?>
        </div>
    <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(16rem, 1fr));gap:1.5rem;">
    <?php foreach ($regions as $region): ?>
        <div>
            <h2 style="font-size:1rem;"><?= e($region['label']) ?> <small style="color:#999;font-weight:normal;">(<?= e($region['key']) ?>)</small></h2>
            <div class="strat-region-dropzone" data-region-id="<?= (int) $region['id'] ?>"
                 style="min-height:4rem;background:#fafbfc;border:1px dashed #ccc;border-radius:6px;padding:0.5rem;">
                <?php foreach ($renderedCardsByRegion[$region['key']] ?? [] as $cardHtml): ?>
                    <?= $cardHtml ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
(function () {
    var csrfToken = <?= json_encode($csrfToken) ?>;
    var draggedPlacementId = null;
    var draggedBlockType = null;

    document.querySelectorAll('.strat-palette-item').forEach(function (item) {
        item.addEventListener('dragstart', function (e) {
            draggedBlockType = item.getAttribute('data-block-type');
            draggedPlacementId = null;
            e.dataTransfer.effectAllowed = 'copy';
        });
    });

    function bindCardDrag(card) {
        card.addEventListener('dragstart', function (e) {
            draggedPlacementId = card.getAttribute('data-placement-id');
            draggedBlockType = null;
            e.dataTransfer.effectAllowed = 'move';
            e.stopPropagation();
        });
    }
    document.querySelectorAll('.strat-placement-card').forEach(bindCardDrag);

    function nextWeightIn(zone) {
        var cards = zone.querySelectorAll('.strat-placement-card');
        return cards.length === 0 ? 10 : (cards.length + 1) * 10;
    }

    document.querySelectorAll('.strat-region-dropzone').forEach(function (zone) {
        zone.addEventListener('dragover', function (e) {
            e.preventDefault();
            zone.style.background = '#eef2ff';
        });
        zone.addEventListener('dragleave', function () {
            zone.style.background = '#fafbfc';
        });
        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            zone.style.background = '#fafbfc';
            var regionId = zone.getAttribute('data-region-id');
            var weight = nextWeightIn(zone);

            if (draggedBlockType) {
                fetch('<?= e(route('/admin/blocks/api/create')) ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ _csrf: csrfToken, block_type: draggedBlockType, region_id: regionId, weight: weight })
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.cardHtml) {
                            var wrapper = document.createElement('div');
                            wrapper.innerHTML = data.cardHtml;
                            var newCard = wrapper.firstElementChild;
                            zone.appendChild(newCard);
                            bindCardDrag(newCard);
                        }
                    });
            } else if (draggedPlacementId) {
                var card = document.querySelector('[data-placement-id="' + draggedPlacementId + '"]');
                if (card) {
                    zone.appendChild(card);
                }
                fetch('<?= e(route('/admin/blocks/api/move')) ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ _csrf: csrfToken, id: draggedPlacementId, region_id: regionId, weight: weight })
                });
            }

            draggedBlockType = null;
            draggedPlacementId = null;
        });
    });
})();
</script>
