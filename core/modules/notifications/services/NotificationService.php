<?php

declare(strict_types=1);

namespace Stratum\Modules\Notifications;

use Stratum\Core\Database;

final class NotificationService
{
    private const LIST_LIMIT = 50;
    private const MESSAGE_MAX = 255;

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Consumes a 'notify' hook event and inserts one row per recipient.
     * All skip rules live here, centralized, so producers never duplicate
     * them: null/zero recipients are dropped (nullable user_id columns on
     * dues payments, donations, video/gallery uploaders), and a recipient
     * equal to the actor is dropped (no self-notifications).
     *
     * Expected event shape:
     * ['user_id' => int|array<int|null>, 'actor_id' => ?int,
     *  'type' => string, 'message' => string, 'url' => ?string]
     *
     * @param array<string, mixed> $event
     */
    public function push(array $event): void
    {
        $recipients = is_array($event['user_id'] ?? null)
            ? $event['user_id']
            : [$event['user_id'] ?? null];
        $actorId = isset($event['actor_id']) ? (int) $event['actor_id'] : null;
        $type = (string) ($event['type'] ?? 'general');
        $message = mb_substr(trim((string) ($event['message'] ?? '')), 0, self::MESSAGE_MAX);
        $url = isset($event['url']) && $event['url'] !== '' ? (string) $event['url'] : null;

        if ($message === '') {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $seen = [];
        foreach ($recipients as $recipient) {
            $recipientId = (int) ($recipient ?? 0);
            if ($recipientId <= 0 || $recipientId === $actorId || isset($seen[$recipientId])) {
                continue;
            }
            $seen[$recipientId] = true;

            $this->db->insert('notifications', [
                'user_id' => $recipientId,
                'actor_id' => $actorId,
                'type' => $type,
                'message' => $message,
                'url' => $url,
                'read_at' => null,
                'created_at' => $now,
            ]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function listForUser(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('notifications') . '
             WHERE user_id = :user_id
             ORDER BY created_at DESC, id DESC
             LIMIT ' . self::LIST_LIMIT,
            ['user_id' => $userId]
        );
    }

    public function unreadCount(int $userId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS unread FROM ' . $this->db->table('notifications') . '
             WHERE user_id = :user_id AND read_at IS NULL',
            ['user_id' => $userId]
        );

        return (int) ($row['unread'] ?? 0);
    }

    /** Scoped to the owner — a user can never mark another user's row. */
    public function markRead(int $notificationId, int $userId): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('notifications') . '
             SET read_at = :now WHERE id = :id AND user_id = :user_id AND read_at IS NULL',
            ['now' => date('Y-m-d H:i:s'), 'id' => $notificationId, 'user_id' => $userId]
        );
    }

    public function markAllRead(int $userId): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('notifications') . '
             SET read_at = :now WHERE user_id = :user_id AND read_at IS NULL',
            ['now' => date('Y-m-d H:i:s'), 'user_id' => $userId]
        );
    }
}
