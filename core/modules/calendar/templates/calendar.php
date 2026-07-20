<?php
/**
 * @var array<string, mixed> $calendar
 * @var array<int, array<string, mixed>> $events
 */
?>
<p><a href="<?= e(route('/calendar')) ?>">&larr; Calendar</a></p>
<h1><?= e($calendar['name']) ?></h1>
<?php if (!empty($calendar['description'])): ?>
    <p class="strat-muted"><?= e($calendar['description']) ?></p>
<?php endif; ?>

<?php if ($events === []): ?>
    <p class="strat-muted">No upcoming events.</p>
<?php else: ?>
    <div class="strat-list">
        <?php foreach ($events as $event): ?>
            <div class="strat-list-row">
                <div class="strat-list-row-icon" aria-hidden="true">📅</div>
                <div class="strat-list-row-main">
                    <div class="strat-list-row-title">
                        <a href="<?= e(route('/calendar/events/' . $event['id'])) ?>"><?= e($event['title']) ?></a>
                    </div>
                    <div class="strat-list-row-meta"><?= e($event['starts_at']) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
