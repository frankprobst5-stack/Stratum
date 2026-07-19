<?php
/**
 * @var array<int, array<string, mixed>> $badges
 * @var string $csrfToken
 */
?>
<h1>Badges</h1>
<p style="color:#666;">Achievement badges are awarded to individual members from their user detail page (Users &rarr; a member &rarr; Badges).</p>

<table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
    <thead>
        <tr style="text-align:left; border-bottom:1px solid #ddd;">
            <th>Badge</th>
            <th>Description</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($badges as $badge): ?>
        <tr style="border-bottom:1px solid #eee;">
            <td>
                <?php if (!empty($badge['icon_url'])): ?>
                    <img src="<?= e($badge['icon_url']) ?>" alt="" style="width:20px; height:20px; vertical-align:middle;">
                <?php endif; ?>
                <?= e($badge['name']) ?>
            </td>
            <td><?= e($badge['description'] ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if ($badges === []): ?>
        <tr><td colspan="2" style="color:#888;">No badges defined yet.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<h2>New badge</h2>
<form method="post" action="<?= e(route('/admin/badges')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <p>
        <label for="name">Name</label><br>
        <input type="text" id="name" name="name" required>
    </p>
    <p>
        <label for="description">Description</label><br>
        <input type="text" id="description" name="description" style="width:100%; max-width:400px;">
    </p>
    <p>
        <label for="icon_url">Icon URL</label><br>
        <input type="text" id="icon_url" name="icon_url" style="width:100%; max-width:400px;" placeholder="https://example.org/assets/images/badge.png">
    </p>
    <button type="submit">Create badge</button>
</form>
