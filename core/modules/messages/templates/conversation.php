<?php
/**
 * @var array<string, mixed> $conversation
 * @var string $otherUsername
 * @var array<int, array<string, mixed>> $messages chronological, each with 'senderName'
 * @var int $currentUserId
 * @var string $csrfToken
 */
$initials = static fn (string $name): string => strtoupper(substr($name, 0, 2));
?>
<p><a href="<?= e(route('/messages')) ?>">&larr; Messages</a></p>
<h1><?= e($otherUsername) ?></h1>

<?php foreach ($messages as $message): ?>
    <div class="strat-post">
        <div class="strat-post-author">
            <div class="strat-avatar"><?= e($initials((string) $message['senderName'])) ?></div>
            <div class="strat-post-author-name"><?= e($message['senderName']) ?></div>
        </div>
        <div class="strat-post-body">
            <div class="strat-post-meta"><?= e($message['created_at']) ?></div>
            <div class="strat-post-content"><?= e($message['body']) ?></div>
        </div>
    </div>
<?php endforeach; ?>

<h2>Reply</h2>
<form method="post" action="<?= e(route('/messages/' . $conversation['id'] . '/reply')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <p>
        <textarea name="body" rows="3" cols="60" required></textarea>
    </p>
    <button type="submit">Send</button>
</form>
