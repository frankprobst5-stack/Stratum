<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Append-only staff notes on a member's account — add or delete, no edit.
 * A note is a point-in-time observation ("called about dues 2026-07-17,
 * said check is in the mail"), not a document that needs revision
 * history; if a note is genuinely wrong, deleting it and adding a
 * corrected one is clearer than silently rewriting what staff said.
 */
final class MemberNoteService
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int, array{id: int, body: string, author_id: int, created_at: string}> newest first */
    public function listFor(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT id, body, author_id, created_at FROM ' . $this->db->table('member_notes') . '
             WHERE user_id = :user_id ORDER BY created_at DESC, id DESC',
            ['user_id' => $userId]
        );
    }

    public function add(int $userId, int $authorId, string $body): void
    {
        $this->db->insert('member_notes', [
            'user_id' => $userId,
            'author_id' => $authorId,
            'body' => $body,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** True if a note existed for this user and was deleted. */
    public function delete(int $userId, int $noteId): bool
    {
        return $this->db->execute(
            'DELETE FROM ' . $this->db->table('member_notes') . ' WHERE id = :id AND user_id = :user_id',
            ['id' => $noteId, 'user_id' => $userId]
        ) > 0;
    }
}
