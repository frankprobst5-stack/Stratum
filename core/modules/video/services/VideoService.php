<?php

declare(strict_types=1);

namespace Stratum\Modules\Video;

use Stratum\Core\Database;
use Stratum\Core\FileUploadValidator;
use Stratum\Core\Slug;

final class VideoService
{
    private const MAX_SIZE = 50 * 1024 * 1024; // 50MB — bigger than downloads' 10MB, still a fixed v1 cap

    /** @var array<string, string> detected MIME type => stored file extension */
    private const ALLOWED_MIME_EXTENSIONS = [
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
    ];

    private readonly FileUploadValidator $validator;

    public function __construct(
        private readonly Database $db,
        private readonly string $storageDir
    ) {
        $this->validator = new FileUploadValidator(self::MAX_SIZE, self::ALLOWED_MIME_EXTENSIONS);
    }

    /**
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $fileEntry
     * @return array{tmpPath: string, originalName: string, mimeType: string, extension: string, size: int}|null
     */
    public function validateUpload(array $fileEntry): ?array
    {
        return $this->validator->validate($fileEntry);
    }

    /** @return array<int, array{id: int, name: string, slug: string}> */
    public function listCategories(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT id, name, slug FROM ' . $this->db->table('video_categories') . ' ORDER BY weight, name'
        );

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'slug' => $r['slug'],
        ], $rows);
    }

    public function createCategory(string $name): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->insert('video_categories', [
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'weight' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function listVideos(int $categoryId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('videos') . '
             WHERE category_id = :category_id AND deleted_at IS NULL
             ORDER BY created_at DESC',
            ['category_id' => $categoryId]
        );
    }

    /**
     * Cross-category recent videos — listVideos() is scoped to one
     * category, nothing else here queries across all of them. Backs the
     * "Recent Videos" front-page block.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listRecent(int $limit): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('videos') . '
             WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT ' . max(1, $limit)
        );
    }

    /** @return array<string, mixed>|null */
    public function findVideo(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('videos') . ' WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    public function createFromUrl(int $categoryId, int $uploaderId, string $title, string $description, string $sourceType, string $externalId): int
    {
        return $this->insertVideo($categoryId, $uploaderId, $title, $description, [
            'source_type' => $sourceType,
            'external_id' => $externalId,
            'filename' => null,
            'mime_type' => null,
            'size' => null,
        ]);
    }

    /** @param array{tmpPath: string, originalName: string, mimeType: string, extension: string, size: int} $validated */
    public function createFromUpload(int $categoryId, int $uploaderId, string $title, string $description, array $validated): int
    {
        $subdir = date('Y/m');
        $targetDir = "{$this->storageDir}/{$subdir}";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . $validated['extension'];
        move_uploaded_file($validated['tmpPath'], "{$targetDir}/{$storedName}");

        return $this->insertVideo($categoryId, $uploaderId, $title, $description, [
            'source_type' => 'upload',
            'external_id' => null,
            'filename' => "{$subdir}/{$storedName}",
            'mime_type' => $validated['mimeType'],
            'size' => $validated['size'],
        ]);
    }

    public function incrementViewCount(int $id): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('videos') . ' SET view_count = view_count + 1 WHERE id = :id',
            ['id' => $id]
        );
    }

    public function softDeleteVideo(int $id): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('videos') . ' SET deleted_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    /** @param array<string, mixed> $video */
    public function absolutePath(array $video): string
    {
        return "{$this->storageDir}/{$video['filename']}";
    }

    /** @param array{source_type: string, external_id: ?string, filename: ?string, mime_type: ?string, size: ?int} $sourceFields */
    private function insertVideo(int $categoryId, int $uploaderId, string $title, string $description, array $sourceFields): int
    {
        $now = date('Y-m-d H:i:s');

        return (int) $this->db->insert('videos', array_merge([
            'category_id' => $categoryId,
            'uploader_id' => $uploaderId,
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'view_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], $sourceFields));
    }

    /** @return array<int, array<string, mixed>> */
    public function listPlaylists(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('video_playlists') . '
             WHERE deleted_at IS NULL ORDER BY created_at DESC'
        );
    }

    /** @return array<string, mixed>|null */
    public function findPlaylistBySlug(string $slug): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('video_playlists') . ' WHERE slug = :slug AND deleted_at IS NULL',
            ['slug' => $slug]
        );
    }

    /** @return array<string, mixed>|null */
    public function findPlaylist(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('video_playlists') . ' WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    public function createPlaylist(string $title, string $description, ?int $createdBy): int
    {
        $now = date('Y-m-d H:i:s');

        return (int) $this->db->insert('video_playlists', [
            'title' => $title,
            'slug' => $this->uniquePlaylistSlug($title),
            'description' => $description !== '' ? $description : null,
            'created_by' => $createdBy,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function softDeletePlaylist(int $id): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('video_playlists') . ' SET deleted_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    /** @return array<int, array<string, mixed>> playlist's videos in order, each with the underlying video row's fields */
    public function listPlaylistVideos(int $playlistId): array
    {
        $items = $this->db->table('video_playlist_items');
        $videos = $this->db->table('videos');

        return $this->db->fetchAll(
            "SELECT v.*, i.id AS item_id, i.position
             FROM {$items} i
             INNER JOIN {$videos} v ON v.id = i.video_id AND v.deleted_at IS NULL
             WHERE i.playlist_id = :playlist_id
             ORDER BY i.position ASC",
            ['playlist_id' => $playlistId]
        );
    }

    /** True if the video was added; false if it was already in the playlist (UNIQUE constraint, not an error). */
    public function addToPlaylist(int $playlistId, int $videoId): bool
    {
        $existing = $this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('video_playlist_items') . '
             WHERE playlist_id = :playlist_id AND video_id = :video_id',
            ['playlist_id' => $playlistId, 'video_id' => $videoId]
        );
        if ($existing !== null) {
            return false;
        }

        $row = $this->db->fetchOne(
            'SELECT COALESCE(MAX(position), -1) AS max_position FROM ' . $this->db->table('video_playlist_items') . '
             WHERE playlist_id = :playlist_id',
            ['playlist_id' => $playlistId]
        );
        $position = $row !== null ? ((int) $row['max_position']) + 1 : 0;

        $this->db->insert('video_playlist_items', [
            'playlist_id' => $playlistId,
            'video_id' => $videoId,
            'position' => $position,
        ]);

        return true;
    }

    public function removeFromPlaylist(int $itemId): void
    {
        $this->db->execute(
            'DELETE FROM ' . $this->db->table('video_playlist_items') . ' WHERE id = :id',
            ['id' => $itemId]
        );
    }

    /**
     * Swaps $itemId's position with its neighbor in $direction ('up' or
     * 'down') — the simplest reorder mechanism that works without any
     * client-side drag-drop JS, matching this app's server-rendered UI.
     */
    public function moveItem(int $playlistId, int $itemId, string $direction): void
    {
        $table = $this->db->table('video_playlist_items');
        $items = $this->db->fetchAll(
            "SELECT id, position FROM {$table} WHERE playlist_id = :playlist_id ORDER BY position ASC",
            ['playlist_id' => $playlistId]
        );

        $index = null;
        foreach ($items as $i => $item) {
            if ((int) $item['id'] === $itemId) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            return;
        }

        $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;
        if ($swapIndex < 0 || $swapIndex >= count($items)) {
            return;
        }

        $a = $items[$index];
        $b = $items[$swapIndex];

        $this->db->execute("UPDATE {$table} SET position = :position WHERE id = :id", ['position' => $b['position'], 'id' => $a['id']]);
        $this->db->execute("UPDATE {$table} SET position = :position WHERE id = :id", ['position' => $a['position'], 'id' => $b['id']]);
    }

    private function uniquePlaylistSlug(string $value): string
    {
        $base = Slug::make($value, 'playlist');
        $slug = $base;
        $suffix = 2;

        while ($this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('video_playlists') . ' WHERE slug = :slug',
            ['slug' => $slug]
        ) !== null) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function uniqueSlug(string $value): string
    {
        $base = Slug::make($value, 'category');
        $slug = $base;
        $suffix = 2;

        while ($this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('video_categories') . ' WHERE slug = :slug',
            ['slug' => $slug]
        ) !== null) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
