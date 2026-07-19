<?php
/**
 * @var array<int, array<string, mixed>> $friends
 * @var array<int, array<string, mixed>> $incoming
 * @var array<int, array<string, mixed>> $outgoing
 * @var string $csrfToken
 */
?>
<h1>Friends</h1>

<?php if ($incoming !== []): ?>
    <h2>Requests</h2>
    <ul>
        <?php foreach ($incoming as $sender): ?>
            <li style="margin-bottom:0.4rem;">
                <a href="<?= e(route('/members/' . $sender['username'])) ?>"><?= e($sender['username']) ?></a>
                wants to be friends —
                <form method="post" action="<?= e(route('/members/' . $sender['username'] . '/friend/accept')) ?>" style="display:inline;">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="redirect_to" value="<?= e(route('/friends')) ?>">
                    <button type="submit">Accept</button>
                </form>
                <form method="post" action="<?= e(route('/members/' . $sender['username'] . '/friend/decline')) ?>" style="display:inline;">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="redirect_to" value="<?= e(route('/friends')) ?>">
                    <button type="submit">Decline</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if ($outgoing !== []): ?>
    <h2>Sent requests</h2>
    <ul>
        <?php foreach ($outgoing as $recipient): ?>
            <li><a href="<?= e(route('/members/' . $recipient['username'])) ?>"><?= e($recipient['username']) ?></a> — pending</li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<h2>Friends (<?= count($friends) ?>)</h2>
<?php if ($friends === []): ?>
    <p style="color:#888;">No friends yet.</p>
<?php else: ?>
    <ul>
        <?php foreach ($friends as $friend): ?>
            <li><a href="<?= e(route('/members/' . $friend['username'])) ?>"><?= e($friend['username']) ?></a></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
