<?php

declare(strict_types=1);

namespace Stratum\Modules\OrgSpaces;

use Stratum\Core\Database;
use Stratum\Core\FileUploadValidator;

/**
 * Per-org shared files — single-version uploads (no version history like
 * the site-wide `downloads` module has; not part of the confirmed
 * requirement, a natural v1.1 addition if a chapter actually needs it).
 * Reuses the shared FileUploadValidator (5th consumer now, after forum
 * attachments/downloads/video/classifieds) — never trusts the client's
 * declared MIME type or filename extension.
 */
final class OrgFileService
{
    private const MAX_SIZE = 10 * 1024 * 1024; // 10MB, matches every other upload path's cap in this app

    /** @var array<string, string> detected MIME type => stored file extension */
    private const ALLOWED_MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
        'application/zip' => 'zip',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
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

    /** @return array<int, array<string, mixed>> */
    public function listFiles(int $orgId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('org_spaces_files') . '
             WHERE org_id = :org_id AND deleted_at IS NULL ORDER BY created_at DESC',
            ['org_id' => $orgId]
        );
    }

    /** @return array<string, mixed>|null */
    public function findFile(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('org_spaces_files') . ' WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    /** @param array{tmpPath: string, originalName: string, mimeType: string, extension: string, size: int} $validated */
    public function storeFile(int $orgId, int $uploaderId, string $title, string $description, array $validated): int
    {
        $subdir = date('Y/m');
        $targetDir = "{$this->storageDir}/{$subdir}";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . $validated['extension'];
        move_uploaded_file($validated['tmpPath'], "{$targetDir}/{$storedName}");

        $now = date('Y-m-d H:i:s');

        return (int) $this->db->insert('org_spaces_files', [
            'org_id' => $orgId,
            'uploader_id' => $uploaderId,
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'filename' => "{$subdir}/{$storedName}",
            'original_name' => $validated['originalName'],
            'mime_type' => $validated['mimeType'],
            'size' => $validated['size'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function incrementDownloadCount(int $id): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('org_spaces_files') . ' SET download_count = download_count + 1 WHERE id = :id',
            ['id' => $id]
        );
    }

    public function softDeleteFile(int $id): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('org_spaces_files') . ' SET deleted_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    /** @param array<string, mixed> $file */
    public function absolutePath(array $file): string
    {
        return "{$this->storageDir}/{$file['filename']}";
    }
}
