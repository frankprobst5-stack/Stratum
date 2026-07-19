<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Generates a resized JPEG thumbnail from an uploaded image via GD.
 * Promoted here once classifieds became a second real consumer of
 * gallery's original thumbnail logic (Stage 5c) — same "promote on
 * 2nd/3rd consumer" rule that already promoted BBCodeParser, Slug, and
 * FileUploadValidator.
 */
final class ImageThumbnailer
{
    /** @var array<string, string> */
    private const SUPPORTED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /**
     * Always re-encodes as JPEG regardless of source format, for one
     * consistent content-type when serving thumbnails — a PNG with
     * transparency loses it (shows a white background), a deliberate v1
     * cosmetic tradeoff, not a functional bug. Silently does nothing if
     * $mimeType isn't a supported image type or GD fails to read it.
     */
    public function make(string $sourcePath, string $mimeType, string $destPath, int $maxWidth = 300): void
    {
        if (!in_array($mimeType, self::SUPPORTED_MIME_TYPES, true)) {
            return;
        }

        $image = match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/gif' => @imagecreatefromgif($sourcePath),
            'image/webp' => @imagecreatefromwebp($sourcePath),
            default => false,
        };

        if ($image === false) {
            return;
        }

        $thumbnail = imagescale($image, $maxWidth);
        if ($thumbnail !== false) {
            imagejpeg($thumbnail, $destPath, 80);
            imagedestroy($thumbnail);
        }

        imagedestroy($image);
    }
}
