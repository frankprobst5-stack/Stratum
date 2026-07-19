<?php
/**
 * @var array<string, mixed> $form
 * @var array<int, array{id: int, label: string, type: string, options: ?string, required: bool}> $fields
 * @var bool $alreadySubmitted
 * @var string $csrfToken
 */
?>
<p><a href="<?= e(route('/forms')) ?>">&larr; Forms</a></p>
<h1><?= e($form['title']) ?></h1>
<?php if (!empty($form['description'])): ?>
    <p style="color:#666;"><?= nl2br(e($form['description'])) ?></p>
<?php endif; ?>

<?php if ($alreadySubmitted): ?>
    <p style="color:#2e7d32;">You've already submitted this form. Thanks!</p>
<?php else: ?>
    <form method="post" action="<?= e(route('/forms/' . $form['slug'])) ?>">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

        <?php foreach ($fields as $field): ?>
            <p>
                <label for="field_<?= (int) $field['id'] ?>">
                    <?= e($field['label']) ?><?= $field['required'] ? ' *' : '' ?>
                </label><br>

                <?php if ($field['type'] === 'textarea'): ?>
                    <textarea id="field_<?= (int) $field['id'] ?>" name="field_<?= (int) $field['id'] ?>" rows="4" cols="50" <?= $field['required'] ? 'required' : '' ?>></textarea>
                <?php elseif ($field['type'] === 'select'): ?>
                    <select id="field_<?= (int) $field['id'] ?>" name="field_<?= (int) $field['id'] ?>" <?= $field['required'] ? 'required' : '' ?>>
                        <option value="">— Select —</option>
                        <?php foreach (preg_split('/\r?\n/', trim((string) $field['options'])) as $option): ?>
                            <?php if (trim($option) !== ''): ?>
                                <option value="<?= e(trim($option)) ?>"><?= e(trim($option)) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($field['type'] === 'checkbox'): ?>
                    <?php foreach (preg_split('/\r?\n/', trim((string) $field['options'])) as $option): ?>
                        <?php if (trim($option) !== ''): ?>
                            <label style="display:block;">
                                <input type="checkbox" name="field_<?= (int) $field['id'] ?>[]" value="<?= e(trim($option)) ?>">
                                <?= e(trim($option)) ?>
                            </label>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <input type="text" id="field_<?= (int) $field['id'] ?>" name="field_<?= (int) $field['id'] ?>" <?= $field['required'] ? 'required' : '' ?>>
                <?php endif; ?>
            </p>
        <?php endforeach; ?>

        <button type="submit">Submit</button>
    </form>
<?php endif; ?>
