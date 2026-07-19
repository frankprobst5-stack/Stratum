<?php

declare(strict_types=1);

namespace Stratum\Modules\Links;

use Stratum\Core\Database;
use Stratum\Core\Slug;

final class LinkService
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int, array{id: int, name: string, slug: string}> */
    public function listCategories(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT id, name, slug FROM ' . $this->db->table('link_categories') . ' ORDER BY weight, name'
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
        $this->db->insert('link_categories', [
            'name' => $name,
            'slug' => $this->uniqueSlug($name, 'category'),
            'weight' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function listLinks(?int $categoryId = null): array
    {
        $table = $this->db->table('links');
        $where = 'WHERE deleted_at IS NULL';
        $params = [];

        if ($categoryId !== null) {
            $where .= ' AND category_id = :category_id';
            $params['category_id'] = $categoryId;
        }

        return $this->db->fetchAll(
            "SELECT * FROM {$table} {$where} ORDER BY title",
            $params
        );
    }

    /** @return array<string, mixed>|null */
    public function findLink(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('links') . ' WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    public function createLink(int $categoryId, ?int $submittedBy, string $title, string $url, string $description): int
    {
        $now = date('Y-m-d H:i:s');

        return (int) $this->db->insert('links', [
            'category_id' => $categoryId,
            'submitted_by' => $submittedBy,
            'title' => $title,
            'url' => $url,
            'description' => $description !== '' ? $description : null,
            'click_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function incrementClickCount(int $id): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('links') . ' SET click_count = click_count + 1 WHERE id = :id',
            ['id' => $id]
        );
    }

    public function softDeleteLink(int $id): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('links') . ' SET deleted_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    private function uniqueSlug(string $value, string $fallback): string
    {
        $base = Slug::make($value, $fallback);
        $slug = $base;
        $suffix = 2;

        while ($this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('link_categories') . ' WHERE slug = :slug',
            ['slug' => $slug]
        ) !== null) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
