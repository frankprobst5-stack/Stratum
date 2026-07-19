<?php
/**
 * @var array<int, array<string, mixed>> $forms
 * @var string $csrfToken
 */
?>
<h1>Surveys & Forms</h1>
<p><a href="<?= e(route('/admin/forms/create')) ?>">+ New form</a></p>

<?php if ($forms === []): ?>
    <p style="color:#888;">No forms yet.</p>
<?php else: ?>
    <table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
        <thead>
            <tr style="text-align:left; border-bottom:1px solid #ddd;">
                <th>Title</th>
                <th>Status</th>
                <th>Submissions</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($forms as $form): ?>
            <tr style="border-bottom:1px solid #eee;">
                <td><a href="<?= e(route('/admin/forms/' . $form['id'])) ?>"><?= e($form['title']) ?></a></td>
                <td><?= e($form['status']) ?></td>
                <td><a href="<?= e(route('/admin/forms/' . $form['id'] . '/results')) ?>"><?= (int) $form['submissionCount'] ?></a></td>
                <td>
                    <form method="post" action="<?= e(route('/admin/forms/' . $form['id'] . '/delete')) ?>" style="display:inline;" onsubmit="return confirm('Delete this form? Its submissions will remain in the database but the form will disappear from this list.');">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <button type="submit" style="border:none; background:none; color:#888; text-decoration:underline; cursor:pointer; padding:0; font-size:0.85rem;">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
