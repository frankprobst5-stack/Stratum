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
<h1 style="color:var(--sc-text-primary);font-size:1.5rem;margin-bottom:0.25rem;">Dashboard</h1>
<p style="color:var(--sc-text-muted);margin-bottom:1.5rem;">Welcome back, <?= e($currentUser['username'] ?? 'admin') ?>. Here's what's real on your site today.</p>

<div class="sc-dashboard-grid">

    <div class="sc-col-4">
        <div class="sc-card">
            <div class="sc-card-header">
                <span class="sc-card-title">Recent Activity</span>
                <a href="<?= e(route('/activity')) ?>" style="font-size:0.8rem;">View all</a>
            </div>
            <?php if ($recentActivity === []): ?>
                <p style="color:var(--sc-text-muted);font-size:0.9rem;">Nothing recent, or the Activity module is disabled.</p>
            <?php else: ?>
                <?php foreach ($recentActivity as $item): ?>
                    <div class="sc-widget-row">
                        <?php if ($item['content_type'] === 'member'): ?>
                            <div class="sc-widget-row-title"><?= e($item['title']) ?> <?= e($item['verb']) ?></div>
                        <?php else: ?>
                            <div class="sc-widget-row-title"><?= e($item['actor'] ?? 'Unknown') ?> <?= e($item['verb']) ?>: <?= e($item['title']) ?></div>
                        <?php endif; ?>
                        <div class="sc-widget-meta"><?= e($item['created_at']) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="sc-col-4">
        <div class="sc-card">
            <div class="sc-card-header"><span class="sc-card-title">Quick Actions</span></div>
            <a class="sc-quick-link-item" style="margin-bottom:0.5rem;display:block;" href="<?= e(route('/admin/articles/create')) ?>">+ New article</a>
            <a class="sc-quick-link-item" style="margin-bottom:0.5rem;display:block;" href="<?= e(route('/admin/users/create')) ?>">+ Add user</a>
            <a class="sc-quick-link-item" style="margin-bottom:0.5rem;display:block;" href="<?= e(route('/admin/modules')) ?>">Manage modules</a>
            <a class="sc-quick-link-item" style="display:block;" href="<?= e(route('/admin/settings')) ?>">Site settings</a>
        </div>
    </div>

    <div class="sc-col-4">
        <div class="sc-card">
            <div class="sc-card-header"><span class="sc-card-title">System Status</span></div>
            <div class="sc-widget-row" style="display:flex;justify-content:space-between;">
                <span>PHP version</span><strong><?= e($phpVersion) ?></strong>
            </div>
            <div class="sc-widget-row" style="display:flex;justify-content:space-between;">
                <span>MySQL version</span><strong><?= e($mysqlVersion) ?></strong>
            </div>
            <div class="sc-widget-row" style="display:flex;justify-content:space-between;">
                <span>Modules</span><strong><?= $enabledModuleCount ?> / <?= $moduleCount ?> enabled</strong>
            </div>
        </div>
    </div>

    <?php if ($openReports !== null || $trashCount !== null): ?>
        <div class="sc-col-6">
            <div class="sc-card">
                <div class="sc-card-header"><span class="sc-card-title">Needs Attention</span></div>
                <?php if ($openReports !== null): ?>
                    <div class="sc-widget-row" style="display:flex;justify-content:space-between;">
                        <a href="<?= e(route('/admin/moderation')) ?>">Open reports</a><strong><?= $openReports ?></strong>
                    </div>
                <?php endif; ?>
                <?php if ($trashCount !== null): ?>
                    <div class="sc-widget-row" style="display:flex;justify-content:space-between;">
                        <a href="<?= e(route('/admin/trash')) ?>">Items in trash</a><strong><?= $trashCount ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($onlineMembers !== null): ?>
        <div class="sc-col-6">
            <div class="sc-card">
                <div class="sc-card-header">
                    <span class="sc-card-title">Who's Online</span>
                    <a href="<?= e(route('/online')) ?>" style="font-size:0.8rem;">View all</a>
                </div>
                <p style="color:var(--sc-text-muted);font-size:0.85rem;margin-bottom:0.5rem;">
                    <?= count($onlineMembers) ?> member<?= count($onlineMembers) === 1 ? '' : 's' ?>, <?= $guestCount ?> guest<?= $guestCount === 1 ? '' : 's' ?> right now.
                </p>
                <?php foreach (array_slice($onlineMembers, 0, 6) as $member): ?>
                    <div class="sc-widget-row"><?= e($member['username']) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="sc-col-12">
        <div class="sc-card">
            <div class="sc-card-header"><span class="sc-card-title">Staff Notes</span></div>
            <p style="color:var(--sc-text-muted);font-size:0.85rem;margin-bottom:0.75rem;">Reminders, handoff notes, ongoing issues — shared between admins/moderators, not attached to any member or content.</p>
            <?php if ($adminNotes === []): ?>
                <p style="color:var(--sc-text-muted);font-size:0.9rem;">No notes yet.</p>
            <?php else: ?>
                <?php foreach ($adminNotes as $note): ?>
                    <div class="sc-widget-row">
                        <div style="white-space:pre-wrap;"><?= e($note['body']) ?></div>
                        <div class="sc-widget-meta">
                            <?= e($note['authorName']) ?> &middot; <?= e($note['created_at']) ?>
                            &middot;
                            <form method="post" action="<?= e(route('/admin/notes/' . $note['id'] . '/delete')) ?>" style="display:inline;">
                                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                <button type="submit" style="border:none;background:none;color:var(--sc-text-muted);text-decoration:underline;cursor:pointer;padding:0;font-size:0.85rem;">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <form method="post" action="<?= e(route('/admin/notes')) ?>" style="margin-top:0.75rem;">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <div class="sc-form-group">
                    <textarea class="sc-input" name="body" rows="2" required placeholder="Leave a note for the team..."></textarea>
                </div>
                <button type="submit" class="sc-btn sc-btn-primary">Add note</button>
            </form>
        </div>
    </div>

</div>
