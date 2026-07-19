<?php
/**
 * @var array<int, array{bookmark_id: int, type: string, title: string, url: string, created_at: string}> $bookmarks
 */
$labels = [
    'article' => 'Article',
    'wiki_page' => 'Wiki',
    'forum_topic' => 'Forum',
];
?>
<h1>My Bookmarks</h1>

<?php if ($bookmarks === []): ?>
    <p style="color:#888;">Nothing saved yet. Look for a Bookmark button on articles, wiki pages, and forum topics.</p>
<?php else: ?>
    <ul style="list-style:none;padding:0;">
        <?php foreach ($bookmarks as $bookmark): ?>
            <li style="margin-bottom:1rem;border-bottom:1px solid #333;padding-bottom:0.75rem;">
                <div style="color:#888;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.03em;"><?= e($labels[$bookmark['type']] ?? $bookmark['type']) ?></div>
                <a href="<?= e($bookmark['url']) ?>"><strong><?= e($bookmark['title']) ?></strong></a>
                <span style="color:#888;font-size:0.8rem;"> &middot; saved <?= e($bookmark['created_at']) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
