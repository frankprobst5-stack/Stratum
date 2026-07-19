<?php

declare(strict_types=1);

namespace Stratum\Modules\Wiki;

use Stratum\Core\Database;
use Stratum\Core\Slug;

final class WikiService
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int, array{id: int, name: string, slug: string}> */
    public function listCategories(): array
    {
        $rows = $this->db->fetchAll('SELECT id, name, slug FROM ' . $this->db->table('wiki_categories') . ' ORDER BY name');

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'slug' => $r['slug'],
        ], $rows);
    }

    public function createCategory(string $name): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->insert('wiki_categories', [
            'name' => $name,
            'slug' => $this->uniqueSlug('wiki_categories', $name, 'category'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function listPages(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('wiki_pages') . ' WHERE deleted_at IS NULL ORDER BY title'
        );
    }

    /** @return array<string, mixed>|null */
    public function findPageBySlug(string $slug): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('wiki_pages') . ' WHERE slug = :slug AND deleted_at IS NULL',
            ['slug' => $slug]
        );
    }

    /** @return array<string, mixed>|null */
    public function findPage(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('wiki_pages') . ' WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    /** @return array{pageId: int, revisionId: int} */
    public function createPage(?int $categoryId, int $authorId, string $title, string $body): array
    {
        $now = date('Y-m-d H:i:s');

        $pageId = (int) $this->db->insert('wiki_pages', [
            'category_id' => $categoryId,
            'slug' => $this->uniqueSlug('wiki_pages', $title, 'page'),
            'title' => $title,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $revisionId = $this->insertRevision($pageId, $authorId, $body, 'Initial version');

        return ['pageId' => $pageId, 'revisionId' => $revisionId];
    }

    public function addRevision(int $pageId, int $authorId, string $body, string $comment): int
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('wiki_pages') . ' SET updated_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $pageId]
        );

        return $this->insertRevision($pageId, $authorId, $body, $comment !== '' ? $comment : null);
    }

    public function restoreRevision(int $pageId, int $authorId, int $revisionId): int
    {
        $old = $this->findRevision($revisionId);
        if ($old === null || (int) $old['page_id'] !== $pageId) {
            throw new \RuntimeException('Cannot restore a revision that does not belong to this page.');
        }

        return $this->addRevision($pageId, $authorId, $old['body'], "Restored from revision #{$revisionId}");
    }

    /** @return array<string, mixed>|null */
    public function currentRevision(int $pageId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('wiki_revisions') . '
             WHERE page_id = :page_id ORDER BY created_at DESC, id DESC LIMIT 1',
            ['page_id' => $pageId]
        );
    }

    /** @return array<int, array<string, mixed>> newest first */
    public function listRevisions(int $pageId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('wiki_revisions') . '
             WHERE page_id = :page_id ORDER BY created_at DESC, id DESC',
            ['page_id' => $pageId]
        );
    }

    /** @return array<string, mixed>|null */
    public function findRevision(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('wiki_revisions') . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    public function softDeletePage(int $id): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('wiki_pages') . ' SET deleted_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    private function insertRevision(int $pageId, int $authorId, string $body, ?string $comment): int
    {
        return (int) $this->db->insert('wiki_revisions', [
            'page_id' => $pageId,
            'author_id' => $authorId,
            'body' => $body,
            'comment' => $comment,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
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
