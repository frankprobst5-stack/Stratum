<?php

declare(strict_types=1);

namespace Stratum\Modules\OrgSpaces;

use Stratum\Core\Database;
use Stratum\Core\FileUploadValidator;
use Stratum\Core\ImageThumbnailer;

/**
 * Per-org shared photo gallery — albums + photos with real GD thumbnails,
 * same shape as the site-wide `gallery` module minus likes/EXIF (not part
 * of the confirmed requirement; natural v1.1 additions). Reuses the
 * shared FileUploadValidator and ImageThumbnailer core services — no
 * parallel thumbnail-generation logic.
 */
final class OrgGalleryService
{
    private const MAX_SIZE = 10 * 1024 * 1024;
    private const THUMBNAIL_WIDTH = 300;

    /** @var array<string, string> */
    private const ALLOWED_MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    private readonly FileUploadValidator $validator;
    private readonly ImageThumbnailer $thumbnailer;

    public function __construct(
        private readonly Database $db,
        private readonly string $storageDir
    ) {
        $this->validator = new FileUploadValidator(self::MAX_SIZE, self::ALLOWED_MIME_EXTENSIONS);
        $this->thumbnailer = new ImageThumbnailer();
    }

    /**
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $fileEntry
     * @return array{tmpPath: string, originalName: string, mimeType: string, extension: string, size: int}|null
     */
    public function validateUpload(array $fileEntry): ?array
    {
        return $this->validator->validate($fileEntry);
    }

    /** @return array<int, array<string, mixed>> */
    public function listAlbums(int $orgId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('org_spaces_gallery_albums') . '
             WHERE org_id = :org_id AND deleted_at IS NULL ORDER BY created_at DESC',
            ['org_id' => $orgId]
        );
    }

    /** @return array<string, mixed>|null */
    public function findAlbum(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('org_spaces_gallery_albums') . ' WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    /**
     * Creates the album together with its first batch of validated photos
     * in one step — an album is never empty, same shape as the site-wide
     * GalleryService::createAlbum().
     *
     * @param array<int, array{tmpPath: string, originalName: string, mimeType: string, extension: string, size: int}> $validatedPhotos
     */
    public function createAlbum(int $orgId, string $title, string $description, int $uploaderId, array $validatedPhotos): int
    {
        $now = date('Y-m-d H:i:s');

        $albumId = (int) $this->db->insert('org_spaces_gallery_albums', [
            'org_id' => $orgId,
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($validatedPhotos as $validated) {
            $this->storePhoto($albumId, $uploaderId, $validated);
        }

        return $albumId;
    }

    /** @return array<int, array<string, mixed>> */
    public function listPhotos(int $albumId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('org_spaces_gallery_photos') . '
             WHERE album_id = :album_id AND deleted_at IS NULL ORDER BY created_at ASC',
            ['album_id' => $albumId]
        );
    }

    /** @return array<string, mixed>|null */
    public function findPhoto(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('org_spaces_gallery_photos') . ' WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    public function softDeletePhoto(int $id): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('org_spaces_gallery_photos') . ' SET deleted_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    public function softDeleteAlbum(int $id): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('org_spaces_gallery_albums') . ' SET deleted_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    /** @param array<string, mixed> $photo */
    public function absolutePath(array $photo): string
    {
        return "{$this->storageDir}/{$photo['filename']}";
    }

    /** @param array<string, mixed> $photo */
    public function absoluteThumbnailPath(array $photo): string
    {
        return "{$this->storageDir}/{$photo['thumbnail_filename']}";
    }

    /** @param array{tmpPath: string, originalName: string, mimeType: string, extension: string, size: int} $validated */
    private function storePhoto(int $albumId, int $uploaderId, array $validated): void
    {
        $subdir = date('Y/m');
        $targetDir = "{$this->storageDir}/{$subdir}";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $baseName = bin2hex(random_bytes(16));
        $storedName = "{$baseName}.{$validated['extension']}";
        $absolutePath = "{$targetDir}/{$storedName}";
        move_uploaded_file($validated['tmpPath'], $absolutePath);

        $dimensions = @getimagesize($absolutePath);
        $width = $dimensions !== false ? (int) $dimensions[0] : null;
        $height = $dimensions !== false ? (int) $dimensions[1] : null;

        $thumbnailName = "{$baseName}_thumb.jpg";
        $this->thumbnailer->make($absolutePath, $validated['mimeType'], "{$targetDir}/{$thumbnailName}", self::THUMBNAIL_WIDTH);

        $now = date('Y-m-d H:i:s');

        $this->db->insert('org_spaces_gallery_photos', [
            'album_id' => $albumId,
            'uploader_id' => $uploaderId,
            'filename' => "{$subdir}/{$storedName}",
            'thumbnail_filename' => "{$subdir}/{$thumbnailName}",
            'mime_type' => $validated['mimeType'],
            'size' => $validated['size'],
            'width' => $width,
            'height' => $height,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
