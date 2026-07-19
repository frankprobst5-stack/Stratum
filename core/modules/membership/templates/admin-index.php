<?php
/**
 * @var array<int, array<string, mixed>> $fields
 * @var array<int, array<string, mixed>> $pending
 * @var array<int, array<string, mixed>> $reviewed
 * @var string $csrfToken
 */
?>
<h1>Membership</h1>

<h2>Pending applications</h2>
<table border="1" cellpadding="6" cellspacing="0" style="width:100%;border-collapse:collapse;">
    <thead>
        <tr>
            <th>Username</th>
            <th>Email</th>
            <th>Answers</th>
            <th>Submitted</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($pending as $app): ?>
            <tr>
                <td><?= e($app['username']) ?></td>
                <td><?= e($app['email']) ?></td>
                <td>
                    <?php foreach ($app['decoratedAnswers'] as $answer): ?>
                        <div><strong><?= e($answer['label']) ?>:</strong> <?= e((string) $answer['value']) ?></div>
                    <?php endforeach; ?>
                </td>
                <td><?= e($app['created_at']) ?></td>
                <td>
                    <form method="post" action="<?= e(route('/admin/membership/applications/' . $app['id'] . '/approve')) ?>" style="display:inline;">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit">Approve</button>
                    </form>
                    <form method="post" action="<?= e(route('/admin/membership/applications/' . $app['id'] . '/reject')) ?>" style="display:inline;">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit">Reject</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($pending === []): ?>
            <tr><td colspan="5" style="color:#888;">No pending applications.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<h2>Reviewed history</h2>
<table border="1" cellpadding="6" cellspacing="0" style="width:100%;border-collapse:collapse;">
    <thead>
        <tr>
            <th>Username</th>
            <th>Email</th>
            <th>Status</th>
            <th>Reviewed at</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($reviewed as $app): ?>
            <tr>
                <td><?= e($app['username']) ?></td>
                <td><?= e($app['email']) ?></td>
                <td><?= e($app['status']) ?></td>
                <td><?= e((string) ($app['reviewed_at'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($reviewed === []): ?>
            <tr><td colspan="4" style="color:#888;">No reviewed applications yet.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<h2>Custom sign-up fields</h2>
<table border="1" cellpadding="6" cellspacing="0" style="width:100%;border-collapse:collapse;">
    <thead>
        <tr>
            <th>Label</th>
            <th>Type</th>
            <th>Required</th>
            <th>Weight</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($fields as $field): ?>
            <tr>
                <td><?= e($field['label']) ?></td>
                <td><?= e($field['field_type']) ?></td>
                <td><?= $field['is_required'] ? 'Yes' : 'No' ?></td>
                <td><?= (int) $field['weight'] ?></td>
                <td>
                    <form method="post" action="<?= e(route('/admin/membership/fields/' . $field['id'] . '/delete')) ?>" style="display:inline;">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($fields === []): ?>
            <tr><td colspan="5" style="color:#888;">No custom fields yet — sign-up only asks for username/email/password.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<h3>Add field</h3>
<form method="post" action="<?= e(route('/admin/membership/fields')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <p>
        <label for="label">Label</label><br>
        <input type="text" id="label" name="label" required style="width:100%;max-width:30rem;">
    </p>
    <p>
        <label for="field_type">Type</label><br>
        <select id="field_type" name="field_type">
            <option value="text">Text</option>
            <option value="textarea">Textarea</option>
            <option value="checkbox">Checkbox</option>
            <option value="select">Select</option>
        </select>
    </p>
    <p>
        <label for="options">Options (one per line — only used for "Select")</label><br>
        <textarea id="options" name="options" rows="3" cols="50"></textarea>
    </p>
    <p>
        <label for="is_required"><input type="checkbox" id="is_required" name="is_required" value="1"> Required</label>
    </p>
    <p>
        <label for="weight">Weight (lower shows first)</label><br>
        <input type="number" id="weight" name="weight" value="0">
    </p>
    <button type="submit">Add field</button>
</form>
