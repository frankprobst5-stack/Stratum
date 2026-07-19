<?php
/**
 * @var array<string, mixed> $form
 * @var array<int, array{id: int, label: string, type: string, options: ?string, required: bool}> $fields
 * @var array<int, array<int, array{value: string, count: int}>> $tallies
 * @var array<int, array{submittedAt: string, username: string, answers: array<int, string>}> $submissions
 */
?>
<p><a href="<?= e(route('/admin/forms/' . $form['id'])) ?>">&larr; <?= e($form['title']) ?></a></p>
<h1>Results: <?= e($form['title']) ?></h1>
<p style="color:#666;"><?= count($submissions) ?> submission<?= count($submissions) === 1 ? '' : 's' ?></p>

<?php if ($tallies !== []): ?>
    <h2>Tallies</h2>
    <?php foreach ($fields as $field): ?>
        <?php if (isset($tallies[$field['id']]) && $tallies[$field['id']] !== []): ?>
            <h3><?= e($field['label']) ?></h3>
            <ul>
                <?php foreach ($tallies[$field['id']] as $row): ?>
                    <li><?= e($row['value']) ?> — <?= (int) $row['count'] ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>

<h2>Submissions</h2>
<?php if ($submissions === []): ?>
    <p style="color:#888;">No submissions yet.</p>
<?php else: ?>
    <table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
        <thead>
            <tr style="text-align:left; border-bottom:1px solid #ddd;">
                <th>Member</th>
                <th>Submitted</th>
                <?php foreach ($fields as $field): ?>
                    <th><?= e($field['label']) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($submissions as $submission): ?>
            <tr style="border-bottom:1px solid #eee;">
                <td><?= e($submission['username']) ?></td>
                <td><?= e($submission['submittedAt']) ?></td>
                <?php foreach ($fields as $field): ?>
                    <td><?= e($submission['answers'][$field['id']] ?? '') ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
