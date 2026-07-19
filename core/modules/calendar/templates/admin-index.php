<?php
/**
 * @var array<int, array{id: int, name: string, slug: string, description: ?string}> $calendars
 * @var string $csrfToken
 */
?>
<h1>Calendars</h1>

<ul>
    <?php foreach ($calendars as $cal): ?>
        <li><a href="<?= e(route('/calendar/' . $cal['slug'])) ?>"><?= e($cal['name']) ?></a></li>
    <?php endforeach; ?>
    <?php if ($calendars === []): ?>
        <li style="color:#888;">No calendars yet.</li>
    <?php endif; ?>
</ul>

<form method="post" action="<?= e(route('/admin/calendar/calendars')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <p>
        <label for="name">Name</label><br>
        <input type="text" id="name" name="name" required>
    </p>
    <p>
        <label for="description">Description</label><br>
        <input type="text" id="description" name="description">
    </p>
    <button type="submit">Add calendar</button>
</form>
