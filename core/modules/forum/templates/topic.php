<?php
/**
 * @var array<string, mixed> $topic
 * @var array<string, mixed>|null $board
 * @var array<int, array<string, mixed>> $posts each with 'authorName', 'renderedBody', 'attachments'
 * @var array{id: int, question: string, closes_at: ?string, isClosed: bool, options: array<int, array{id: int, label: string, votes: int}>, totalVotes: int}|null $poll
 * @var ?int $myPollVote
 * @var bool $canReply
 * @var bool $canModerate
 * @var bool $canReport
 * @var bool $showBookmark
 * @var bool $isBookmarked
 * @var array<int, array{name: string, slug: string}> $tags
 * @var bool $isLoggedIn
 * @var string $csrfToken
 */
$initials = static fn (string $name): string => strtoupper(substr($name, 0, 2));
?>
<?php if ($board !== null): ?>
    <p><a href="<?= e(route('/forum/boards/' . $board['slug'])) ?>">&larr; <?= e($board['name']) ?></a></p>
<?php endif; ?>

<h1>
    <?php if ($topic['is_pinned']): ?><span class="strat-pill" data-tone="accent">Pinned</span> <?php endif; ?>
    <?php if ($topic['is_locked']): ?><span class="strat-pill" data-tone="neutral">Locked</span> <?php endif; ?>
    <?= e($topic['title']) ?>
</h1>

<?php if ($tags !== []): ?>
    <p>
        <?php foreach ($tags as $tag): ?>
            <a class="strat-pill" data-tone="accent" href="<?= e(route('/tags/' . $tag['slug'])) ?>"><?= e($tag['name']) ?></a>
        <?php endforeach; ?>
    </p>
<?php endif; ?>

<?php if ($showBookmark): ?>
    <form method="post" action="<?= e(route('/bookmarks/toggle')) ?>" style="margin-bottom:0.75rem;">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="bookmarkable_type" value="forum_topic">
        <input type="hidden" name="bookmarkable_id" value="<?= (int) $topic['id'] ?>">
        <input type="hidden" name="redirect_to" value="<?= e(route('/forum/topics/' . $topic['id'])) ?>">
        <button type="submit"><?= $isBookmarked ? '&#9733; Bookmarked' : '&#9734; Bookmark' ?></button>
    </form>
<?php endif; ?>

<?php if ($canModerate): ?>
    <p>
        <?php foreach ([
            ['pin', $topic['is_pinned'] ? null : 'Pin'],
            ['unpin', $topic['is_pinned'] ? 'Unpin' : null],
            ['lock', $topic['is_locked'] ? null : 'Lock'],
            ['unlock', $topic['is_locked'] ? 'Unlock' : null],
        ] as [$action, $label]): ?>
            <?php if ($label !== null): ?>
                <form method="post" action="<?= e(route('/forum/topics/' . $topic['id'] . '/' . $action)) ?>" style="display:inline;">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button type="submit"><?= e($label) ?></button>
                </form>
            <?php endif; ?>
        <?php endforeach; ?>
        <form method="post" action="<?= e(route('/forum/topics/' . $topic['id'] . '/delete')) ?>" style="display:inline;">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <button type="submit">Delete topic</button>
        </form>
    </p>
<?php endif; ?>

<?php if ($poll !== null): ?>
    <div class="strat-poll">
        <strong><?= e($poll['question']) ?></strong>
        <?php if ($poll['closes_at'] !== null): ?>
            <small class="strat-muted"> &middot; <?= $poll['isClosed'] ? 'Closed' : 'Closes' ?> <?= e($poll['closes_at']) ?></small>
        <?php endif; ?>

        <?php if ($myPollVote === null && !$poll['isClosed'] && $isLoggedIn): ?>
            <form method="post" action="<?= e(route('/forum/topics/' . $topic['id'] . '/poll/vote')) ?>" style="margin-top:0.5rem;">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <?php foreach ($poll['options'] as $option): ?>
                    <p style="margin:0.25rem 0;">
                        <label>
                            <input type="radio" name="option_id" value="<?= (int) $option['id'] ?>" required>
                            <?= e($option['label']) ?>
                        </label>
                    </p>
                <?php endforeach; ?>
                <button type="submit">Vote</button>
            </form>
        <?php else: ?>
            <?php if (!$isLoggedIn): ?>
                <p class="strat-muted" style="margin:0.5rem 0 0.25rem;"><a href="<?= e(route('/login')) ?>">Log in</a> to vote. Current results:</p>
            <?php endif; ?>
            <div style="margin-top:0.5rem;">
                <?php foreach ($poll['options'] as $option): ?>
                    <?php $pct = $poll['totalVotes'] > 0 ? round($option['votes'] / $poll['totalVotes'] * 100) : 0; ?>
                    <div class="strat-poll-option-row">
                        <div class="strat-poll-option-header">
                            <span><?= e($option['label']) ?><?= $myPollVote === $option['id'] ? ' &#10003;' : '' ?></span>
                            <span class="strat-muted"><?= (int) $option['votes'] ?> (<?= $pct ?>%)</span>
                        </div>
                        <div class="strat-poll-bar-track">
                            <div class="strat-poll-bar-fill" style="width:<?= $pct ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($myPollVote !== null && !$poll['isClosed'] && $isLoggedIn): ?>
                <details style="margin-top:0.5rem;">
                    <summary style="cursor:pointer; color:var(--strat-muted-text); font-size:0.85rem;">Change your vote</summary>
                    <form method="post" action="<?= e(route('/forum/topics/' . $topic['id'] . '/poll/vote')) ?>" style="margin-top:0.5rem;">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <?php foreach ($poll['options'] as $option): ?>
                            <p style="margin:0.25rem 0;">
                                <label>
                                    <input type="radio" name="option_id" value="<?= (int) $option['id'] ?>" <?= $myPollVote === $option['id'] ? 'checked' : '' ?> required>
                                    <?= e($option['label']) ?>
                                </label>
                            </p>
                        <?php endforeach; ?>
                        <button type="submit">Update vote</button>
                    </form>
                </details>
            <?php endif; ?>
            <small class="strat-muted"><?= (int) $poll['totalVotes'] ?> vote<?= $poll['totalVotes'] === 1 ? '' : 's' ?> total.</small>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php foreach ($posts as $post): ?>
    <div class="strat-post">
        <div class="strat-post-author">
            <div class="strat-avatar"><?= e($initials((string) $post['authorName'])) ?></div>
            <div class="strat-post-author-name"><?= e($post['authorName']) ?></div>
        </div>
        <div class="strat-post-body">
            <div class="strat-post-meta"><?= e($post['created_at']) ?></div>
            <div class="strat-post-content"><?= raw($post['renderedBody']) ?></div>

            <?php if (($post['authorSignature'] ?? '') !== ''): ?>
                <div class="strat-post-signature"><?= raw($post['authorSignature']) ?></div>
            <?php endif; ?>

            <?php if ($post['attachments'] !== []): ?>
                <ul>
                    <?php foreach ($post['attachments'] as $attachment): ?>
                        <li>
                            <a href="<?= e(route('/forum/attachments/' . $attachment['id'])) ?>">
                                <?= e($attachment['original_name']) ?>
                            </a>
                            <small class="strat-muted">(<?= (int) $attachment['size'] ?> bytes)</small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="strat-post-footer">
                <?php if ($isLoggedIn): ?>
                    <form method="post" action="<?= e(route('/forum/posts/' . $post['id'] . '/like')) ?>" style="display:inline;">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit"><?= $post['likedByMe'] ? '&#9829; Unlike' : '&#9825; Like' ?></button>
                    </form>
                <?php endif; ?>
                <?php if ($post['likeCount'] > 0): ?>
                    <small class="strat-muted">&#9829; <?= (int) $post['likeCount'] ?></small>
                <?php endif; ?>
                <?php if ($canModerate): ?>
                    <form method="post" action="<?= e(route('/forum/posts/' . $post['id'] . '/delete')) ?>" style="display:inline;">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit">Delete post</button>
                    </form>
                <?php endif; ?>
                <?php if ($canReport): ?>
                    <a href="<?= e(route('/reports/new?type=forum_post&id=' . $post['id'])) ?>" class="strat-muted">Report</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php if ($topic['is_locked'] && !$canModerate): ?>
    <p class="strat-muted">This topic is locked.</p>
<?php elseif ($canReply): ?>
    <h2>Reply</h2>
    <form method="post" action="<?= e(route('/forum/topics/' . $topic['id'] . '/reply')) ?>" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <p>
            <textarea name="body" rows="4" cols="60" required data-bbcode-toolbar></textarea><br>
            <small class="strat-muted">Supports [b] [i] [u] [url] [quote] [code]</small>
        </p>
        <p>
            <label for="attachment">Attachment (optional)</label><br>
            <input type="file" id="attachment" name="attachment">
        </p>
        <button type="submit">Post reply</button>
    </form>
<?php elseif ($isLoggedIn): ?>
    <p class="strat-muted">You don't have permission to reply.</p>
<?php else: ?>
    <p><a href="<?= e(route('/login')) ?>">Log in</a> to reply.</p>
<?php endif; ?>
