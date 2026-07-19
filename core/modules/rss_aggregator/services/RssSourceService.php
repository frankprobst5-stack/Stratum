<?php

declare(strict_types=1);

namespace Stratum\Modules\RssAggregator;

use Stratum\Core\Database;

final class RssSourceService
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function listSources(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('rss_sources') . ' ORDER BY name ASC'
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('rss_sources') . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    /** @return bool true if created, false if the URL's scheme was rejected */
    public function createSource(string $name, string $feedUrl): bool
    {
        $scheme = strtolower((string) parse_url($feedUrl, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->insert('rss_sources', [
            'name' => $name,
            'feed_url' => $feedUrl,
            'is_enabled' => 1,
            'last_fetched_at' => null,
            'last_fetch_error' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return true;
    }

    /**
     * $authorId is captured once here rather than resolved live when an
     * item is later auto-published — fetchAndStore() can run from
     * cron.daily with no logged-in admin in scope, so "whoever's logged
     * in right now" isn't available at publish time. Turning auto-publish
     * off doesn't need an author; turning it on does.
     */
    public function setAutoPublish(int $id, bool $enabled, ?int $authorId): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('rss_sources') . '
             SET auto_publish = :auto_publish, article_author_id = :author_id, updated_at = :now
             WHERE id = :id',
            [
                'auto_publish' => $enabled ? 1 : 0,
                'author_id' => $enabled ? $authorId : null,
                'now' => date('Y-m-d H:i:s'),
                'id' => $id,
            ]
        );
    }

    public function toggleEnabled(int $id): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('rss_sources') . '
             SET is_enabled = 1 - is_enabled, updated_at = :now
             WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    public function deleteSource(int $id): void
    {
        $this->db->execute(
            'DELETE FROM ' . $this->db->table('rss_sources') . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    /** @return array<int, array<string, mixed>> items from enabled sources, newest first, with source name joined in */
    public function listRecentItems(int $limit = 100): array
    {
        $itemsTable = $this->db->table('rss_items');
        $sourcesTable = $this->db->table('rss_sources');

        return $this->db->fetchAll(
            "SELECT i.*, s.name AS source_name
             FROM {$itemsTable} i
             JOIN {$sourcesTable} s ON s.id = i.source_id
             WHERE s.is_enabled = 1
             ORDER BY i.published_at DESC, i.created_at DESC
             LIMIT " . max(1, $limit)
        );
    }
}
