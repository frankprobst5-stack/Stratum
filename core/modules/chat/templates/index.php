<?php
/**
 * @var array<int, array<string, mixed>> $rooms public rooms only, most-recently-active first
 * @var bool $isLoggedIn
 * @var string $csrfToken
 */
?>
<h1>Chat</h1>

<?php if ($rooms === []): ?>
    <p style="color:#888;">No chat rooms yet<?= $isLoggedIn ? ' — start one below.' : '.' ?></p>
<?php else: ?>
    <table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
        <thead>
            <tr style="text-align:left; border-bottom:1px solid #ddd;">
                <th>Room</th>
                <th>Topic</th>
                <th>Members</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rooms as $room): ?>
            <tr style="border-bottom:1px solid #eee;">
                <td>
                    <?= e($room['name']) ?>
                    <?php if ($room['source'] === 'user'): ?>
                        <small style="color:#999;">(member room)</small>
                    <?php endif; ?>
                </td>
                <td style="color:#666;"><?= e($room['topic'] ?? '') ?></td>
                <td><?= (int) $room['member_count'] ?></td>
                <td>
                    <?php if ($isLoggedIn): ?>
                        <a href="<?= e(route('/chat/rooms/' . $room['id'])) ?>">Enter</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if (!$isLoggedIn): ?>
    <p style="color:#666;"><a href="<?= e(route('/login')) ?>">Log in</a> to join a room or start your own.</p>
<?php else: ?>
    <h2>Start a room</h2>
    <p style="color:#666;">
        Always public — anyone can join. It sticks around while people are in
        it and disappears on its own once everyone's left.
    </p>
    <form method="post" action="<?= e(route('/chat/rooms/create')) ?>" style="max-width:28rem;">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <p>
            <label style="display:block;font-size:0.85rem;color:#666;">Room name
                <input type="text" name="name" required style="width:100%;">
            </label>
        </p>
        <p>
            <label style="display:block;font-size:0.85rem;color:#666;">Topic (optional)
                <input type="text" name="topic" style="width:100%;">
            </label>
        </p>
        <button type="submit">Start room</button>
    </form>
<?php endif; ?>
