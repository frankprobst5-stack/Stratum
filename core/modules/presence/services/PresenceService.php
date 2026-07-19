<?php

declare(strict_types=1);

namespace Stratum\Modules\Presence;

use Stratum\Core\Database;

final class PresenceService
{
    /** Matches the common e107/SMF-era convention for how long a quiet tab still counts as "online." */
    private const ONLINE_WINDOW_MINUTES = 5;

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Records this session as active right now — guest or member, told
     * apart only by whether $userId is null. Called once per request from
     * public/index.php (see the call site's comment for why this can't be
     * a normal module route/hook), so this has to be cheap: one upsert,
     * no reads.
     */
    public function touch(string $sessionId, ?int $userId): void
    {
        $table = $this->db->table('presence');

        // NOW() here too, not a PHP-bound timestamp — the write and the
        // read-side window comparison must share exactly one clock
        // (MySQL's), or a misconfigured APP_TIMEZONE could make every
        // session look permanently online or permanently offline instead
        // of just cosmetically wrong.
        $this->db->execute(
            "INSERT INTO {$table} (session_id, user_id, last_seen_at) VALUES (:session_id, :user_id, NOW())
             ON DUPLICATE KEY UPDATE user_id = :user_id2, last_seen_at = NOW()",
            ['session_id' => $sessionId, 'user_id' => $userId, 'user_id2' => $userId]
        );
    }

    /**
     * @return array<int, array{user_id: int, username: string, last_seen_at: string}> online members, most-recently-active first
     *
     * The window comparison is done entirely in MySQL (`NOW() - INTERVAL`),
     * never a PHP-computed cutoff — same reasoning as
     * ArticleService::PUBLISHED_CONDITION: one clock, no PHP-vs-MySQL
     * timezone drift possible, regardless of whether APP_TIMEZONE happens
     * to be configured correctly on a given install.
     */
    public function onlineMembers(): array
    {
        $presence = $this->db->table('presence');
        $users = $this->db->table('users');

        return $this->db->fetchAll(
            "SELECT p.user_id, u.username, p.last_seen_at
             FROM {$presence} p
             INNER JOIN {$users} u ON u.id = p.user_id AND u.deleted_at IS NULL
             WHERE p.user_id IS NOT NULL AND p.last_seen_at >= NOW() - INTERVAL " . self::ONLINE_WINDOW_MINUTES . " MINUTE
             ORDER BY p.last_seen_at DESC"
        );
    }

    public function guestCount(): int
    {
        $table = $this->db->table('presence');

        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS c FROM {$table}
             WHERE user_id IS NULL AND last_seen_at >= NOW() - INTERVAL " . self::ONLINE_WINDOW_MINUTES . ' MINUTE'
        );

        return (int) ($row['c'] ?? 0);
    }
}
