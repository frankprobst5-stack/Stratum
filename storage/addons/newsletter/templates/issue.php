<?php
/**
 * @var array<string, mixed> $issue
 * @var array<string, mixed> $page current page
 * @var string $renderedBody
 * @var array<int, array<string, mixed>> $toc every page in this issue, ordered by position
 * @var int $position 1-indexed position of the current page
 * @var int $pageCount
 */
?>
<p><a href="<?= e(route('/newsletter')) ?>">&larr; Newsletter</a></p>

<div style="display:flex; gap:1.5rem; align-items:flex-start;">
    <article style="flex:1; min-width:0;">
        <h1><?= e($issue['title']) ?></h1>
        <h2><?= e($page['title']) ?></h2>

        <div style="white-space:pre-wrap;"><?= raw($renderedBody) ?></div>

        <p style="margin-top:1.5rem; display:flex; justify-content:space-between;">
            <?php if ($position > 1): ?>
                <a href="<?= e(route('/newsletter/' . $issue['slug'] . '/' . ($position - 1))) ?>">&larr; Previous</a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
            <?php if ($position < $pageCount): ?>
                <a href="<?= e(route('/newsletter/' . $issue['slug'] . '/' . ($position + 1))) ?>">Next &rarr;</a>
            <?php endif; ?>
        </p>
    </article>

    <aside style="width:14rem; flex-shrink:0;">
        <strong>In this issue</strong>
        <div class="strat-list" style="margin-top:0.5rem;">
            <?php foreach ($toc as $tocPage): ?>
                <?php $isCurrent = (int) $tocPage['id'] === (int) $page['id']; ?>
                <div class="strat-list-row" style="padding:0.5rem 0.75rem;<?= $isCurrent ? ' border-color:var(--strat-accent);' : '' ?>">
                    <div class="strat-list-row-main">
                        <?php if ($isCurrent): ?>
                            <strong><?= e($tocPage['title']) ?></strong>
                        <?php else: ?>
                            <a href="<?= e(route('/newsletter/' . $issue['slug'] . '/' . $tocPage['position'])) ?>"><?= e($tocPage['title']) ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </aside>
</div>
