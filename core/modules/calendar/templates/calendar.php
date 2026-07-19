<?php
/**
 * @var array<string, mixed> $calendar
 * @var array<int, array<string, mixed>> $events
 */
?>
<p><a href="<?= e(route('/calendar')) ?>">&larr; Calendar</a></p>
<h1><?= e($calendar['name']) ?></h1>
<?php if (!empty($calendar['description'])): ?>
    <p style="color:#666;"><?= e($calendar['description']) ?></p>
<?php endif; ?>

<?php if ($events === []): ?>
    <p>No upcoming events.</p>
<?php endif; ?>

<ul>
    <?php foreach ($events as $event): ?>
        <li>
            <?= e($event['starts_at']) ?> —
            <a href="<?= e(route('/calendar/events/' . $event['id'])) ?>"><?= e($event['title']) ?></a>
        </li>
    <?php endforeach; ?>
</ul>
