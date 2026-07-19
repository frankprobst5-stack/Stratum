<?php
/**
 * @var array<int, array<string, mixed>> $messages
 * @var string $csrfToken
 */

$toDatetimeLocal = static function (?string $value): string {
    if ($value === null || $value === '') {
        return '';
    }

    return str_replace(' ', 'T', substr($value, 0, 16));
};
?>
<h1>Ticker Messages</h1>
<p style="color:#888;">Rendered in the site header on every page while enabled and within its active window.</p>

<table border="1" cellpadding="6" cellspacing="0" style="width:100%;border-collapse:collapse;">
    <thead>
        <tr>
            <th>Message</th>
            <th>URL</th>
            <th>Level</th>
            <th>Starts</th>
            <th>Ends</th>
            <th>Weight</th>
            <th>Enabled</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($messages as $m): ?>
            <?php $formId = 'ticker-update-' . $m['id']; ?>
            <tr>
                <td><input form="<?= e($formId) ?>" type="text" name="message" value="<?= e($m['message']) ?>" maxlength="280" required style="width:100%;"></td>
                <td><input form="<?= e($formId) ?>" type="text" name="url" value="<?= e((string) ($m['url'] ?? '')) ?>" style="width:100%;"></td>
                <td>
                    <select form="<?= e($formId) ?>" name="level">
                        <?php foreach (['info', 'warning', 'urgent'] as $level): ?>
                            <option value="<?= e($level) ?>" <?= $m['level'] === $level ? 'selected' : '' ?>><?= e(ucfirst($level)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input form="<?= e($formId) ?>" type="datetime-local" name="starts_at" value="<?= e($toDatetimeLocal($m['starts_at'] ?? null)) ?>"></td>
                <td><input form="<?= e($formId) ?>" type="datetime-local" name="ends_at" value="<?= e($toDatetimeLocal($m['ends_at'] ?? null)) ?>"></td>
                <td><input form="<?= e($formId) ?>" type="number" name="weight" value="<?= (int) $m['weight'] ?>" style="width:4rem;"></td>
                <td>
                    <button form="<?= e($formId) ?>" type="submit">Save</button>
                    <form method="post" action="<?= e(route('/admin/ticker/messages/' . $m['id'] . '/toggle')) ?>" style="display:inline;">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit"><?= $m['is_enabled'] ? 'Disable' : 'Enable' ?></button>
                    </form>
                    <form method="post" action="<?= e(route('/admin/ticker/messages/' . $m['id'] . '/delete')) ?>" style="display:inline;">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit">Delete</button>
                    </form>
                </td>
            </tr>
            <!-- Declared out-of-band so it doesn't have to wrap the <td> cells above (a <form> can't
                 legally wrap table cells) — inputs/button reference it via the form="" attribute. -->
            <form id="<?= e($formId) ?>" method="post" action="<?= e(route('/admin/ticker/messages/' . $m['id'] . '/update')) ?>" style="display:none;">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            </form>
        <?php endforeach; ?>
        <?php if ($messages === []): ?>
            <tr><td colspan="7" style="color:#888;">No ticker messages yet.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<?php foreach ($messages as $m): ?>
    <!-- Declared outside the table (a <form> can't legally wrap table cells, and browsers
         foster-parent/otherwise mangle <form> markup nested inside a table) — the row's
         inputs above reference this by id via their form="" attribute instead. -->
    <form id="ticker-update-<?= (int) $m['id'] ?>" method="post" action="<?= e(route('/admin/ticker/messages/' . $m['id'] . '/update')) ?>" style="display:none;">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    </form>
<?php endforeach; ?>

<h2>Add message</h2>
<form method="post" action="<?= e(route('/admin/ticker/messages')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <p>
        <label for="message">Message</label><br>
        <input type="text" id="message" name="message" maxlength="280" required style="width:100%;max-width:40rem;">
    </p>
    <p>
        <label for="url">Link URL (optional)</label><br>
        <input type="text" id="url" name="url" style="width:100%;max-width:40rem;">
    </p>
    <p>
        <label for="level">Level</label><br>
        <select id="level" name="level">
            <option value="info">Info</option>
            <option value="warning">Warning</option>
            <option value="urgent">Urgent</option>
        </select>
    </p>
    <p>
        <label for="starts_at">Starts (optional — blank means immediately)</label><br>
        <input type="datetime-local" id="starts_at" name="starts_at">
    </p>
    <p>
        <label for="ends_at">Ends (optional — blank means never expires)</label><br>
        <input type="datetime-local" id="ends_at" name="ends_at">
    </p>
    <p>
        <label for="weight">Weight (lower shows first)</label><br>
        <input type="number" id="weight" name="weight" value="0">
    </p>
    <button type="submit">Add message</button>
</form>
