<?php
/**
 * @var array<int, array<string, mixed>> $fields
 * @var string $csrfToken
 * @var string|null $error
 * @var array<string, string> $values
 */
?>
<h1>Sign up</h1>

<?php if ($error !== null): ?>
    <p style="color:#b00020;"><?= e($error) ?></p>
<?php endif; ?>

<form method="post" action="<?= e(route('/register')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

    <p>
        <label for="username">Username</label><br>
        <input type="text" id="username" name="username" value="<?= e((string) ($values['username'] ?? '')) ?>" required autofocus>
    </p>

    <p>
        <label for="email">Email</label><br>
        <input type="email" id="email" name="email" value="<?= e((string) ($values['email'] ?? '')) ?>" required>
    </p>

    <p>
        <label for="password">Password</label><br>
        <input type="password" id="password" name="password" required minlength="12">
    </p>

    <p>
        <label for="password_confirm">Confirm password</label><br>
        <input type="password" id="password_confirm" name="password_confirm" required minlength="12">
    </p>

    <?php foreach ($fields as $field): ?>
        <?php $fieldKey = 'field_' . $field['id']; ?>
        <p>
            <?php if ($field['field_type'] === 'checkbox'): ?>
                <label for="<?= e($fieldKey) ?>">
                    <input type="checkbox" id="<?= e($fieldKey) ?>" name="<?= e($fieldKey) ?>" value="1" <?= $field['is_required'] ? 'required' : '' ?>>
                    <?= e($field['label']) ?>
                </label>
            <?php elseif ($field['field_type'] === 'textarea'): ?>
                <label for="<?= e($fieldKey) ?>"><?= e($field['label']) ?></label><br>
                <textarea id="<?= e($fieldKey) ?>" name="<?= e($fieldKey) ?>" rows="4" cols="50" <?= $field['is_required'] ? 'required' : '' ?>></textarea>
            <?php elseif ($field['field_type'] === 'select'): ?>
                <label for="<?= e($fieldKey) ?>"><?= e($field['label']) ?></label><br>
                <select id="<?= e($fieldKey) ?>" name="<?= e($fieldKey) ?>" <?= $field['is_required'] ? 'required' : '' ?>>
                    <option value="">-- Select --</option>
                    <?php foreach ((json_decode((string) ($field['options_json'] ?? '[]'), true) ?: []) as $option): ?>
                        <option value="<?= e($option) ?>"><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <label for="<?= e($fieldKey) ?>"><?= e($field['label']) ?></label><br>
                <input type="text" id="<?= e($fieldKey) ?>" name="<?= e($fieldKey) ?>" <?= $field['is_required'] ? 'required' : '' ?>>
            <?php endif; ?>
        </p>
    <?php endforeach; ?>

    <button type="submit">Submit application</button>
</form>
