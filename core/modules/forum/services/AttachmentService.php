<?php

declare(strict_types=1);

namespace Stratum\Modules\Forum;

use Stratum\Core\Database;
use Stratum\Core\FileUploadValidator;

/**
 * Validates and stores forum post attachments per docs/coding-standards.md:
 * stored outside the web root, MIME-sniffed via finfo (not trusted from the
 * client's declared type or filename extension), served through a
 * controller rather than directly from storage/. Validation itself is
 * delegated to the shared FileUploadValidator (promoted to core when
 * downloads/Stage 5a became a second consumer) — this class keeps only the
 * forum-specific storage/DB glue.
 */
final class AttachmentService
{
    private const MAX_SIZE = 10 * 1024 * 1024; // 10MB

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
    public function validate(array $fileEntry): ?array
    {
        return $this->validator->validate($fileEntry);
    }

    /** @param array{tmpPath: string, originalName: string, mimeType: string, extension: string, size: int} $validated */
    public function store(array $validated, int $postId): void
    {
        $subdir = date('Y/m');
        $targetDir = "{$this->storageDir}/{$subdir}";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . $validated['extension'];
        move_uploaded_file($validated['tmpPath'], "{$targetDir}/{$storedName}");

        $this->db->insert('forum_attachments', [
            'post_id' => $postId,
            'filename' => "{$subdir}/{$storedName}",
            'original_name' => $validated['originalName'],
            'mime_type' => $validated['mimeType'],
            'size' => $validated['size'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('forum_attachments') . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function listForPost(int $postId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('forum_attachments') . ' WHERE post_id = :post_id ORDER BY created_at ASC',
            ['post_id' => $postId]
        );
    }

    /** @param array<string, mixed> $attachment */
    public function absolutePath(array $attachment): string
    {
        return "{$this->storageDir}/{$attachment['filename']}";
    }
}
