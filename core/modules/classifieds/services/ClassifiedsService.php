<?php

declare(strict_types=1);

namespace Stratum\Modules\Classifieds;

use Stratum\Core\Database;
use Stratum\Core\FileUploadValidator;
use Stratum\Core\ImageThumbnailer;
use Stratum\Core\Slug;

final class ClassifiedsService
{
    private const MAX_SIZE = 10 * 1024 * 1024; // 10MB, matches downloads/gallery's image cap
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

    /** @return array<int, array{id: int, name: string, slug: string}> */
    public function listCategories(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT id, name, slug FROM ' . $this->db->table('classifieds_categories') . ' ORDER BY weight, name'
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
        $this->db->insert('classifieds_categories', [
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'weight' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function listListings(int $categoryId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('classifieds_listings') . '
             WHERE category_id = :category_id AND deleted_at IS NULL
             ORDER BY status ASC, created_at DESC',
            ['category_id' => $categoryId]
        );
    }

    /** @return array<string, mixed>|null */
    public function findListing(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('classifieds_listings') . ' WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    /**
     * @param array{tmpPath: string, originalName: string, mimeType: string, extension: string, size: int}|null $validatedPhoto
     */
    public function createListing(int $categoryId, int $userId, string $title, string $description, ?string $price, ?array $validatedPhoto): int
    {
        $now = date('Y-m-d H:i:s');

        $photoFields = ['filename' => null, 'thumbnail_filename' => null, 'mime_type' => null, 'size' => null];
        if ($validatedPhoto !== null) {
            $photoFields = $this->storePhoto($validatedPhoto);
        }

        return (int) $this->db->insert('classifieds_listings', array_merge([
            'category_id' => $categoryId,
            'user_id' => $userId,
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'price' => $price,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ], $photoFields));
    }

    public function setStatus(int $listingId, string $status): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('classifieds_listings') . ' SET status = :status, updated_at = :now WHERE id = :id',
            ['status' => $status, 'now' => date('Y-m-d H:i:s'), 'id' => $listingId]
        );
    }

    public function softDeleteListing(int $id): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('classifieds_listings') . ' SET deleted_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    /** @param array<string, mixed> $listing */
    public function absolutePath(array $listing): string
    {
        return "{$this->storageDir}/{$listing['filename']}";
    }

    /** @param array<string, mixed> $listing */
    public function absoluteThumbnailPath(array $listing): string
    {
        return "{$this->storageDir}/{$listing['thumbnail_filename']}";
    }

    /**
     * @param array{tmpPath: string, originalName: string, mimeType: string, extension: string, size: int} $validated
     * @return array{filename: string, thumbnail_filename: string, mime_type: string, size: int}
     */
    private function storePhoto(array $validated): array
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

        $thumbnailName = "{$baseName}_thumb.jpg";
        $this->thumbnailer->make($absolutePath, $validated['mimeType'], "{$targetDir}/{$thumbnailName}", self::THUMBNAIL_WIDTH);

        return [
            'filename' => "{$subdir}/{$storedName}",
            'thumbnail_filename' => "{$subdir}/{$thumbnailName}",
            'mime_type' => $validated['mimeType'],
            'size' => $validated['size'],
        ];
    }

    private function uniqueSlug(string $value): string
    {
        $base = Slug::make($value, 'category');
        $slug = $base;
        $suffix = 2;

        while ($this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('classifieds_categories') . ' WHERE slug = :slug',
            ['slug' => $slug]
        ) !== null) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
