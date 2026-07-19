<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Validates an uploaded file against a size cap and an allow-list of MIME
 * types, sniffed via finfo — never trusts the client's declared type or the
 * filename extension. Promoted here once downloads (Stage 5a) became a
 * second real consumer of forum's original AttachmentService validation
 * logic (Stage 3b) — same "promote on 2nd/3rd consumer" rule that already
 * promoted BBCodeParser and Slug. Each caller supplies its own size/allow-list,
 * so modules don't have to agree on limits, just share the mechanism.
 */
final class FileUploadValidator
{
    /** @param array<string, string> $allowedMimeExtensions detected MIME type => stored file extension */
    public function __construct(
        private readonly int $maxSize,
        private readonly array $allowedMimeExtensions
    ) {
    }

    /**
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $fileEntry
     * @return array{tmpPath: string, originalName: string, mimeType: string, extension: string, size: int}|null
     */
    public function validate(array $fileEntry): ?array
    {
        if (($fileEntry['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        $size = (int) ($fileEntry['size'] ?? 0);
        if ($size <= 0 || $size > $this->maxSize) {
            return null;
        }

        $tmpPath = (string) $fileEntry['tmp_name'];
        if (!is_uploaded_file($tmpPath)) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $tmpPath) ?: '';
        finfo_close($finfo);

        $extension = $this->allowedMimeExtensions[$detectedMime] ?? null;
        if ($extension === null) {
            return null; // not on the allow-list — irrelevant what the client claimed
        }

        return [
            'tmpPath' => $tmpPath,
            'originalName' => basename((string) $fileEntry['name']),
            'mimeType' => $detectedMime,
            'extension' => $extension,
            'size' => $size,
        ];
    }
}
