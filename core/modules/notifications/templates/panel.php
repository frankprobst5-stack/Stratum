<?php
/**
 * Compact notification list — used both for the dropdown's initial
 * server-rendered render (embedded directly in NotificationBellBlock's
 * output) and for the AJAX re-fetch when the dropdown is opened, same
 * "one template, not duplicated in JS" reasoning chat/templates/message.php
 * already established.
 *
 * @var array<int, array<string, mixed>> $notifications already limited to a small recent slice by the caller
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
    'commerce.purchase_confirmed' => 'Purchase',
    'message.received' => 'Message',
];
?>
<?php if ($notifications === []): ?>
    <div style="padding:0.6rem; font-size:0.85rem;" class="strat-muted">Nothing here yet.</div>
<?php else: ?>
    <?php foreach ($notifications as $notification): ?>
        <?php $isUnread = $notification['read_at'] === null; ?>
        <div style="padding:0.5rem 0.6rem; border-bottom:1px solid var(--strat-card-border);<?= $isUnread ? '' : ' opacity:0.65;' ?>">
            <div class="strat-muted" style="font-size:0.72rem; text-transform:uppercase; letter-spacing:0.03em;">
                <?= e($typeLabels[$notification['type']] ?? 'Notification') ?> &middot; <?= e($notification['created_at']) ?>
            </div>
            <div style="font-size:0.85rem;">
                <?php if (!empty($notification['url'])): ?>
                    <a href="<?= e($notification['url']) ?>" style="color:inherit;"><?= e($notification['message']) ?></a>
                <?php else: ?>
                    <?= e($notification['message']) ?>
                <?php endif; ?>
            </div>
            <?php if ($isUnread): ?>
                <form method="post" action="<?= e(route('/notifications/' . $notification['id'] . '/read')) ?>" style="margin-top:0.2rem;">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button type="submit" style="border:none; background:none; color:var(--strat-accent); cursor:pointer; padding:0; font-size:0.75rem;">Mark read</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
<a href="<?= e(route('/notifications')) ?>" style="display:block; text-align:center; padding:0.5rem; font-size:0.85rem;">View all</a>
