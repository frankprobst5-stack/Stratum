<?php
/**
 * @var array<int, array<string, mixed>> $notifications
 * @var int $unreadCount
 * @var string $csrfToken
 */

$typeLabels = [
    'forum.reply' => 'Forum',
    'comment' => 'Comment',
    'membership.approved' => 'Membership',
    'org.announcement' => 'Organization',
    'forum.moderator' => 'Forum',
    'dues.confirmed' => 'Dues',
    'donation.confirmed' => 'Donation',
    'friend.request' => 'Friend request',
    'friend.accepted' => 'Friend request',
    'rank.promoted' => 'Rank up',
];
?>
<h1>Notifications</h1>

<?php if ($unreadCount > 0): ?>
    <form action="/notifications/read-all" method="post" style="margin-bottom:1rem;">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <button type="submit">Mark all read (<?= (int) $unreadCount ?>)</button>
    </form>
<?php endif; ?>

<?php if ($notifications === []): ?>
    <p style="color:#888;">Nothing here yet — replies, approvals, and announcements will show up here.</p>
<?php else: ?>
    <ul style="list-style:none;padding:0;">
        <?php foreach ($notifications as $notification): ?>
            <?php $isUnread = $notification['read_at'] === null; ?>
            <li style="margin-bottom:1rem;border-bottom:1px solid #333;padding:0.75rem;<?= $isUnread ? 'background:#eef3fd;border-left:4px solid #2f6fed;' : 'opacity:0.75;' ?>">
                <div style="color:#888;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.03em;">
                    <?= e($typeLabels[$notification['type']] ?? 'Notification') ?>
                    &middot; <?= e($notification['created_at']) ?>
                </div>
                <?php if (!empty($notification['url'])): ?>
                    <a href="<?= e($notification['url']) ?>"><?= e($notification['message']) ?></a>
                <?php else: ?>
                    <span><?= e($notification['message']) ?></span>
                <?php endif; ?>
                <?php if ($isUnread): ?>
                    <form action="/notifications/<?= (int) $notification['id'] ?>/read" method="post" style="display:inline;margin-left:0.75rem;">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit" style="font-size:0.8rem;">Mark read</button>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
