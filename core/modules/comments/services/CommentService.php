<?php

declare(strict_types=1);

namespace Stratum\Modules\Comments;

use Stratum\Core\Database;

final class CommentService
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function listFor(string $commentableType, int $commentableId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('comments') . '
             WHERE commentable_type = :type AND commentable_id = :id AND deleted_at IS NULL
             ORDER BY created_at ASC',
            ['type' => $commentableType, 'id' => $commentableId]
        );
    }

    public function countFor(string $commentableType, int $commentableId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total FROM ' . $this->db->table('comments') . '
             WHERE commentable_type = :type AND commentable_id = :id AND deleted_at IS NULL',
            ['type' => $commentableType, 'id' => $commentableId]
        );

        return (int) ($row['total'] ?? 0);
    }

    /**
     * Raw recent comments across every content type — no other method here
     * queries across types (listFor()/countFor() are both scoped to one
     * piece of content). Deliberately returns more rows than the caller's
     * eventual display limit: the block resolving these via ContentResolver
     * will drop any row whose type it can't resolve (gallery/video/
     * downloads aren't wired into ContentResolver yet — see its own
     * docblock), so over-fetching here means a real caller still gets a
     * full page of resolvable comments instead of a mysteriously short one.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listRecent(int $limit): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('comments') . '
             WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT ' . max(1, $limit)
        );
    }

    public function create(string $commentableType, int $commentableId, int $userId, string $body): void
    {
        $now = date('Y-m-d H:i:s');

        $this->db->insert('comments', [
            'commentable_type' => $commentableType,
            'commentable_id' => $commentableId,
            'user_id' => $userId,
            'body' => $body,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
