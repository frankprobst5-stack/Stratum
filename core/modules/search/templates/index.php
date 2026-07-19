<?php
/**
 * @var string $query
 * @var array<int, array{content_type: string, label: string, title: string, snippet: string, url: string, created_at: string}> $results
 */
?>
<h1>Search</h1>
<form action="/search" method="get" style="margin-bottom:1.5rem;display:flex;gap:0.5rem;">
    <input type="text" name="q" value="<?= e($query) ?>" placeholder="Search articles, forum, wiki, downloads, classifieds..." style="padding:0.5rem;flex:1;max-width:32rem;">
    <button type="submit">Search</button>
</form>

<?php if ($query === ''): ?>
    <p style="color:#888;">Enter a search term above.</p>
<?php elseif ($results === []): ?>
    <p style="color:#888;">No results for &ldquo;<?= e($query) ?>&rdquo;.</p>
<?php else: ?>
    <ul style="list-style:none;padding:0;">
        <?php foreach ($results as $result): ?>
            <li style="margin-bottom:1.5rem;border-bottom:1px solid #333;padding-bottom:1rem;">
                <div style="color:#888;font-size:0.8rem;text-transform:uppercase;letter-spacing:0.03em;"><?= e($result['label']) ?></div>
                <a href="<?= e($result['url']) ?>"><strong><?= e($result['title']) ?></strong></a>
                <?php if ($result['snippet'] !== ''): ?>
                    <p><?= e($result['snippet']) ?></p>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
