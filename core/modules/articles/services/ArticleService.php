<?php

declare(strict_types=1);

namespace Stratum\Modules\Articles;

use Stratum\Core\Database;
use Stratum\Core\Slug;

final class ArticleService
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * `is_published = 1` alone isn't sufficient once scheduling exists:
     * cron.daily only runs once a day (see docs/roadmap.md's cron
     * infrastructure entry), so an article scheduled for 9am could stay
     * flagged unpublished for up to ~18 hours after its time actually
     * passed if that flag were the only check. The OR clause makes public
     * visibility accurate to the moment `published_at` passes regardless
     * of whether cron has caught up yet — the flag becomes a housekeeping
     * convenience (fast admin-list filtering, a consistent-looking DB
     * state) rather than the authoritative gate. Both branches compare
     * against MySQL's own NOW(), never a PHP-computed date — the exact
     * mismatch that caused a real bug during the Site Search build.
     */
    private const PUBLISHED_CONDITION = "(is_published = 1 OR (published_at IS NOT NULL AND published_at <= NOW()))";

    /** @return array<int, array<string, mixed>> */
    public function listPublished(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('articles') . '
             WHERE ' . self::PUBLISHED_CONDITION . ' AND deleted_at IS NULL
             ORDER BY published_at DESC'
        );
    }

    /**
     * Recent published articles, optionally scoped to one category — the
     * backing query for the "Latest Content" block (hero slider + compact
     * list placements alike, just different `$limit`/display config).
     * Recent-only, no popularity/comment-count sort — that would need a
     * batch join against `comments` that doesn't exist yet; deferred until
     * a real caller needs it, same "add it when the second consumer needs
     * it" discipline the rest of this codebase follows.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listPublishedByCategory(?int $categoryId, int $limit): array
    {
        $table = $this->db->table('articles');
        $params = [];
        $where = self::PUBLISHED_CONDITION . ' AND deleted_at IS NULL';

        if ($categoryId !== null) {
            $where .= ' AND category_id = :category_id';
            $params['category_id'] = $categoryId;
        }

        return $this->db->fetchAll(
            "SELECT * FROM {$table} WHERE {$where} ORDER BY published_at DESC LIMIT " . max(1, $limit),
            $params
        );
    }

    /** @return array<string, mixed>|null */
    public function findPublishedBySlug(string $slug): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('articles') . '
             WHERE slug = :slug AND ' . self::PUBLISHED_CONDITION . ' AND deleted_at IS NULL',
            ['slug' => $slug]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function listAll(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('articles') . '
             WHERE deleted_at IS NULL ORDER BY created_at DESC'
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('articles') . ' WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    /**
     * Creates the article together with its first revision in one step —
     * same "container + first content together" shape
     * `createTopicWithFirstPost()`/wiki's `createPage()` already use.
     *
     * @param array<string, mixed> $data 'publish_action' is 'draft'|'now'|'schedule';
     *     'scheduled_at' (datetime-local format) is only read when 'schedule'.
     * @return array{articleId: int, revisionId: int}
     */
    public function create(array $data): array
    {
        $now = date('Y-m-d H:i:s');
        [$isPublished, $publishedAt] = $this->resolvePublishState($data, null);

        $articleId = (int) $this->db->insert('articles', [
            'category_id' => $data['category_id'] ?: null,
            'author_id' => $data['author_id'],
            'title' => $data['title'],
            'slug' => $this->uniqueSlug((string) $data['title']),
            'excerpt' => $data['excerpt'] !== '' ? $data['excerpt'] : null,
            'featured_image_url' => !empty($data['featured_image_url']) ? $data['featured_image_url'] : null,
            'is_published' => $isPublished ? 1 : 0,
            'published_at' => $publishedAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $revisionId = $this->insertRevision($articleId, (int) $data['author_id'], (string) $data['body'], 'Initial version');

        return ['articleId' => $articleId, 'revisionId' => $revisionId];
    }

    /**
     * Updates the article's metadata (title/category/excerpt/publish
     * state); the body is never touched here — see addRevision(), a
     * separate call, same split wiki's update()/addRevision() already has.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $existing = $this->find($id);
        if ($existing === null) {
            return;
        }

        [$isPublished, $publishedAt] = $this->resolvePublishState($data, $existing['published_at']);

        $this->db->execute(
            'UPDATE ' . $this->db->table('articles') . '
             SET category_id = :category_id, title = :title, excerpt = :excerpt,
                 featured_image_url = :featured_image_url,
                 is_published = :is_published, published_at = :published_at, updated_at = :now
             WHERE id = :id',
            [
                'category_id' => $data['category_id'] ?: null,
                'title' => $data['title'],
                'excerpt' => $data['excerpt'] !== '' ? $data['excerpt'] : null,
                'featured_image_url' => !empty($data['featured_image_url']) ? $data['featured_image_url'] : null,
                'is_published' => $isPublished ? 1 : 0,
                'published_at' => $publishedAt,
                'now' => date('Y-m-d H:i:s'),
                'id' => $id,
            ]
        );
    }

    public function addRevision(int $articleId, int $authorId, string $body, string $comment): int
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('articles') . ' SET updated_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $articleId]
        );

        return $this->insertRevision($articleId, $authorId, $body, $comment !== '' ? $comment : null);
    }

    public function restoreRevision(int $articleId, int $authorId, int $revisionId): int
    {
        $old = $this->findRevision($revisionId);
        if ($old === null || (int) $old['article_id'] !== $articleId) {
            throw new \RuntimeException('Cannot restore a revision that does not belong to this article.');
        }

        return $this->addRevision($articleId, $authorId, $old['body'], "Restored from revision #{$revisionId}");
    }

    /** @return array<string, mixed>|null */
    public function currentRevision(int $articleId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('articles_revisions') . '
             WHERE article_id = :article_id ORDER BY created_at DESC, id DESC LIMIT 1',
            ['article_id' => $articleId]
        );
    }

    /** @return array<int, array<string, mixed>> newest first */
    public function listRevisions(int $articleId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('articles_revisions') . '
             WHERE article_id = :article_id ORDER BY created_at DESC, id DESC',
            ['article_id' => $articleId]
        );
    }

    /** @return array<string, mixed>|null */
    public function findRevision(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('articles_revisions') . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    private function insertRevision(int $articleId, int $authorId, string $body, ?string $comment): int
    {
        return (int) $this->db->insert('articles_revisions', [
            'article_id' => $articleId,
            'author_id' => $authorId,
            'body' => $body,
            'comment' => $comment,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Three explicit choices rather than inferring intent from a bare
     * datetime — 'draft' (no published_at at all), 'now' (published
     * immediately), 'schedule' (published_at set to a future moment,
     * is_published stays 0 until cron.daily's publishDueScheduled() flips
     * it). A "scheduled" time that's actually in the past is treated as
     * "now" rather than rejected — the admin's intent was clearly to
     * publish, not to fail validation over a moot timestamp.
     *
     * @param array<string, mixed> $data
     * @return array{0: bool, 1: ?string}
     */
    private function resolvePublishState(array $data, ?string $existingPublishedAt): array
    {
        $action = $data['publish_action'] ?? 'draft';
        $now = date('Y-m-d H:i:s');

        if ($action === 'now') {
            return [true, $existingPublishedAt ?? $now];
        }

        if ($action === 'schedule' && !empty($data['scheduled_at'])) {
            $scheduledAt = (new \DateTimeImmutable((string) $data['scheduled_at']))->format('Y-m-d H:i:s');

            return $scheduledAt <= $now ? [true, $scheduledAt] : [false, $scheduledAt];
        }

        return [false, null];
    }

    /**
     * Flips any article whose scheduled time has arrived — called from
     * cron.daily, same "compute the due-ness entirely in MySQL" approach
     * that avoided the PHP-vs-MySQL timezone mismatch bug Site Search hit
     * (see docs/roadmap.md's Site Search entry): a single UPDATE comparing
     * published_at against MySQL's own NOW(), never a PHP-computed date.
     *
     * @return int number of articles published by this run
     */
    public function publishDueScheduled(): int
    {
        return $this->db->execute(
            'UPDATE ' . $this->db->table('articles') . '
             SET is_published = 1, updated_at = NOW()
             WHERE is_published = 0 AND published_at IS NOT NULL AND published_at <= NOW() AND deleted_at IS NULL'
        );
    }

    public function softDelete(int $id): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('articles') . ' SET deleted_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    /** @return array<int, array{id: int, name: string, slug: string}> */
    public function listCategories(): array
    {
        $rows = $this->db->fetchAll('SELECT id, name, slug FROM ' . $this->db->table('articles_categories') . ' ORDER BY name');

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'slug' => $r['slug'],
        ], $rows);
    }

    public function createCategory(string $name): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->insert('articles_categories', [
            'name' => $name,
            'slug' => $this->uniqueCategorySlug($name),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Slug::make($title);
        $slug = $base;
        $suffix = 2;

        while ($this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('articles') . ' WHERE slug = :slug',
            ['slug' => $slug]
        ) !== null) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function uniqueCategorySlug(string $name): string
    {
        $base = Slug::make($name);
        $slug = $base;
        $suffix = 2;

        while ($this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('articles_categories') . ' WHERE slug = :slug',
            ['slug' => $slug]
        ) !== null) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
