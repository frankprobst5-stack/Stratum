<?php
/**
 * @var array<int, array<string, mixed>> $orgs
 * @var string $csrfToken
 */
?>
<h1>Organizations</h1>

<ul>
    <?php foreach ($orgs as $org): ?>
        <li>
            <a href="<?= e(route('/organizations/' . $org['slug'])) ?>"><?= e($org['name']) ?></a>
            <?= $org['is_active'] ? '' : '<em>(archived)</em>' ?>
            <form method="post" action="<?= e(route('/admin/org_spaces/' . $org['id'] . '/toggle-active')) ?>" style="display:inline;">
                <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                <button type="submit"><?= $org['is_active'] ? 'Archive' : 'Reactivate' ?></button>
            </form>
        </li>
    <?php endforeach; ?>
    <?php if ($orgs === []): ?>
        <li style="color:#888;">No organizations yet.</li>
    <?php endif; ?>
</ul>

<h2>Create an organization</h2>
<form method="post" action="<?= e(route('/admin/org_spaces/create')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <p>
        <label for="name">Name</label><br>
        <input type="text" id="name" name="name" required>
    </p>
    <p>
        <label for="description">Description</label><br>
        <textarea id="description" name="description" rows="3" cols="50" data-bbcode-toolbar></textarea>
    </p>
    <button type="submit">Create organization</button>
</form>
