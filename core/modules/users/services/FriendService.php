<?php

declare(strict_types=1);

namespace Stratum\Modules\Users;

use Stratum\Core\Database;

final class FriendService
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Sending a request when the other person already has a pending
     * request out to you is treated as an instant mutual accept, rather
     * than leaving two crossed pending requests sitting there — the same
     * "you both already said yes" resolution most friend-request systems
     * apply. Returns a result specific enough for the caller to know
     * whether a notification is actually warranted — 'auto_accepted' is
     * distinct from 'already_friends' precisely so the caller doesn't
     * send a spurious "your request was accepted" notification to
     * someone you were already friends with before this call.
     *
     * @return 'pending'|'auto_accepted'|'already_friends'|'already_pending'|'self'
     */
    public function sendRequest(int $senderId, int $recipientId): string
    {
        // The template never renders an Add Friend button on your own
        // profile, but that alone doesn't stop a direct crafted POST —
        // same defense-in-depth FollowService::toggle() already applies
        // to its own self-follow case.
        if ($senderId === $recipientId) {
            return 'self';
        }

        $reverse = $this->db->fetchOne(
            'SELECT id, status FROM ' . $this->db->table('friend_requests') . '
             WHERE sender_id = :recipient AND recipient_id = :sender',
            ['recipient' => $recipientId, 'sender' => $senderId]
        );

        if ($reverse !== null) {
            if ($reverse['status'] === 'accepted') {
                return 'already_friends';
            }

            $this->setStatus((int) $reverse['id'], 'accepted');

            return 'auto_accepted';
        }

        $existing = $this->db->fetchOne(
            'SELECT status FROM ' . $this->db->table('friend_requests') . '
             WHERE sender_id = :sender AND recipient_id = :recipient',
            ['sender' => $senderId, 'recipient' => $recipientId]
        );

        if ($existing !== null) {
            return $existing['status'] === 'accepted' ? 'already_friends' : 'already_pending';
        }

        $now = date('Y-m-d H:i:s');
        $this->db->insert('friend_requests', [
            'sender_id' => $senderId,
            'recipient_id' => $recipientId,
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return 'pending';
    }

    /** True if a pending request from $senderId to $recipientId existed and was accepted. */
    public function accept(int $recipientId, int $senderId): bool
    {
        $request = $this->findPending($senderId, $recipientId);
        if ($request === null) {
            return false;
        }

        $this->setStatus((int) $request['id'], 'accepted');

        return true;
    }

    /** Declining and unfriending are the same operation — delete the row — so a declined request doesn't permanently block a future one via the unique-pair constraint. */
    public function decline(int $recipientId, int $senderId): bool
    {
        $request = $this->findPending($senderId, $recipientId);
        if ($request === null) {
            return false;
        }

        $this->db->execute('DELETE FROM ' . $this->db->table('friend_requests') . ' WHERE id = :id', ['id' => $request['id']]);

        return true;
    }

    /**
     * Removes an accepted friendship in either direction. Each of the
     * two ids appears twice in the SQL (once per direction of the OR),
     * so each occurrence gets its own uniquely-named placeholder — this
     * codebase's PDO layer rejects a named placeholder reused twice in
     * one query, the same reason SearchService::bindLike() and
     * ForumService::bindIdList() exist.
     */
    public function removeFriend(int $userId, int $otherUserId): void
    {
        $this->db->execute(
            'DELETE FROM ' . $this->db->table('friend_requests') . "
             WHERE status = 'accepted' AND (
                (sender_id = :a1 AND recipient_id = :b1) OR (sender_id = :b2 AND recipient_id = :a2)
             )",
            ['a1' => $userId, 'b1' => $otherUserId, 'b2' => $userId, 'a2' => $otherUserId]
        );
    }

    /**
     * @return 'self'|'friends'|'request_sent'|'request_received'|'none'
     */
    public function relationshipStatus(int $viewerId, int $targetId): string
    {
        if ($viewerId === $targetId) {
            return 'self';
        }

        $row = $this->db->fetchOne(
            'SELECT sender_id, status FROM ' . $this->db->table('friend_requests') . '
             WHERE (sender_id = :a1 AND recipient_id = :b1) OR (sender_id = :b2 AND recipient_id = :a2)',
            ['a1' => $viewerId, 'b1' => $targetId, 'b2' => $viewerId, 'a2' => $targetId]
        );

        if ($row === null) {
            return 'none';
        }

        if ($row['status'] === 'accepted') {
            return 'friends';
        }

        return (int) $row['sender_id'] === $viewerId ? 'request_sent' : 'request_received';
    }

    /** @return array<int, array<string, mixed>> accepted friends, each a users row */
    public function listFriends(int $userId): array
    {
        $requests = $this->db->table('friend_requests');
        $users = $this->db->table('users');

        return $this->db->fetchAll(
            "SELECT u.* FROM {$requests} fr
             INNER JOIN {$users} u ON u.id = (CASE WHEN fr.sender_id = :user_id1 THEN fr.recipient_id ELSE fr.sender_id END)
             WHERE fr.status = 'accepted' AND (fr.sender_id = :user_id2 OR fr.recipient_id = :user_id3)
             AND u.deleted_at IS NULL
             ORDER BY u.username",
            ['user_id1' => $userId, 'user_id2' => $userId, 'user_id3' => $userId]
        );
    }

    /** @return array<int, array<string, mixed>> incoming pending requests, each a users row (the sender) */
    public function listIncomingRequests(int $userId): array
    {
        $requests = $this->db->table('friend_requests');
        $users = $this->db->table('users');

        return $this->db->fetchAll(
            "SELECT u.* FROM {$requests} fr
             INNER JOIN {$users} u ON u.id = fr.sender_id
             WHERE fr.status = 'pending' AND fr.recipient_id = :user_id AND u.deleted_at IS NULL
             ORDER BY fr.created_at DESC",
            ['user_id' => $userId]
        );
    }

    /** @return array<int, array<string, mixed>> outgoing pending requests, each a users row (the recipient) */
    public function listOutgoingRequests(int $userId): array
    {
        $requests = $this->db->table('friend_requests');
        $users = $this->db->table('users');

        return $this->db->fetchAll(
            "SELECT u.* FROM {$requests} fr
             INNER JOIN {$users} u ON u.id = fr.recipient_id
             WHERE fr.status = 'pending' AND fr.sender_id = :user_id AND u.deleted_at IS NULL
             ORDER BY fr.created_at DESC",
            ['user_id' => $userId]
        );
    }

    public function friendCount(int $userId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS c FROM ' . $this->db->table('friend_requests') . "
             WHERE status = 'accepted' AND (sender_id = :user_id1 OR recipient_id = :user_id2)",
            ['user_id1' => $userId, 'user_id2' => $userId]
        );

        return (int) ($row['c'] ?? 0);
    }

    /** @return array<string, mixed>|null */
    private function findPending(int $senderId, int $recipientId): ?array
    {
        return $this->db->fetchOne(
            "SELECT id FROM " . $this->db->table('friend_requests') . "
             WHERE sender_id = :sender AND recipient_id = :recipient AND status = 'pending'",
            ['sender' => $senderId, 'recipient' => $recipientId]
        );
    }

    private function setStatus(int $requestId, string $status): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('friend_requests') . ' SET status = :status, updated_at = :now WHERE id = :id',
            ['status' => $status, 'now' => date('Y-m-d H:i:s'), 'id' => $requestId]
        );
    }
}
