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
    <p class="strat-muted">No upcoming events.</p>
<?php endif; ?>

<?php foreach ($grouped as $day => $dayEvents): ?>
    <h2><?= e($day) ?></h2>
    <div class="strat-list">
        <?php foreach ($dayEvents as $event): ?>
            <div class="strat-list-row">
                <div class="strat-list-row-icon" aria-hidden="true">📅</div>
                <div class="strat-list-row-main">
                    <div class="strat-list-row-title">
                        <a href="<?= e(route('/calendar/events/' . $event['id'])) ?>"><?= e($event['title']) ?></a>
                    </div>
                    <div class="strat-list-row-meta">
                        <?= e(substr($event['starts_at'], 11, 5)) ?> &middot;
                        <a href="<?= e(route('/calendar/' . $event['calendar_slug'])) ?>"><?= e($event['calendar_name']) ?></a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
