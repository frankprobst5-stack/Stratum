<?php

declare(strict_types=1);

namespace Stratum\Modules\Chat;

use Stratum\Core\Database;

/**
 * Room/membership/message CRUD for the Stage 9 chat build. See the
 * migration's docblock for the "why" behind the schema shape — this
 * class is where the two confirmed lifecycle rules actually live:
 * user-created rooms are always public and self-delete the instant
 * their last member leaves (leaveRoom(), not a scheduled sweep); admin
 * rooms are permanent and may be public or private, with private
 * membership managed directly by an admin rather than self-serve join.
 */
final class ChatService
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int, array<string, mixed>> public rooms only (any source), most-recently-active first — the exact set the "Available Chat Rooms" block and the /chat index show. */
    public function listPublicRooms(int $limit = 50): array
    {
        $table = $this->db->table('chat_rooms');
        $membersTable = $this->db->table('chat_room_members');

        return $this->db->fetchAll(
            "SELECT r.*, (SELECT COUNT(*) FROM {$membersTable} m WHERE m.room_id = r.id) AS member_count
             FROM {$table} r
             WHERE r.visibility = 'public'
             ORDER BY r.updated_at DESC
             LIMIT " . max(1, $limit)
        );
    }

    /** @return array<int, array<string, mixed>> every room, for the admin screen — public and private, admin- and user-created alike. */
    public function listAllRooms(): array
    {
        $table = $this->db->table('chat_rooms');
        $membersTable = $this->db->table('chat_room_members');

        return $this->db->fetchAll(
            "SELECT r.*, (SELECT COUNT(*) FROM {$membersTable} m WHERE m.room_id = r.id) AS member_count
             FROM {$table} r
             ORDER BY r.source, r.name"
        );
    }

    /** @return array<string, mixed>|null */
    public function findRoom(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM ' . $this->db->table('chat_rooms') . ' WHERE id = :id', ['id' => $id]);
    }

    public function isMember(int $roomId, int $userId): bool
    {
        return $this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('chat_room_members') . ' WHERE room_id = :room_id AND user_id = :user_id',
            ['room_id' => $roomId, 'user_id' => $userId]
        ) !== null;
    }

    public function memberCount(int $roomId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS c FROM ' . $this->db->table('chat_room_members') . ' WHERE room_id = :room_id',
            ['room_id' => $roomId]
        );

        return (int) ($row['c'] ?? 0);
    }

    /** @return array<int, array<string, mixed>> */
    public function listMembers(int $roomId): array
    {
        $usersTable = $this->db->table('users');
        $membersTable = $this->db->table('chat_room_members');

        return $this->db->fetchAll(
            "SELECT u.id, u.username, m.joined_at
             FROM {$membersTable} m
             JOIN {$usersTable} u ON u.id = m.user_id
             WHERE m.room_id = :room_id
             ORDER BY m.joined_at",
            ['room_id' => $roomId]
        );
    }

    /** Admin-only: creates a permanent room, public or private. */
    public function createAdminRoom(string $name, ?string $topic, string $visibility): int|false
    {
        $name = trim($name);
        if ($name === '' || !in_array($visibility, ['public', 'private'], true)) {
            return false;
        }

        $now = date('Y-m-d H:i:s');

        return (int) $this->db->insert('chat_rooms', [
            'name' => $name,
            'topic' => $topic !== null && trim($topic) !== '' ? trim($topic) : null,
            'source' => 'admin',
            'visibility' => $visibility,
            'owner_user_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** Always public, always owned by the creator, who is auto-joined as its first member. */
    public function createUserRoom(string $name, ?string $topic, int $ownerUserId): int|false
    {
        $name = trim($name);
        if ($name === '') {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $id = (int) $this->db->insert('chat_rooms', [
            'name' => $name,
            'topic' => $topic !== null && trim($topic) !== '' ? trim($topic) : null,
            'source' => 'user',
            'visibility' => 'public',
            'owner_user_id' => $ownerUserId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->joinRoom($id, $ownerUserId);

        return $id;
    }

    /** Admin-only: name/topic/visibility on any room. A user room stays 'public' regardless of what's passed — this is the one thing admin can't flip, since it's the confirmed rule, not an oversight. */
    public function updateRoom(int $id, string $name, ?string $topic, string $visibility): void
    {
        $room = $this->findRoom($id);
        if ($room === null) {
            return;
        }

        $name = trim($name);
        if ($name === '') {
            return;
        }

        $effectiveVisibility = $room['source'] === 'user'
            ? 'public'
            : (in_array($visibility, ['public', 'private'], true) ? $visibility : $room['visibility']);

        $this->db->execute(
            'UPDATE ' . $this->db->table('chat_rooms') . '
             SET name = :name, topic = :topic, visibility = :visibility, updated_at = :now
             WHERE id = :id',
            [
                'name' => $name,
                'topic' => $topic !== null && trim($topic) !== '' ? trim($topic) : null,
                'visibility' => $effectiveVisibility,
                'now' => date('Y-m-d H:i:s'),
                'id' => $id,
            ]
        );
    }

    /** Admin-only: removes a room outright — members and messages cascade via FK. */
    public function deleteRoom(int $id): void
    {
        $this->db->execute('DELETE FROM ' . $this->db->table('chat_rooms') . ' WHERE id = :id', ['id' => $id]);
    }

    /** Idempotent — joining a room you're already in is a harmless no-op. */
    public function joinRoom(int $roomId, int $userId): void
    {
        if ($this->isMember($roomId, $userId)) {
            return;
        }

        $this->db->insert('chat_room_members', [
            'room_id' => $roomId,
            'user_id' => $userId,
            'joined_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Removes the membership row, then — the confirmed self-delete rule —
     * if the room is now empty AND it's a user-created room, deletes the
     * whole room right here, synchronously. Deliberately not a
     * cron.daily sweep like the original 2026-07-18 design notes
     * proposed: checking at the exact moment someone leaves is simpler
     * (no scheduler wiring) and more honest about when "empty" actually
     * happened, not up to a day stale.
     */
    public function leaveRoom(int $roomId, int $userId): void
    {
        $room = $this->findRoom($roomId);
        if ($room === null) {
            return;
        }

        $this->db->execute(
            'DELETE FROM ' . $this->db->table('chat_room_members') . ' WHERE room_id = :room_id AND user_id = :user_id',
            ['room_id' => $roomId, 'user_id' => $userId]
        );

        if ($room['source'] === 'user' && $this->memberCount($roomId) === 0) {
            $this->deleteRoom($roomId);
        }
    }

    /** Admin-only: adds an existing member by username — the self-serve join a private room deliberately doesn't offer. Returns false if the username doesn't resolve to a real account. */
    public function addMemberByUsername(int $roomId, string $username): bool
    {
        $user = $this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('users') . ' WHERE username = :username',
            ['username' => trim($username)]
        );
        if ($user === null) {
            return false;
        }

        $this->joinRoom($roomId, (int) $user['id']);

        return true;
    }

    /** Admin-only kick — unlike leaveRoom(), a removed member doesn't trigger the user-room self-delete check here; an admin removing the last member from a misbehaving room should leave it empty for further moderation, not vanish it out from under them. */
    public function removeMember(int $roomId, int $userId): void
    {
        $this->db->execute(
            'DELETE FROM ' . $this->db->table('chat_room_members') . ' WHERE room_id = :room_id AND user_id = :user_id',
            ['room_id' => $roomId, 'user_id' => $userId]
        );
    }

    /**
     * Posts a message and bumps the room's updated_at — the single
     * "activity" signal listPublicRooms()/the block sort by, no separate
     * last-activity column needed. A leading "/me " strips itself and
     * flags the message as an action (classic IRC "* user waves"
     * rendering), the one slash command this simplified v1 supports.
     */
    /** @return array{id: int, body: string, is_action: bool}|false the saved message, so the controller never needs to re-derive the /me parsing itself. */
    public function postMessage(int $roomId, int $userId, string $rawBody): array|false
    {
        $isAction = false;
        $body = trim($rawBody);
        if (str_starts_with($body, '/me ')) {
            $isAction = true;
            $body = trim(substr($body, 4));
        }

        if ($body === '') {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $id = (int) $this->db->insert('chat_messages', [
            'room_id' => $roomId,
            'user_id' => $userId,
            'body' => $body,
            'is_action' => $isAction ? 1 : 0,
            'created_at' => $now,
        ]);

        $this->db->execute(
            'UPDATE ' . $this->db->table('chat_rooms') . ' SET updated_at = :now WHERE id = :id',
            ['now' => $now, 'id' => $roomId]
        );

        return ['id' => $id, 'body' => $body, 'is_action' => $isAction];
    }

    /** @return array<int, array<string, mixed>> most recent $limit messages, oldest first (ready to render top-to-bottom). */
    public function recentMessages(int $roomId, int $limit = 50): array
    {
        $usersTable = $this->db->table('users');
        $messagesTable = $this->db->table('chat_messages');

        $rows = $this->db->fetchAll(
            "SELECT m.*, u.username
             FROM {$messagesTable} m
             JOIN {$usersTable} u ON u.id = m.user_id
             WHERE m.room_id = :room_id
             ORDER BY m.id DESC
             LIMIT " . max(1, $limit),
            ['room_id' => $roomId]
        );

        return array_reverse($rows);
    }

    /** @return array<int, array<string, mixed>> everything after $afterId, oldest first — the AJAX polling endpoint's whole query. */
    public function messagesAfter(int $roomId, int $afterId): array
    {
        $usersTable = $this->db->table('users');
        $messagesTable = $this->db->table('chat_messages');

        return $this->db->fetchAll(
            "SELECT m.*, u.username
             FROM {$messagesTable} m
             JOIN {$usersTable} u ON u.id = m.user_id
             WHERE m.room_id = :room_id AND m.id > :after_id
             ORDER BY m.id ASC",
            ['room_id' => $roomId, 'after_id' => $afterId]
        );
    }
}
