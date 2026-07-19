<?php
/**
 * @var array<int, array{user_id: int, username: string, last_seen_at: string}> $members
 * @var int $guestCount
 */
?>
<h1>Who's Online</h1>
<p style="color:#888;">Active in the last 5 minutes.</p>

<p><?= count($members) ?> member<?= count($members) === 1 ? '' : 's' ?>, <?= $guestCount ?> guest<?= $guestCount === 1 ? '' : 's' ?> online.</p>

<?php if ($members === []): ?>
    <p style="color:#888;">No members currently online.</p>
<?php else: ?>
    <ul>
        <?php foreach ($members as $member): ?>
            <li><?= e($member['username']) ?> <small style="color:#888;">(last seen <?= e($member['last_seen_at']) ?>)</small></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
