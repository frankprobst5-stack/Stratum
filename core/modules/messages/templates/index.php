<?php
/**
 * @var array<int, array<string, mixed>> $conversations each with 'otherUsername', 'unreadCount'
 * @var string $csrfToken
 */
?>
<h1>Messages</h1>

<?php if ($conversations === []): ?>
    <p class="strat-muted">No conversations yet.</p>
<?php else: ?>
    <div class="strat-list">
        <?php foreach ($conversations as $conversation): ?>
            <div class="strat-list-row">
                <div class="strat-avatar"><?= e(strtoupper(substr((string) $conversation['otherUsername'], 0, 2))) ?></div>
                <div class="strat-list-row-main">
                    <div class="strat-list-row-title">
                        <a href="<?= e(route('/messages/' . $conversation['id'])) ?>"><?= e($conversation['otherUsername']) ?></a>
                        <?php if ((int) $conversation['unreadCount'] > 0): ?>
                            <span class="strat-pill" data-tone="accent"><?= (int) $conversation['unreadCount'] ?> new</span>
                        <?php endif; ?>
                    </div>
                    <div class="strat-list-row-meta"><?= e($conversation['last_message_at'] ?? $conversation['created_at']) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<h2>New message</h2>
<form method="post" action="<?= e(route('/messages/start')) ?>" style="max-width:28rem;">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <p>
        <label class="strat-muted" style="display:block;">To (username)
            <input type="text" name="username" required style="width:100%;">
        </label>
    </p>
    <p>
        <label class="strat-muted" style="display:block;">Message
            <textarea name="body" rows="3" required style="width:100%;"></textarea>
        </label>
    </p>
    <button type="submit">Send</button>
</form>
