<?php
/**
 * @var array<int, array{id: int, name: string, slug: string, description: ?string}> $calendars
 * @var string $csrfToken
 */
?>
<h1>New Event</h1>

<form method="post" action="<?= e(route('/calendar/create')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

    <p>
        <label for="calendar_id">Calendar</label><br>
        <select id="calendar_id" name="calendar_id" required>
            <?php foreach ($calendars as $cal): ?>
                <option value="<?= (int) $cal['id'] ?>"><?= e($cal['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </p>

    <p>
        <label for="title">Title</label><br>
        <input type="text" id="title" name="title" required style="width:100%; max-width:500px;">
    </p>

    <p>
        <label for="starts_at">Starts</label><br>
        <input type="datetime-local" id="starts_at" name="starts_at" required>
    </p>

    <p>
        <label for="ends_at">Ends (optional)</label><br>
        <input type="datetime-local" id="ends_at" name="ends_at">
    </p>

    <p>
        <label><input type="checkbox" name="is_all_day" value="1"> All day</label>
    </p>

    <p>
        <label for="location">Location</label><br>
        <input type="text" id="location" name="location" style="width:100%; max-width:500px;">
    </p>

    <p>
        <label for="description">Description</label><br>
        <textarea id="description" name="description" rows="6" cols="60"></textarea>
    </p>

    <p>
        <label for="recurrence_type">Repeats</label><br>
        <select id="recurrence_type" name="recurrence_type">
            <option value="none">Does not repeat</option>
            <option value="daily">Daily</option>
            <option value="weekly">Weekly</option>
            <option value="monthly">Monthly</option>
        </select>
    </p>

    <p>
        <label for="occurrence_count">Number of occurrences (if repeating, max 26)</label><br>
        <input type="number" id="occurrence_count" name="occurrence_count" value="1" min="1" max="26">
    </p>

    <button type="submit">Create event</button>
</form>
