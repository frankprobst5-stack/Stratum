<?php
/**
 * @var array<string, mixed> $org
 * @var array<int, array<string, mixed>> $events
 * @var bool $canManage
 * @var string $csrfToken
 */
$base = '/organizations/' . $org['slug'];
$grouped = [];
foreach ($events as $event) {
    $day = substr($event['starts_at'], 0, 10);
    $grouped[$day][] = $event;
}
?>
<p><a href="<?= e(route($base)) ?>">&larr; <?= e($org['name']) ?></a></p>

<h1><?= e($org['name']) ?> — Calendar</h1>
<p style="color:#888;">Private to <?= e($org['name']) ?>'s members.</p>

<?php if ($events === []): ?>
    <p style="color:#888;">No upcoming events.</p>
<?php endif; ?>

<?php foreach ($grouped as $day => $dayEvents): ?>
    <h2><?= e($day) ?></h2>
    <ul>
        <?php foreach ($dayEvents as $event): ?>
            <li>
                <?= e(substr($event['starts_at'], 11, 5)) ?> —
                <a href="<?= e(route($base . '/calendar/events/' . $event['id'])) ?>"><?= e($event['title']) ?></a>
                <?php if (!empty($event['location'])): ?>
                    <small style="color:#888;">(<?= e($event['location']) ?>)</small>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endforeach; ?>

<h2>New event</h2>
<form method="post" action="<?= e(route($base . '/calendar/events')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <p>
        <label for="title">Title</label><br>
        <input type="text" id="title" name="title" required style="width:100%;max-width:400px;">
    </p>
    <p>
        <label for="description">Description</label><br>
        <textarea id="description" name="description" rows="3" cols="50"></textarea>
    </p>
    <p>
        <label for="location">Location</label><br>
        <input type="text" id="location" name="location">
    </p>
    <p>
        <label for="starts_at">Starts</label><br>
        <input type="datetime-local" id="starts_at" name="starts_at" required>
    </p>
    <p>
        <label for="ends_at">Ends (optional)</label><br>
        <input type="datetime-local" id="ends_at" name="ends_at">
    </p>
    <button type="submit">Create event</button>
</form>
