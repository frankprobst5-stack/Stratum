<?php
/**
 * @var array<int, array<string, mixed>> $events each with calendar_name/calendar_slug
 */
$grouped = [];
foreach ($events as $event) {
    $day = substr($event['starts_at'], 0, 10);
    $grouped[$day][] = $event;
}
?>
<h1>Calendar</h1>

<p><a href="<?= e(route('/calendar/create')) ?>">+ New event</a></p>

<?php if ($events === []): ?>
    <p>No upcoming events.</p>
<?php endif; ?>

<?php foreach ($grouped as $day => $dayEvents): ?>
    <h2><?= e($day) ?></h2>
    <ul>
        <?php foreach ($dayEvents as $event): ?>
            <li>
                <?= e(substr($event['starts_at'], 11, 5)) ?> —
                <a href="<?= e(route('/calendar/events/' . $event['id'])) ?>"><?= e($event['title']) ?></a>
                <small style="color:#888;">(<a href="<?= e(route('/calendar/' . $event['calendar_slug'])) ?>"><?= e($event['calendar_name']) ?></a>)</small>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endforeach; ?>
