<?php
/**
 * @var array<int, array<string, mixed>> $articles each with 'authorName'
 */
?>
<h1>Articles</h1>

<?php if ($articles === []): ?>
    <p>No articles yet.</p>
<?php endif; ?>

<?php foreach ($articles as $article): ?>
    <article style="margin-bottom:1.5rem; padding-bottom:1.5rem; border-bottom:1px solid #eee;">
        <h2><a href="<?= e(route('/articles/' . $article['slug'])) ?>"><?= e($article['title']) ?></a></h2>
        <p style="color:#666; font-size:0.9rem;">
            by <?= e($article['authorName']) ?> &middot; <?= e($article['published_at']) ?>
        </p>
        <?php if (!empty($article['excerpt'])): ?>
            <p><?= e($article['excerpt']) ?></p>
        <?php endif; ?>
    </article>
<?php endforeach; ?>
