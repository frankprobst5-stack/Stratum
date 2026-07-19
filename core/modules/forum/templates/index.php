<?php
/**
 * @var array<int, array{id: int, name: string, slug: string, boards: array<int, array<string, mixed>>}> $categories
 * each board in $category['boards'] carries a 'children' array of its own sub-boards (ForumService::nestBoards())
 */

// A recursive closure, not a global function declaration — this template
// file gets include()'d fresh on every request, so a top-level `function`
// here would fatal with "Cannot redeclare" the moment two forum pages
// render in the same process (already true of the PHP dev server, and of
// any future long-running worker/opcache setup).
$renderBoardRow = function (array $board, int $depth) use (&$renderBoardRow): void {
    ?>
    <div class="strat-list-row<?= $depth > 0 ? ' is-sub' : '' ?>">
        <div class="strat-list-row-icon" aria-hidden="true"><?= $depth > 0 ? "\u{21B3}" : "\u{1F4AC}" ?></div>
        <div class="strat-list-row-main">
            <div class="strat-list-row-title">
                <a href="<?= e(route('/forum/boards/' . $board['slug'])) ?>"><?= e($board['name']) ?></a>
            </div>
            <?php if (!empty($board['description'])): ?>
                <div class="strat-list-row-meta"><?= e($board['description']) ?></div>
            <?php endif; ?>
        </div>
        <div class="strat-list-row-stats">
            <div><strong><?= (int) $board['topic_count'] ?></strong> topics</div>
            <div><strong><?= (int) $board['post_count'] ?></strong> posts</div>
        </div>
        <div class="strat-list-row-lastpost">
            <?php if (($board['last_post_username'] ?? null) !== null): ?>
                <a href="<?= e(route('/forum/topics/' . $board['last_post_topic_id'])) ?>"><?= e((string) $board['last_post_topic_title']) ?></a><br>
                by <strong><?= e((string) $board['last_post_username']) ?></strong>
            <?php else: ?>
                No posts yet
            <?php endif; ?>
        </div>
    </div>
    <?php
    foreach ($board['children'] as $child) {
        $renderBoardRow($child, $depth + 1);
    }
};
?>
<h1>Forum</h1>

<?php if ($categories === []): ?>
    <p class="strat-muted">No forum categories yet.</p>
<?php endif; ?>

<?php foreach ($categories as $category): ?>
    <h2><?= e($category['name']) ?></h2>
    <div class="strat-list">
        <?php foreach ($category['boards'] as $board): ?>
            <?php $renderBoardRow($board, 0) ?>
        <?php endforeach; ?>
        <?php if ($category['boards'] === []): ?>
            <p class="strat-muted">No boards in this category yet.</p>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
