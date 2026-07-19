<?php
/**
 * @var array<string, mixed> $form
 * @var array<int, array{id: int, label: string, type: string, options: ?string, required: bool}> $fields
 * @var string $csrfToken
 */
?>
<p><a href="<?= e(route('/admin/forms')) ?>">&larr; Surveys & Forms</a></p>
<h1><?= e($form['title']) ?></h1>
<p style="color:#666;">
    Status: <strong><?= e($form['status']) ?></strong>
    &middot;
    <a href="<?= e(route('/admin/forms/' . $form['id'] . '/results')) ?>">View submissions</a>
</p>

<p>
    <?php if ($form['status'] !== 'published'): ?>
        <form method="post" action="<?= e(route('/admin/forms/' . $form['id'] . '/publish')) ?>" style="display:inline;">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <button type="submit">Publish</button>
        </form>
    <?php endif; ?>
    <?php if ($form['status'] === 'published'): ?>
        <form method="post" action="<?= e(route('/admin/forms/' . $form['id'] . '/close')) ?>" style="display:inline;">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <button type="submit">Close</button>
        </form>
    <?php endif; ?>
</p>

<h2>Fields</h2>
<?php if ($fields === []): ?>
    <p style="color:#888;">No fields yet — add one below.</p>
<?php else: ?>
    <ol>
        <?php foreach ($fields as $field): ?>
            <li style="margin-bottom:0.4rem;">
                <?= e($field['label']) ?>
                <small style="color:#888;">(<?= e($field['type']) ?><?= $field['required'] ? ', required' : '' ?>)</small>
                <?php if (in_array($field['type'], ['select', 'checkbox'], true) && $field['options'] !== null): ?>
                    <br><small style="color:#888;">Options: <?= e(str_replace("\n", ', ', trim($field['options']))) ?></small>
                <?php endif; ?>
                <form method="post" action="<?= e(route('/admin/forms/' . $form['id'] . '/fields/' . $field['id'] . '/delete')) ?>" style="display:inline;">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <button type="submit" style="border:none; background:none; color:#888; text-decoration:underline; cursor:pointer; padding:0; font-size:0.85rem;">Remove</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>

<h3>Add a field</h3>
<form method="post" action="<?= e(route('/admin/forms/' . $form['id'] . '/fields')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

    <p>
        <label for="label">Label</label><br>
        <input type="text" id="label" name="label" required>
    </p>

    <p>
        <label for="type">Type</label><br>
        <select id="type" name="type">
            <option value="text">Text (single line)</option>
            <option value="textarea">Text (paragraph)</option>
            <option value="select">Choice (pick one)</option>
            <option value="checkbox">Choice (pick any number)</option>
        </select>
    </p>

    <p>
        <label for="options">Options (one per line — only used for "Choice" types)</label><br>
        <textarea id="options" name="options" rows="4" cols="40"></textarea>
    </p>

    <p>
        <label><input type="checkbox" name="required" value="1"> Required</label>
    </p>

    <button type="submit">Add field</button>
</form>
