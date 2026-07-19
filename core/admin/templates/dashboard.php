<?php
/**
 * @var array<string, mixed>|null $currentUser
 * @var int $moduleCount
 * @var int $enabledModuleCount
 * @var array<int, array{label: string, verb: string, title: string, url: ?string, actor: ?string, created_at: string}> $recentActivity
 * @var int|null $openReports
 * @var int|null $trashCount
 * @var array<int, array{user_id: int, username: string, last_seen_at: string}>|null $onlineMembers
 * @var int $guestCount
 * @var array<int, array{id: int, body: string, author_id: ?int, created_at: string, authorName: string}> $adminNotes
 * @var string $phpVersion
 * @var string $mysqlVersion
 * @var string $csrfToken
 */
?>
<h1>Dashboard</h1>
<p style="color:#6b7280;">Welcome back, <?= e($currentUser['username'] ?? 'admin') ?>.</p>

<div class="admin-panel-grid">

    <div class="admin-panel">
        <h2>Recent Activity <a href="<?= e(route('/activity')) ?>">View all</a></h2>
        <?php if ($recentActivity === []): ?>
            <p class="muted">Nothing recent, or the Activity module is disabled.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($recentActivity as $item): ?>
                    <li>
                        <?php if ($item['content_type'] === 'member'): ?>
                            <strong><?= e($item['title']) ?></strong> <?= e($item['verb']) ?>
                        <?php else: ?>
                            <strong><?= e($item['actor'] ?? 'Unknown') ?></strong> <?= e($item['verb']) ?>:
                            <?= e($item['title']) ?>
                        <?php endif; ?>
                        <br><small class="muted"><?= e($item['created_at']) ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="admin-panel">
        <h2>Quick Actions</h2>
        <ul>
            <li><a href="<?= e(route('/admin/articles/create')) ?>">+ New article</a></li>
            <li><a href="<?= e(route('/admin/users/create')) ?>">+ Add user</a></li>
            <li><a href="<?= e(route('/admin/modules')) ?>">Manage modules</a></li>
            <li><a href="<?= e(route('/admin/settings')) ?>">Site settings</a></li>
        </ul>
    </div>

    <div class="admin-panel">
        <h2>System Status</h2>
        <div class="admin-stat"><span>PHP version</span><span class="value"><?= e($phpVersion) ?></span></div>
        <div class="admin-stat"><span>MySQL version</span><span class="value"><?= e($mysqlVersion) ?></span></div>
        <div class="admin-stat"><span>Modules</span><span class="value"><?= $enabledModuleCount ?> / <?= $moduleCount ?> enabled</span></div>
    </div>

    <?php if ($openReports !== null || $trashCount !== null): ?>
        <div class="admin-panel">
            <h2>Needs Attention</h2>
            <?php if ($openReports !== null): ?>
                <div class="admin-stat">
                    <a href="<?= e(route('/admin/moderation')) ?>">Open reports</a>
                    <span class="value"><?= $openReports ?></span>
                </div>
            <?php endif; ?>
            <?php if ($trashCount !== null): ?>
                <div class="admin-stat">
                    <a href="<?= e(route('/admin/trash')) ?>">Items in trash</a>
                    <span class="value"><?= $trashCount ?></span>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($onlineMembers !== null): ?>
        <div class="admin-panel">
            <h2>Who's Online <a href="<?= e(route('/online')) ?>">View all</a></h2>
            <p class="muted"><?= count($onlineMembers) ?> member<?= count($onlineMembers) === 1 ? '' : 's' ?>, <?= $guestCount ?> guest<?= $guestCount === 1 ? '' : 's' ?> right now.</p>
            <?php if ($onlineMembers !== []): ?>
                <ul>
                    <?php foreach (array_slice($onlineMembers, 0, 6) as $member): ?>
                        <li><?= e($member['username']) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="admin-panel">
        <h2>Staff Notes</h2>
        <p class="muted">Reminders, handoff notes, ongoing issues — shared between admins/moderators, not attached to any member or content.</p>
        <?php if ($adminNotes === []): ?>
            <p class="muted">No notes yet.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($adminNotes as $note): ?>
                    <li style="margin-bottom:0.5rem;">
                        <div style="white-space:pre-wrap;"><?= e($note['body']) ?></div>
                        <small class="muted">
                            <?= e($note['authorName']) ?> &middot; <?= e($note['created_at']) ?>
                            &middot;
                            <form method="post" action="<?= e(route('/admin/notes/' . $note['id'] . '/delete')) ?>" style="display:inline;">
                                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                <button type="submit" style="border:none; background:none; color:#888; text-decoration:underline; cursor:pointer; padding:0; font-size:0.85rem;">Delete</button>
                            </form>
                        </small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <form method="post" action="<?= e(route('/admin/notes')) ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <textarea name="body" rows="2" cols="40" required placeholder="Leave a note for the team..." style="width:100%; box-sizing:border-box;"></textarea>
            <button type="submit">Add note</button>
        </form>
    </div>

</div>
