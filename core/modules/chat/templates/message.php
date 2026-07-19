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
<div class="strat-chat-message" data-message-id="<?= (int) $message['id'] ?>" style="padding:0.25rem 0;">
    <?php if ($isAction): ?>
        <em style="color:#888;">* <?= e($message['username']) ?> <?= e($message['body']) ?></em>
    <?php else: ?>
        <strong><?= e($message['username']) ?>:</strong> <?= e($message['body']) ?>
    <?php endif; ?>
</div>
