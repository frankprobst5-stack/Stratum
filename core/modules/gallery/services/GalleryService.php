<?php

declare(strict_types=1);

namespace Stratum\Modules\Gallery;

use Stratum\Core\Database;
use Stratum\Core\FileUploadValidator;
use Stratum\Core\ImageThumbnailer;

final class GalleryService
{
    private const MAX_SIZE = 10 * 1024 * 1024; // 10MB, matches downloads' image cap
    private const THUMBNAIL_WIDTH = 300;

    /** @var array<string, string> detected MIME type => stored file extension */
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
    public function listAlbums(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('gallery_albums') . '
             WHERE deleted_at IS NULL ORDER BY created_at DESC'
        );
    }

    /** @return array<string, mixed>|null */
    public function findAlbum(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('gallery_albums') . ' WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    /**
     * Creates the album together with its first batch of already-validated
     * photos in one step — an album is never empty, same "container +
     * first content together" shape as ForumService::createTopicWithFirstPost()
     * and DownloadService::createFile().
     *
     * @param array<int, array{tmpPath: string, originalName: string, mimeType: string, extension: string, size: int}> $validatedPhotos
     */
    public function createAlbum(string $title, string $description, int $uploaderId, array $validatedPhotos): int
    {
        $now = date('Y-m-d H:i:s');

        $albumId = (int) $this->db->insert('gallery_albums', [
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($validatedPhotos as $validated) {
            $this->storePhoto($albumId, $uploaderId, '', $validated);
        }

        return $albumId;
    }

    /**
     * @param array<int, array{tmpPath: string, originalName: string, mimeType: string, extension: string, size: int}> $validatedPhotos
     */
    public function addPhotos(int $albumId, int $uploaderId, array $validatedPhotos): void
    {
        foreach ($validatedPhotos as $validated) {
            $this->storePhoto($albumId, $uploaderId, '', $validated);
        }

        $this->db->execute(
            'UPDATE ' . $this->db->table('gallery_albums') . ' SET updated_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $albumId]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function listPhotos(int $albumId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('gallery_photos') . '
             WHERE album_id = :album_id AND deleted_at IS NULL ORDER BY created_at ASC',
            ['album_id' => $albumId]
        );
    }

    /** @return array<string, mixed>|null */
    public function findPhoto(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('gallery_photos') . ' WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    /**
     * Cross-album recent photos — listPhotos() is scoped to one album,
     * nothing else here queries across all of them. Backs the "Gallery
     * Highlights" front-page block.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listRecentPhotos(int $limit): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('gallery_photos') . '
             WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT ' . max(1, $limit)
        );
    }

    public function toggleLike(int $photoId, int $userId): void
    {
        $table = $this->db->table('gallery_likes');
        $existing = $this->db->fetchOne(
            "SELECT id FROM {$table} WHERE photo_id = :photo_id AND user_id = :user_id",
            ['photo_id' => $photoId, 'user_id' => $userId]
        );

        if ($existing !== null) {
            $this->db->execute("DELETE FROM {$table} WHERE id = :id", ['id' => $existing['id']]);

            return;
        }

        $this->db->insert('gallery_likes', [
            'photo_id' => $photoId,
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function likeCount(int $photoId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS c FROM ' . $this->db->table('gallery_likes') . ' WHERE photo_id = :photo_id',
            ['photo_id' => $photoId]
        );

        return (int) ($row['c'] ?? 0);
    }

    public function hasLiked(int $photoId, int $userId): bool
    {
        return $this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('gallery_likes') . ' WHERE photo_id = :photo_id AND user_id = :user_id',
            ['photo_id' => $photoId, 'user_id' => $userId]
        ) !== null;
    }

    public function softDeletePhoto(int $id): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('gallery_photos') . ' SET deleted_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    public function softDeleteAlbum(int $id): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('gallery_albums') . ' SET deleted_at = :now WHERE id = :id',
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
    private function storePhoto(int $albumId, int $uploaderId, string $caption, array $validated): void
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

        $exif = $this->extractExif($absolutePath, $validated['mimeType']);

        $this->db->insert('gallery_photos', [
            'album_id' => $albumId,
            'uploader_id' => $uploaderId,
            'caption' => $caption !== '' ? $caption : null,
            'filename' => "{$subdir}/{$storedName}",
            'thumbnail_filename' => "{$subdir}/{$thumbnailName}",
            'mime_type' => $validated['mimeType'],
            'size' => $validated['size'],
            'width' => $width,
            'height' => $height,
            'exif_json' => $exif !== null ? json_encode($exif, JSON_UNESCAPED_SLASHES) : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * JPEG-only — exif_read_data() doesn't support PNG/GIF/WebP. Non-JPEG
     * uploads simply get no EXIF data, not an error.
     *
     * @return array{camera: string, takenAt: ?string}|null
     */
    private function extractExif(string $path, string $mimeType): ?array
    {
        if ($mimeType !== 'image/jpeg') {
            return null;
        }

        $data = @exif_read_data($path, 'ANY_TAG', true);
        if ($data === false) {
            return null;
        }

        $camera = trim((string) ($data['IFD0']['Model'] ?? ''));
        $takenAt = $data['EXIF']['DateTimeOriginal'] ?? null;

        if ($camera === '' && $takenAt === null) {
            return null;
        }

        return ['camera' => $camera, 'takenAt' => $takenAt];
    }
}
