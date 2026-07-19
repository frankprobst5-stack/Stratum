<?php
/**
 * @var array<int, array<string, mixed>> $forms
 */
?>
<h1>Forms & Surveys</h1>

<?php if ($forms === []): ?>
    <p style="color:#888;">No forms are open right now.</p>
<?php else: ?>
    <ul>
        <?php foreach ($forms as $form): ?>
            <li style="margin-bottom:0.75rem;">
                <a href="<?= e(route('/forms/' . $form['slug'])) ?>"><?= e($form['title']) ?></a>
                <?php if (!empty($form['description'])): ?>
                    <br><small style="color:#666;"><?= e($form['description']) ?></small>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
