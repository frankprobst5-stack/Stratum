<?php
/**
 * One chat message — rendered both for the room's initial page load and
 * as the AJAX fragment returned by postMessage()/pollMessages(), same
 * "one template, not duplicated in JS" reasoning the block-management
 * drag-and-drop endpoints already established.
 *
 * @var array<string, mixed> $message {id, username, body, is_action}
 */
$isAction = (int) ($message['is_action'] ?? 0) === 1;
?>
<div class="strat-chat-message<?= $isAction ? ' is-action' : '' ?>" data-message-id="<?= (int) $message['id'] ?>">
    <?php if ($isAction): ?>
        <span class="strat-chat-message-author">* <?= e($message['username']) ?></span>
        <span class="strat-chat-message-body"><?= e($message['body']) ?></span>
    <?php else: ?>
        <span class="strat-chat-message-author"><?= e($message['username']) ?>:</span>
        <span class="strat-chat-message-body"><?= e($message['body']) ?></span>
    <?php endif; ?>
</div>
