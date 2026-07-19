<?php
/**
 * @var array<int, array{id: int, name: string, slug: string, links: array<int, array<string, mixed>>}> $categories
 * @var bool $canSubmit
 */
?>
<h1>Link Directory</h1>

<?php if ($canSubmit): ?>
    <p><a href="<?= e(route('/links/submit')) ?>">+ Submit a link</a></p>
<?php endif; ?>

<?php if ($categories === []): ?>
    <p style="color:#888;">No link categories yet.</p>
<?php endif; ?>

<?php foreach ($categories as $category): ?>
    <h2><?= e($category['name']) ?></h2>
    <?php if ($category['links'] === []): ?>
        <p style="color:#888;">No links in this category yet.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($category['links'] as $link): ?>
                <li style="margin-bottom:0.5rem;">
                    <a href="<?= e(route('/links/' . $link['id'] . '/visit')) ?>" target="_blank" rel="noopener noreferrer">
                        <?= e($link['title']) ?>
                    </a>
                    <?php if (!empty($link['description'])): ?>
                        <br><small style="color:#666;"><?= e($link['description']) ?></small>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
<?php endforeach; ?>
