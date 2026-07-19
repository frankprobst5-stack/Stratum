<?php
/**
 * @var array<string, mixed> $event with calendar_name/calendar_slug
 * @var array<int, array{user_id: int, status: string, username: string}> $attendees
 * @var array<int, array{user_id: int, checked_in_at: string, username: string}> $attendance
 * @var string|null $myRsvp
 * @var array<int, array<string, mixed>> $comments each with 'authorName'
 * @var bool $canRsvp
 * @var bool $canManage
 * @var bool $canComment
 * @var bool $isLoggedIn
 * @var string $csrfToken
 */
$going = array_filter($attendees, static fn (array $a): bool => $a['status'] === 'going');
$maybe = array_filter($attendees, static fn (array $a): bool => $a['status'] === 'maybe');
?>
<p><a href="<?= e(route('/calendar/' . $event['calendar_slug'])) ?>">&larr; <?= e($event['calendar_name']) ?></a></p>

<h1><?= e($event['title']) ?></h1>
<p style="color:#666;">
    <?= e($event['starts_at']) ?><?php if (!empty($event['ends_at'])): ?> &ndash; <?= e($event['ends_at']) ?><?php endif; ?>
    <?php if (!empty($event['location'])): ?>
        <br>📍 <?= e($event['location']) ?>
        &middot; <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($event['location']) ?>" target="_blank" rel="noopener noreferrer">View on map</a>
    <?php endif; ?>
</p>

<?php if (!empty($event['location'])): ?>
    <iframe
        src="https://www.google.com/maps?q=<?= urlencode($event['location']) ?>&output=embed"
        width="100%" height="300" style="border:0; max-width:600px;" loading="lazy"
        referrerpolicy="no-referrer-when-downgrade"
    ></iframe>
<?php endif; ?>

<?php if (!empty($event['description'])): ?>
    <div style="white-space:pre-wrap;"><?= e($event['description']) ?></div>
<?php endif; ?>

<?php if ($canManage): ?>
    <form method="post" action="<?= e(route('/calendar/events/' . $event['id'] . '/delete')) ?>" style="margin-top:1rem;">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <button type="submit">Delete event</button>
    </form>
<?php endif; ?>

<h2>RSVP</h2>

<?php if ($canRsvp): ?>
    <form method="post" action="<?= e(route('/calendar/events/' . $event['id'] . '/rsvp')) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <?php foreach (['going' => 'Going', 'maybe' => 'Maybe', 'declined' => 'Not going'] as $value => $label): ?>
            <button type="submit" name="status" value="<?= e($value) ?>" <?= $myRsvp === $value ? 'style="font-weight:bold;"' : '' ?>>
                <?= e($label) ?><?= $myRsvp === $value ? ' ✓' : '' ?>
            </button>
        <?php endforeach; ?>
    </form>
<?php elseif ($isLoggedIn): ?>
    <p style="color:#888;">You don't have permission to RSVP.</p>
<?php else: ?>
    <p><a href="<?= e(route('/login')) ?>">Log in</a> to RSVP.</p>
<?php endif; ?>

<p>
    <strong>Going (<?= count($going) ?>):</strong>
    <?= implode(', ', array_map(static fn (array $a): string => e($a['username']), $going)) ?: '—' ?>
</p>
<p>
    <strong>Maybe (<?= count($maybe) ?>):</strong>
    <?= implode(', ', array_map(static fn (array $a): string => e($a['username']), $maybe)) ?: '—' ?>
</p>

<?php if ($canManage): ?>
    <h2>Attendance (<?= count($attendance) ?> checked in)</h2>
    <p style="color:#888; font-size:0.9rem;">Who actually showed up — separate from RSVP, and works for walk-ins who never RSVP'd.</p>

    <?php if ($attendance !== []): ?>
        <ul>
            <?php foreach ($attendance as $row): ?>
                <li>
                    <?= e($row['username']) ?>
                    <small style="color:#888;">(<?= e($row['checked_in_at']) ?>)</small>
                    <form method="post" action="<?= e(route('/calendar/events/' . $event['id'] . '/attendance/' . $row['user_id'] . '/remove')) ?>" style="display:inline;">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit" style="border:none; background:none; color:#888; text-decoration:underline; cursor:pointer; padding:0; font-size:0.85rem;">Remove</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="<?= e(route('/calendar/events/' . $event['id'] . '/attendance')) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <label for="attendance_username">Check in a member by username</label><br>
        <input type="text" id="attendance_username" name="username" required>
        <button type="submit">Check in</button>
    </form>
<?php endif; ?>

<hr>

<h2>Comments (<?= count($comments) ?>)</h2>

<?php foreach ($comments as $comment): ?>
    <div style="margin-bottom:1rem; padding:0.75rem; background:#f4f5f7; border-radius:6px;">
        <strong><?= e($comment['authorName']) ?></strong>
        <span style="color:#888; font-size:0.85rem;"> &middot; <?= e($comment['created_at']) ?></span>
        <p style="white-space:pre-wrap; margin:0.5rem 0 0;"><?= e($comment['body']) ?></p>
    </div>
<?php endforeach; ?>

<?php if ($canComment): ?>
    <form method="post" action="<?= e(route('/comments')) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="commentable_type" value="calendar_event">
        <input type="hidden" name="commentable_id" value="<?= (int) $event['id'] ?>">
        <input type="hidden" name="redirect_to" value="<?= e(route('/calendar/events/' . $event['id'])) ?>">
        <p>
            <label for="body">Add a comment</label><br>
            <textarea id="body" name="body" rows="3" cols="50" required></textarea>
        </p>
        <button type="submit">Post comment</button>
    </form>
<?php elseif ($isLoggedIn): ?>
    <p style="color:#888;">You don't have permission to comment.</p>
<?php else: ?>
    <p><a href="<?= e(route('/login')) ?>">Log in</a> to comment.</p>
<?php endif; ?>
