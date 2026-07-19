<?php

declare(strict_types=1);

namespace Stratum\Modules\OrgSpaces;

use Stratum\Core\Database;

/**
 * A per-org private forum — flat topics/posts, no boards or categories
 * (an org space is one small community, not a multi-board site forum).
 * Content is never public; every read is gated by the controller on org
 * membership via OrgSpaceService::isMember(), the same check the roster
 * already uses. Any member can post/reply — no separate create_topic/
 * reply capability split like the public forum's, since spam control
 * across a small, admin-curated roster isn't the same problem a public
 * signup forum has. Moderation (lock/delete) is officer/admin-only via
 * the existing org_spaces.moderate capability.
 */
final class OrgForumService
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int, array<string, mixed>> topics with computed reply_count/last_post_at, newest activity first */
    public function listTopics(int $orgId): array
    {
        $topics = $this->db->table('org_spaces_forum_topics');
        $posts = $this->db->table('org_spaces_forum_posts');

        return $this->db->fetchAll("
            SELECT
                t.*,
                (SELECT COUNT(*) FROM {$posts} p WHERE p.topic_id = t.id AND p.deleted_at IS NULL) - 1 AS reply_count,
                (SELECT MAX(p.created_at) FROM {$posts} p WHERE p.topic_id = t.id AND p.deleted_at IS NULL) AS last_post_at
            FROM {$topics} t
            WHERE t.org_id = :org_id AND t.deleted_at IS NULL
            ORDER BY last_post_at DESC
        ", ['org_id' => $orgId]);
    }

    /** @return array<string, mixed>|null */
    public function findTopic(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('org_spaces_forum_topics') . ' WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    /** @return array{topicId: int, postId: int} */
    public function createTopicWithFirstPost(int $orgId, int $authorId, string $title, string $body): array
    {
        $now = date('Y-m-d H:i:s');

        $topicId = (int) $this->db->insert('org_spaces_forum_topics', [
            'org_id' => $orgId,
            'author_id' => $authorId,
            'title' => $title,
            'is_locked' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $postId = (int) $this->db->insert('org_spaces_forum_posts', [
            'topic_id' => $topicId,
            'author_id' => $authorId,
            'body' => $body,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['topicId' => $topicId, 'postId' => $postId];
    }

    /** @return array<int, array<string, mixed>> */
    public function listPosts(int $topicId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('org_spaces_forum_posts') . '
             WHERE topic_id = :topic_id AND deleted_at IS NULL ORDER BY created_at ASC',
            ['topic_id' => $topicId]
        );
    }

    /** @return array<string, mixed>|null */
    public function findPost(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('org_spaces_forum_posts') . ' WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    public function reply(int $topicId, int $authorId, string $body): int
    {
        $now = date('Y-m-d H:i:s');

        return (int) $this->db->insert('org_spaces_forum_posts', [
            'topic_id' => $topicId,
            'author_id' => $authorId,
            'body' => $body,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function setLocked(int $topicId, bool $locked): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('org_spaces_forum_topics') . ' SET is_locked = :v, updated_at = :now WHERE id = :id',
            ['v' => $locked ? 1 : 0, 'now' => date('Y-m-d H:i:s'), 'id' => $topicId]
        );
    }

    public function softDeleteTopic(int $id): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('org_spaces_forum_topics') . ' SET deleted_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    public function softDeletePost(int $id): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('org_spaces_forum_posts') . ' SET deleted_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }
}
