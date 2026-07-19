<?php
/**
 * @var array<string, mixed> $org
 * @var array<int, array<string, mixed>> $albums
 * @var bool $canManage
 * @var string $csrfToken
 */
$base = '/organizations/' . $org['slug'];
?>
<p><a href="<?= e(route($base)) ?>">&larr; <?= e($org['name']) ?></a></p>

<h1><?= e($org['name']) ?> — Gallery</h1>
<p style="color:#888;">Private to <?= e($org['name']) ?>'s members.</p>

<?php if ($albums === []): ?>
    <p style="color:#888;">No albums yet.</p>
<?php else: ?>
    <div style="display:flex; flex-wrap:wrap; gap:1rem;">
        <?php foreach ($albums as $album): ?>
            <a href="<?= e(route($base . '/gallery/albums/' . $album['id'])) ?>" style="display:block; width:160px; text-decoration:none; color:inherit;">
                <div style="background:#f4f5f7; border-radius:6px; padding:1rem; text-align:center;">
                    <?= e($album['title']) ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<h2>New album</h2>
<form method="post" action="<?= e(route($base . '/gallery/albums')) ?>" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <p>
        <label for="title">Album title</label><br>
        <input type="text" id="title" name="title" required style="width:100%;max-width:400px;">
    </p>
    <p>
        <label for="description">Description (optional)</label><br>
        <textarea id="description" name="description" rows="2" cols="50"></textarea>
    </p>
    <p>
        <label for="photos">Photos (JPEG/PNG/GIF/WebP, 10MB max each)</label><br>
        <input type="file" id="photos" name="photos[]" multiple required>
    </p>
    <button type="submit">Create album</button>
</form>
