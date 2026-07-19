<?php
/**
 * @var array<string, mixed> $org
 * @var array<int, array<string, mixed>> $files each with 'uploaderName'
 * @var bool $canManage
 * @var string $csrfToken
 */
$base = '/organizations/' . $org['slug'];
?>
<p><a href="<?= e(route($base)) ?>">&larr; <?= e($org['name']) ?></a></p>

<h1><?= e($org['name']) ?> — Files</h1>
<p style="color:#888;">Private to <?= e($org['name']) ?>'s members.</p>

<?php if ($files === []): ?>
    <p style="color:#888;">No files yet.</p>
<?php else: ?>
    <table border="0" cellpadding="6" style="border-collapse:collapse; width:100%;">
        <thead>
            <tr style="text-align:left; border-bottom:1px solid #ddd;">
                <th>Title</th>
                <th>Uploaded by</th>
                <th>Size</th>
                <th>Downloads</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($files as $file): ?>
            <tr style="border-bottom:1px solid #eee;">
                <td>
                    <a href="<?= e(route($base . '/files/' . $file['id'] . '/download')) ?>"><?= e($file['title']) ?></a>
                    <?php if (!empty($file['description'])): ?>
                        <div style="color:#888; font-size:0.85rem;"><?= e($file['description']) ?></div>
                    <?php endif; ?>
                </td>
                <td><?= e($file['uploaderName']) ?></td>
                <td><?= number_format((int) $file['size'] / 1024, 1) ?> KB</td>
                <td><?= (int) $file['download_count'] ?></td>
                <td>
                    <?php if ($canManage): ?>
                        <form method="post" action="<?= e(route($base . '/files/' . $file['id'] . '/delete')) ?>">
                            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                            <button type="submit">Delete</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<h2>Upload a file</h2>
<form method="post" action="<?= e(route($base . '/files')) ?>" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <p>
        <label for="title">Title</label><br>
        <input type="text" id="title" name="title" required style="width:100%;max-width:400px;">
    </p>
    <p>
        <label for="description">Description (optional)</label><br>
        <textarea id="description" name="description" rows="2" cols="50"></textarea>
    </p>
    <p>
        <label for="file">File (10MB max — PDF, Word, Excel, ZIP, TXT, JPG, PNG)</label><br>
        <input type="file" id="file" name="file" required>
    </p>
    <button type="submit">Upload</button>
</form>
