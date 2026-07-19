<?php
/**
 * @var array<string, mixed> $member
 * @var ?string $rankName
 * @var array<int, array<string, mixed>> $badges each with 'name', 'icon_url', 'awarded_at'
 * @var int $friendCount
 * @var int $followerCount
 * @var int $followingCount
 * @var bool $isLoggedIn
 * @var 'self'|'friends'|'request_sent'|'request_received'|'none' $relationship
 * @var bool $isFollowing
 * @var string $csrfToken
 */
?>
<?php if (!empty($member['banner_url'])): ?>
    <div style="width:100%; max-height:180px; overflow:hidden; border-radius:8px; margin-bottom:0.75rem;">
        <img src="<?= e($member['banner_url']) ?>" alt="" style="width:100%; display:block;">
    </div>
<?php endif; ?>

<div style="display:flex; align-items:center; gap:1rem;">
    <?php if (!empty($member['avatar_url'])): ?>
        <img src="<?= e($member['avatar_url']) ?>" alt="" style="width:64px; height:64px; border-radius:50%; object-fit:cover;">
    <?php endif; ?>
    <div>
        <h1 style="margin:0;"><?= e($member['username']) ?></h1>
        <?php if ($rankName !== null): ?>
            <p style="margin:0.15rem 0 0; color:#666;">
                <?= e($rankName) ?> &middot; <?= (int) ($member['points'] ?? 0) ?> points
            </p>
        <?php endif; ?>
    </div>
</div>

<?php if ($badges !== []): ?>
    <div style="margin:0.5rem 0; display:flex; gap:0.75rem; flex-wrap:wrap;">
        <?php foreach ($badges as $badge): ?>
            <span title="<?= e($badge['name']) ?><?= !empty($badge['description']) ? ' — ' . e($badge['description']) : '' ?>" style="display:inline-flex; align-items:center; gap:0.3rem; background:#f4f5f7; border-radius:12px; padding:0.2rem 0.6rem; font-size:0.85rem;">
                <?php if (!empty($badge['icon_url'])): ?>
                    <img src="<?= e($badge['icon_url']) ?>" alt="" style="width:16px; height:16px;">
                <?php endif; ?>
                <?= e($badge['name']) ?>
            </span>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<p style="color:#666; font-size:0.9rem;">
    <?= (int) $friendCount ?> friend<?= $friendCount === 1 ? '' : 's' ?>
    &middot; <?= (int) $followerCount ?> follower<?= $followerCount === 1 ? '' : 's' ?>
    &middot; following <?= (int) $followingCount ?>
    &middot; joined <?= e($member['created_at']) ?>
</p>

<?php if (!empty($member['about_me'])): ?>
    <div style="white-space:pre-wrap; margin:0.75rem 0;"><?= e($member['about_me']) ?></div>
<?php endif; ?>

<?php if ($relationship !== 'self'): ?>
    <div style="margin:0.75rem 0; display:flex; gap:0.5rem;">
        <?php if ($relationship === 'friends'): ?>
            <form method="post" action="<?= e(route('/members/' . $member['username'] . '/friend/remove')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <button type="submit">&#10003; Friends — Remove</button>
            </form>
        <?php elseif ($relationship === 'request_sent'): ?>
            <em style="color:#888;">Friend request sent</em>
        <?php elseif ($relationship === 'request_received'): ?>
            <form method="post" action="<?= e(route('/members/' . $member['username'] . '/friend/accept')) ?>" style="display:inline;">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <button type="submit">Accept friend request</button>
            </form>
            <form method="post" action="<?= e(route('/members/' . $member['username'] . '/friend/decline')) ?>" style="display:inline;">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <button type="submit">Decline</button>
            </form>
        <?php elseif ($isLoggedIn): ?>
            <form method="post" action="<?= e(route('/members/' . $member['username'] . '/friend/request')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <button type="submit">+ Add friend</button>
            </form>
        <?php endif; ?>

        <?php if ($isLoggedIn): ?>
            <form method="post" action="<?= e(route('/members/' . $member['username'] . '/follow')) ?>">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <button type="submit"><?= $isFollowing ? 'Unfollow' : '+ Follow' ?></button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (!$isLoggedIn && $relationship !== 'self'): ?>
    <p><a href="<?= e(route('/login')) ?>">Log in</a> to add as a friend or follow.</p>
<?php endif; ?>
