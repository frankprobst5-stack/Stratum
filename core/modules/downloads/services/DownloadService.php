<?php

declare(strict_types=1);

namespace Stratum\Modules\Downloads;

use Stratum\Core\ClamAvScanner;
use Stratum\Core\Database;
use Stratum\Core\FileUploadValidator;
use Stratum\Core\Slug;

final class DownloadService
{
    private const MAX_SIZE = 10 * 1024 * 1024; // 10MB, matches forum attachments — a conservative v1 default

    /** @var array<string, string> detected MIME type => stored file extension */
    private const ALLOWED_MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
        'application/zip' => 'zip',
    ];

    private readonly FileUploadValidator $validator;
    private readonly ClamAvScanner $scanner;

    public function __construct(
        private readonly Database $db,
        private readonly string $storageDir
    ) {
        $this->validator = new FileUploadValidator(self::MAX_SIZE, self::ALLOWED_MIME_EXTENSIONS);
        $this->scanner = new ClamAvScanner();
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
            'SELECT id, name, slug FROM ' . $this->db->table('downloads_categories') . ' ORDER BY weight, name'
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
        $this->db->insert('downloads_categories', [
            'name' => $name,
            'slug' => $this->uniqueSlug('downloads_categories', $name, 'category'),
            'weight' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function findCategoryBySlug(string $slug): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('downloads_categories') . ' WHERE slug = :slug',
            ['slug' => $slug]
        );
    }

    /** @return array<int, array<string, mixed>> files (with computed current-version fields) for a category */
    public function listFiles(int $categoryId): array
    {
        $files = $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('downloads_files') . '
             WHERE category_id = :category_id AND deleted_at IS NULL
             ORDER BY title ASC',
            ['category_id' => $categoryId]
        );

        return array_map(fn (array $file): array => $file + ['currentVersion' => $this->currentVersion((int) $file['id'])], $files);
    }

    /**
     * Cross-category recent files — listFiles() is scoped to one category,
     * nothing else here queries across all of them. Backs the "Downloads
     * List" front-page block.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listRecent(int $limit): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('downloads_files') . '
             WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT ' . max(1, $limit)
        );
    }

    /** @return array<string, mixed>|null */
    public function findFile(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('downloads_files') . ' WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    /**
     * Creates the file container and its first version in one step — a file
     * with zero versions makes no sense, same reasoning as
     * ForumService::createTopicWithFirstPost().
     *
     * @param array{tmpPath: string, originalName: string, mimeType: string, extension: string, size: int} $validated
     */
    public function createFile(int $categoryId, string $title, string $description, int $uploaderId, array $validated): int
    {
        $now = date('Y-m-d H:i:s');

        $fileId = (int) $this->db->insert('downloads_files', [
            'category_id' => $categoryId,
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'download_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->storeVersion($fileId, $uploaderId, $validated, 1);

        return $fileId;
    }

    /**
     * @param array{tmpPath: string, originalName: string, mimeType: string, extension: string, size: int} $validated
     */
    public function addVersion(int $fileId, int $uploaderId, array $validated): void
    {
        $next = 1 + (int) ($this->currentVersion($fileId)['version_number'] ?? 0);
        $this->storeVersion($fileId, $uploaderId, $validated, $next);

        $this->db->execute(
            'UPDATE ' . $this->db->table('downloads_files') . ' SET updated_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $fileId]
        );
    }

    /** @return array<string, mixed>|null the latest version, or null if the file somehow has none */
    public function currentVersion(int $fileId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('downloads_versions') . '
             WHERE file_id = :file_id ORDER BY version_number DESC LIMIT 1',
            ['file_id' => $fileId]
        );
    }

    /** @return array<int, array<string, mixed>> newest first */
    public function listVersions(int $fileId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('downloads_versions') . '
             WHERE file_id = :file_id ORDER BY version_number DESC',
            ['file_id' => $fileId]
        );
    }

    /** @return array<string, mixed>|null */
    public function findVersion(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('downloads_versions') . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    public function incrementDownloadCount(int $fileId): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('downloads_files') . ' SET download_count = download_count + 1 WHERE id = :id',
            ['id' => $fileId]
        );
    }

    public function softDeleteFile(int $id): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('downloads_files') . ' SET deleted_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    public function absolutePath(array $version): string
    {
        return "{$this->storageDir}/{$version['filename']}";
    }

    /** @param array{tmpPath: string, originalName: string, mimeType: string, extension: string, size: int} $validated */
    private function storeVersion(int $fileId, int $uploaderId, array $validated, int $versionNumber): void
    {
        $subdir = date('Y/m');
        $targetDir = "{$this->storageDir}/{$subdir}";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . $validated['extension'];
        $storedPath = "{$targetDir}/{$storedName}";
        move_uploaded_file($validated['tmpPath'], $storedPath);

        $this->db->insert('downloads_versions', [
            'file_id' => $fileId,
            'uploader_id' => $uploaderId,
            'filename' => "{$subdir}/{$storedName}",
            'original_name' => $validated['originalName'],
            'mime_type' => $validated['mimeType'],
            'size' => $validated['size'],
            'scan_status' => $this->scanner->scan($storedPath),
            'version_number' => $versionNumber,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return array<int, array{id: int, label: string, url: string}> */
    public function listMirrors(int $fileId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT id, label, url FROM ' . $this->db->table('downloads_mirrors') . '
             WHERE file_id = :file_id ORDER BY id',
            ['file_id' => $fileId]
        );

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'label' => $r['label'],
            'url' => $r['url'],
        ], $rows);
    }

    public function addMirror(int $fileId, string $label, string $url): void
    {
        $this->db->insert('downloads_mirrors', [
            'file_id' => $fileId,
            'label' => $label,
            'url' => $url,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function deleteMirror(int $mirrorId): void
    {
        $this->db->execute(
            'DELETE FROM ' . $this->db->table('downloads_mirrors') . ' WHERE id = :id',
            ['id' => $mirrorId]
        );
    }

    private function uniqueSlug(string $table, string $value, string $fallback): string
    {
        $base = Slug::make($value, $fallback);
        $slug = $base;
        $suffix = 2;

        while ($this->db->fetchOne(
            "SELECT id FROM " . $this->db->table($table) . " WHERE slug = :slug",
            ['slug' => $slug]
        ) !== null) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
