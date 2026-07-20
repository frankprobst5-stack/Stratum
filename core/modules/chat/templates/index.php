<?php
/**
 * @var array<int, array<string, mixed>> $rooms public rooms only, most-recently-active first
 * @var bool $isLoggedIn
 * @var string $csrfToken
 */
?>
<h1>Chat</h1>

<?php if ($rooms === []): ?>
    <p class="strat-muted">No chat rooms yet<?= $isLoggedIn ? ' — start one below.' : '.' ?></p>
<?php else: ?>
    <div class="strat-list">
        <?php foreach ($rooms as $room): ?>
            <?php $href = $isLoggedIn ? route('/chat/rooms/' . $room['id']) : null; ?>
            <div class="strat-list-row">
                <div class="strat-list-row-icon" aria-hidden="true">💬</div>
                <div class="strat-list-row-main">
                    <div class="strat-list-row-title">
                        <?php if ($href !== null): ?>
                            <a href="<?= e($href) ?>"><?= e($room['name']) ?></a>
                        <?php else: ?>
                            <?= e($room['name']) ?>
                        <?php endif; ?>
                        <?php if ($room['source'] === 'user'): ?>
                            <span class="strat-pill" data-tone="neutral">member room</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($room['topic'])): ?>
                        <div class="strat-list-row-meta"><?= e($room['topic']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="strat-list-row-stats">
                    <div><strong><?= (int) $room['member_count'] ?></strong> members</div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!$isLoggedIn): ?>
    <p class="strat-muted"><a href="<?= e(route('/login')) ?>">Log in</a> to join a room or start your own.</p>
<?php else: ?>
    <h2>Start a room</h2>
    <p class="strat-muted">
        Always public — anyone can join. It sticks around while people are in
        it and disappears on its own once everyone's left.
    </p>
    <form method="post" action="<?= e(route('/chat/rooms/create')) ?>" style="max-width:28rem;">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <p>
            <label style="display:block;" class="strat-muted">Room name
                <input type="text" name="name" required style="width:100%;">
            </label>
        </p>
        <p>
            <label style="display:block;" class="strat-muted">Topic (optional)
                <input type="text" name="topic" style="width:100%;">
            </label>
        </p>
        <button type="submit">Start room</button>
    </form>
<?php endif; ?>
