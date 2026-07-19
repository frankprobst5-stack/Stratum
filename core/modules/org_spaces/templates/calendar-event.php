<?php
/**
 * @var array<string, mixed> $org
 * @var array<string, mixed> $event
 * @var string $authorName
 * @var bool $canManage
 * @var string $csrfToken
 */
$base = '/organizations/' . $org['slug'];
?>
<p><a href="<?= e(route($base . '/calendar')) ?>">&larr; <?= e($org['name']) ?> Calendar</a></p>

<h1><?= e($event['title']) ?></h1>
<p style="color:#666; font-size:0.9rem;">
    <?= e($event['starts_at']) ?><?php if (!empty($event['ends_at'])): ?> &ndash; <?= e($event['ends_at']) ?><?php endif; ?>
    &middot; posted by <?= e($authorName) ?>
    <?php if (!empty($event['location'])): ?> &middot; <?= e($event['location']) ?><?php endif; ?>
</p>

<?php if (!empty($event['description'])): ?>
    <div style="white-space:pre-wrap;"><?= e($event['description']) ?></div>
<?php endif; ?>

<?php if ($canManage): ?>
    <form method="post" action="<?= e(route($base . '/calendar/events/' . $event['id'] . '/delete')) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <button type="submit">Delete event</button>
    </form>
<?php endif; ?>
