<?php
/**
 * @var array<string, mixed> $org
 * @var array<int, array<string, mixed>> $topics each with 'authorName', 'reply_count', 'last_post_at'
 * @var bool $canManage
 * @var string $csrfToken
 */
$base = '/organizations/' . $org['slug'];
?>
<p><a href="<?= e(route($base)) ?>">&larr; <?= e($org['name']) ?></a></p>

<h1><?= e($org['name']) ?> — Forum</h1>
<p style="color:#888;">This forum is private to <?= e($org['name']) ?>'s members.</p>

<?php if ($topics === []): ?>
    <p style="color:#888;">No topics yet.</p>
<?php else: ?>
    <table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
        <thead>
            <tr style="text-align:left; border-bottom:1px solid #ddd;">
                <th>Topic</th>
                <th>Started by</th>
                <th>Replies</th>
                <th>Last activity</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($topics as $topic): ?>
            <tr style="border-bottom:1px solid #eee;">
                <td>
                    <?php if ($topic['is_locked']): ?><strong>[Locked]</strong> <?php endif; ?>
                    <a href="<?= e(route($base . '/forum/topics/' . $topic['id'])) ?>"><?= e($topic['title']) ?></a>
                </td>
                <td><?= e($topic['authorName']) ?></td>
                <td><?= (int) $topic['reply_count'] ?></td>
                <td><?= e($topic['last_post_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<h2>New topic</h2>
<form method="post" action="<?= e(route($base . '/forum/topics')) ?>">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <p>
        <label for="title">Title</label><br>
        <input type="text" id="title" name="title" required style="width:100%;max-width:400px;">
    </p>
    <p>
        <textarea name="body" rows="4" cols="60" required data-bbcode-toolbar></textarea><br>
        <small style="color:#666;">Supports [b] [i] [u] [url] [quote] [code]</small>
    </p>
    <button type="submit">Post topic</button>
</form>
