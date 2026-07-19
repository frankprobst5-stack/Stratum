<?php

declare(strict_types=1);

namespace Stratum\Modules\Pages;

use Stratum\Core\Database;
use Stratum\Core\Slug;

final class PageService
{
    private readonly HtmlSanitizer $sanitizer;

    public function __construct(private readonly Database $db)
    {
        $this->sanitizer = new HtmlSanitizer();
    }

    /** @return array<string, mixed>|null */
    public function findPublishedBySlug(string $slug): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('pages') . '
             WHERE slug = :slug AND is_published = 1 AND deleted_at IS NULL',
            ['slug' => $slug]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function listAll(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('pages') . ' WHERE deleted_at IS NULL ORDER BY title'
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('pages') . ' WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): string
    {
        $now = date('Y-m-d H:i:s');

        return $this->db->insert('pages', [
            'slug' => $this->uniqueSlug((string) $data['title']),
            'title' => $data['title'],
            'body' => $this->sanitizer->sanitize((string) $data['body']),
            'is_published' => !empty($data['is_published']) ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('pages') . '
             SET title = :title, body = :body, is_published = :is_published, updated_at = :now
             WHERE id = :id',
            [
                'title' => $data['title'],
                'body' => $this->sanitizer->sanitize((string) $data['body']),
                'is_published' => !empty($data['is_published']) ? 1 : 0,
                'now' => date('Y-m-d H:i:s'),
                'id' => $id,
            ]
        );
    }

    public function softDelete(int $id): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('pages') . ' SET deleted_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    private function uniqueSlug(string $title): string
    {
        $base = Slug::make($title, 'page');
        $slug = $base;
        $suffix = 2;

        while ($this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('pages') . ' WHERE slug = :slug',
            ['slug' => $slug]
        ) !== null) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
