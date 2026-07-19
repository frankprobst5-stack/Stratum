<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Admin-to-admin scratchpad — see the `admin_notes` migration's own
 * docblock for how this differs from MemberNoteService. Append-only,
 * add or delete, no edit — same reasoning MemberNoteService already
 * documented for the identical shape.
 */
final class AdminNoteService
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int, array{id: int, body: string, author_id: ?int, created_at: string}> */
    public function listRecent(int $limit = 10): array
    {
        return $this->db->fetchAll(
            'SELECT id, body, author_id, created_at FROM ' . $this->db->table('admin_notes') . '
             ORDER BY created_at DESC, id DESC LIMIT ' . max(1, $limit)
        );
    }

    public function add(?int $authorId, string $body): void
    {
        $this->db->insert('admin_notes', [
            'author_id' => $authorId,
            'body' => $body,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function delete(int $noteId): void
    {
        $this->db->execute(
            'DELETE FROM ' . $this->db->table('admin_notes') . ' WHERE id = :id',
            ['id' => $noteId]
        );
    }
}
