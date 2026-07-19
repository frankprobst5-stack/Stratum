<?php
/**
 * @var array<string, mixed> $board
 * @var array<int, array<string, mixed>> $subBoards
 * @var array<int, array<string, mixed>> $topics each with 'authorName'
 * @var bool $canCreateTopic
 * @var bool $showTags
 * @var bool $isLoggedIn
 * @var string $csrfToken
 */
?>
<p><a href="<?= e(route('/forum')) ?>">&larr; Forum</a></p>
<h1><?= e($board['name']) ?></h1>
<?php if (!empty($board['description'])): ?>
    <p class="strat-muted"><?= e($board['description']) ?></p>
<?php endif; ?>

<?php if ($subBoards !== []): ?>
    <h2>Sub-boards</h2>
    <div class="strat-list">
        <?php foreach ($subBoards as $sub): ?>
            <div class="strat-list-row">
                <div class="strat-list-row-icon" aria-hidden="true">&#128172;</div>
                <div class="strat-list-row-main">
                    <div class="strat-list-row-title">
                        <a href="<?= e(route('/forum/boards/' . $sub['slug'])) ?>"><?= e($sub['name']) ?></a>
                    </div>
                    <?php if (!empty($sub['description'])): ?>
                        <div class="strat-list-row-meta"><?= e($sub['description']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<h2>Topics</h2>
<div class="strat-list">
    <?php foreach ($topics as $topic): ?>
        <div class="strat-list-row">
            <div class="strat-list-row-icon" aria-hidden="true"><?= $topic['is_locked'] ? "\u{1F512}" : "\u{1F4AC}" ?></div>
            <div class="strat-list-row-main">
                <div class="strat-list-row-title">
                    <?php if ($topic['is_pinned']): ?><span class="strat-pill" data-tone="accent">Pinned</span> <?php endif; ?>
                    <?php if ($topic['is_locked']): ?><span class="strat-pill" data-tone="neutral">Locked</span> <?php endif; ?>
                    <a href="<?= e(route('/forum/topics/' . $topic['id'])) ?>"><?= e($topic['title']) ?></a>
                </div>
                <div class="strat-list-row-meta">by <?= e($topic['authorName']) ?></div>
            </div>
            <div class="strat-list-row-stats">
                <div><strong><?= max(0, (int) $topic['reply_count']) ?></strong> replies</div>
            </div>
            <div class="strat-list-row-lastpost">
                <?php if (($topic['last_post_username'] ?? null) !== null): ?>
                    by <strong><?= e((string) $topic['last_post_username']) ?></strong>
                <?php else: ?>
                    &mdash;
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if ($topics === []): ?>
        <p class="strat-muted">No topics yet.</p>
    <?php endif; ?>
</div>

<?php if ($canCreateTopic): ?>
    <h2>New topic</h2>
    <form method="post" action="<?= e(route('/forum/boards/' . $board['slug'] . '/topics')) ?>" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <p>
            <label for="title">Title</label><br>
            <input type="text" id="title" name="title" required style="width:100%; max-width:500px;">
        </p>
        <p>
            <label for="body">Message</label><br>
            <textarea id="body" name="body" rows="6" cols="60" required data-bbcode-toolbar></textarea><br>
            <small class="strat-muted">Supports [b] [i] [u] [url] [quote] [code]</small>
        </p>
        <p>
            <label for="attachment">Attachment (optional)</label><br>
            <input type="file" id="attachment" name="attachment">
        </p>
        <?php if ($showTags): ?>
            <p>
                <label for="tags">Tags (comma separated)</label><br>
                <input type="text" id="tags" name="tags" style="width:100%; max-width:500px;" placeholder="e.g. question, help-wanted">
            </p>
        <?php endif; ?>
        <details>
            <summary>Add a poll (optional)</summary>
            <p>
                <label for="poll_question">Poll question</label><br>
                <input type="text" id="poll_question" name="poll_question" style="width:100%; max-width:500px;">
            </p>
            <?php for ($i = 1; $i <= 6; $i++): ?>
                <p>
                    <label for="poll_option_<?= $i ?>">Option <?= $i ?><?= $i > 2 ? ' (optional)' : '' ?></label><br>
                    <input type="text" id="poll_option_<?= $i ?>" name="poll_option_<?= $i ?>">
                </p>
            <?php endfor; ?>
            <p>
                <label for="poll_closes_at">Closes at (optional)</label><br>
                <input type="datetime-local" id="poll_closes_at" name="poll_closes_at">
            </p>
            <small class="strat-muted">Leave the question blank to skip the poll. At least 2 options are needed for a poll to be created.</small>
        </details>
        <p><button type="submit">Post topic</button></p>
    </form>
<?php elseif ($isLoggedIn): ?>
    <p class="strat-muted">You don't have permission to start a topic here.</p>
<?php else: ?>
    <p><a href="<?= e(route('/login')) ?>">Log in</a> to start a topic.</p>
<?php endif; ?>
