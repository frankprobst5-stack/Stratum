<?php

declare(strict_types=1);

namespace Stratum\Modules\Users;

use Stratum\Core\Database;

/** One-directional, unconfirmed — same toggle shape ForumService::toggleLike()/GalleryService::toggleLike() already use. */
final class FollowService
{
    public function __construct(private readonly Database $db)
    {
    }

    public function isFollowing(int $followerId, int $followedId): bool
    {
        return $this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('member_follows') . '
             WHERE follower_id = :follower AND followed_id = :followed',
            ['follower' => $followerId, 'followed' => $followedId]
        ) !== null;
    }

    public function toggle(int $followerId, int $followedId): void
    {
        if ($followerId === $followedId) {
            return;
        }

        $existing = $this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('member_follows') . '
             WHERE follower_id = :follower AND followed_id = :followed',
            ['follower' => $followerId, 'followed' => $followedId]
        );

        if ($existing !== null) {
            $this->db->execute('DELETE FROM ' . $this->db->table('member_follows') . ' WHERE id = :id', ['id' => $existing['id']]);

            return;
        }

        $this->db->insert('member_follows', [
            'follower_id' => $followerId,
            'followed_id' => $followedId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function followerCount(int $userId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS c FROM ' . $this->db->table('member_follows') . ' WHERE followed_id = :id',
            ['id' => $userId]
        );

        return (int) ($row['c'] ?? 0);
    }

    public function followingCount(int $userId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS c FROM ' . $this->db->table('member_follows') . ' WHERE follower_id = :id',
            ['id' => $userId]
        );

        return (int) ($row['c'] ?? 0);
    }

    /** @return array<int, array<string, mixed>> users this person follows */
    public function listFollowing(int $userId): array
    {
        $follows = $this->db->table('member_follows');
        $users = $this->db->table('users');

        return $this->db->fetchAll(
            "SELECT u.* FROM {$follows} f
             INNER JOIN {$users} u ON u.id = f.followed_id
             WHERE f.follower_id = :id AND u.deleted_at IS NULL
             ORDER BY u.username",
            ['id' => $userId]
        );
    }

    /** @return array<int, array<string, mixed>> users following this person */
    public function listFollowers(int $userId): array
    {
        $follows = $this->db->table('member_follows');
        $users = $this->db->table('users');

        return $this->db->fetchAll(
            "SELECT u.* FROM {$follows} f
             INNER JOIN {$users} u ON u.id = f.follower_id
             WHERE f.followed_id = :id AND u.deleted_at IS NULL
             ORDER BY u.username",
            ['id' => $userId]
        );
    }
}
