<?php

declare(strict_types=1);

namespace Stratum\Modules\Messages;

use Stratum\Core\Database;

final class MessagesService
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Finds the existing conversation between $userA/$userB, or creates
     * one — user ids are always stored in a canonical (smaller-first)
     * order, so it's impossible for two separate rows to exist for the
     * same pair depending on who messaged whom first.
     */
    public function findOrCreateConversation(int $userA, int $userB): int
    {
        [$one, $two] = $userA < $userB ? [$userA, $userB] : [$userB, $userA];

        $existing = $this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('message_conversations') . '
             WHERE user_one_id = :one AND user_two_id = :two',
            ['one' => $one, 'two' => $two]
        );
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $now = date('Y-m-d H:i:s');

        return (int) $this->db->insert('message_conversations', [
            'user_one_id' => $one,
            'user_two_id' => $two,
            'last_message_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function findConversation(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('message_conversations') . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    /** @param array<string, mixed> $conversation */
    public function isParticipant(array $conversation, int $userId): bool
    {
        return (int) $conversation['user_one_id'] === $userId || (int) $conversation['user_two_id'] === $userId;
    }

    /** @param array<string, mixed> $conversation */
    public function otherParticipantId(array $conversation, int $currentUserId): int
    {
        return (int) $conversation['user_one_id'] === $currentUserId
            ? (int) $conversation['user_two_id']
            : (int) $conversation['user_one_id'];
    }

    public function sendMessage(int $conversationId, int $senderId, string $body): int
    {
        $now = date('Y-m-d H:i:s');

        $id = (int) $this->db->insert('direct_messages', [
            'conversation_id' => $conversationId,
            'sender_id' => $senderId,
            'body' => $body,
            'read_at' => null,
            'created_at' => $now,
        ]);

        $this->db->execute(
            'UPDATE ' . $this->db->table('message_conversations') . '
             SET last_message_at = :now1, updated_at = :now2 WHERE id = :id',
            ['now1' => $now, 'now2' => $now, 'id' => $conversationId]
        );

        return $id;
    }

    /**
     * @return array<int, array<string, mixed>> every conversation $userId is part of,
     *     each with 'otherUsername' and 'unreadCount', most recently active first
     */
    public function listConversationsForUser(int $userId): array
    {
        $conversationsTable = $this->db->table('message_conversations');
        $usersTable = $this->db->table('users');
        $messagesTable = $this->db->table('direct_messages');

        return $this->db->fetchAll(
            "SELECT c.*,
                    u.username AS otherUsername,
                    (SELECT COUNT(*) FROM {$messagesTable} m
                     WHERE m.conversation_id = c.id AND m.sender_id != :user_id1 AND m.read_at IS NULL) AS unreadCount
             FROM {$conversationsTable} c
             JOIN {$usersTable} u ON u.id = IF(c.user_one_id = :user_id2, c.user_two_id, c.user_one_id)
             WHERE c.user_one_id = :user_id3 OR c.user_two_id = :user_id4
             ORDER BY COALESCE(c.last_message_at, c.created_at) DESC",
            ['user_id1' => $userId, 'user_id2' => $userId, 'user_id3' => $userId, 'user_id4' => $userId]
        );
    }

    /** @return array<int, array<string, mixed>> chronological, each with 'senderName' */
    public function listMessagesInConversation(int $conversationId): array
    {
        $messagesTable = $this->db->table('direct_messages');
        $usersTable = $this->db->table('users');

        return $this->db->fetchAll(
            "SELECT m.*, u.username AS senderName
             FROM {$messagesTable} m
             JOIN {$usersTable} u ON u.id = m.sender_id
             WHERE m.conversation_id = :conversation_id
             ORDER BY m.created_at ASC, m.id ASC",
            ['conversation_id' => $conversationId]
        );
    }

    public function markConversationRead(int $conversationId, int $userId): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('direct_messages') . '
             SET read_at = :now
             WHERE conversation_id = :conversation_id AND sender_id != :user_id AND read_at IS NULL',
            ['now' => date('Y-m-d H:i:s'), 'conversation_id' => $conversationId, 'user_id' => $userId]
        );
    }

    /** Total unread messages across every conversation $userId is part of — powers the header badge. */
    public function unreadCount(int $userId): int
    {
        $conversationsTable = $this->db->table('message_conversations');
        $messagesTable = $this->db->table('direct_messages');

        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS unread
             FROM {$messagesTable} m
             JOIN {$conversationsTable} c ON c.id = m.conversation_id
             WHERE (c.user_one_id = :user_id1 OR c.user_two_id = :user_id2)
               AND m.sender_id != :user_id3 AND m.read_at IS NULL",
            ['user_id1' => $userId, 'user_id2' => $userId, 'user_id3' => $userId]
        );

        return (int) ($row['unread'] ?? 0);
    }
}
