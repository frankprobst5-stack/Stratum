<?php

declare(strict_types=1);

namespace Stratum\Modules\Bookmarks;

use Stratum\Core\ContentResolver;
use Stratum\Core\Database;

/**
 * Save-for-later across any content type ContentResolver knows about — the
 * same commentable_type/commentable_id shape `comments` established. Toggle
 * semantics matching gallery_likes/forum_post_likes: no capability required
 * beyond being logged in, since bookmarking is a private, non-content-
 * creating action with no moderation surface.
 */
final class BookmarkService
{
    /** @var array<int, string> types with a bookmark button wired up in v1 */
    private const BOOKMARKABLE_TYPES = ['article', 'wiki_page', 'forum_topic'];

    private readonly ContentResolver $resolver;

    public function __construct(private readonly Database $db)
    {
        $this->resolver = new ContentResolver($db);
    }

    public function isBookmarkable(string $type): bool
    {
        return in_array($type, self::BOOKMARKABLE_TYPES, true);
    }

    /** Toggles a bookmark and returns the new state (true = now bookmarked). Caller is responsible for confirming the target still exists first. */
    public function toggle(string $type, int $id, int $userId): bool
    {
        $table = $this->db->table('bookmarks');
        $existing = $this->db->fetchOne(
            "SELECT id FROM {$table} WHERE bookmarkable_type = :type AND bookmarkable_id = :id AND user_id = :user_id",
            ['type' => $type, 'id' => $id, 'user_id' => $userId]
        );

        if ($existing !== null) {
            $this->db->execute("DELETE FROM {$table} WHERE id = :id", ['id' => $existing['id']]);

            return false;
        }

        $this->db->insert('bookmarks', [
            'bookmarkable_type' => $type,
            'bookmarkable_id' => $id,
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    public function isBookmarked(string $type, int $id, int $userId): bool
    {
        return $this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('bookmarks') . '
             WHERE bookmarkable_type = :type AND bookmarkable_id = :id AND user_id = :user_id',
            ['type' => $type, 'id' => $id, 'user_id' => $userId]
        ) !== null;
    }

    /**
     * Resolves each of the user's bookmarks live (title/URL as they are
     * right now, not as they were when saved) and silently drops any whose
     * content no longer resolves — deleted/unpublished content just
     * vanishes from the list rather than showing a stale, broken entry.
     *
     * @return array<int, array{bookmark_id: int, type: string, title: string, url: string, created_at: string}>
     */
    public function listForUser(int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT id, bookmarkable_type, bookmarkable_id, created_at FROM ' . $this->db->table('bookmarks') . '
             WHERE user_id = :user_id ORDER BY created_at DESC',
            ['user_id' => $userId]
        );

        $results = [];
        foreach ($rows as $row) {
            $target = $this->resolver->resolve($row['bookmarkable_type'], (int) $row['bookmarkable_id']);
            if ($target === null) {
                continue;
            }

            $results[] = [
                'bookmark_id' => (int) $row['id'],
                'type' => $row['bookmarkable_type'],
                'title' => $target['title'],
                'url' => $target['url'],
                'created_at' => $row['created_at'],
            ];
        }

        return $results;
    }
}
