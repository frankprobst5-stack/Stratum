<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Fixed-window request counter for the REST API (Stage 10). One row per
 * (identifier, minute) in `api_rate_limits`, incremented atomically via
 * `INSERT ... ON DUPLICATE KEY UPDATE` — a read-then-write would let two
 * concurrent requests in the same window both read the same count and
 * both pass, undercounting exactly when it matters most.
 */
final class ApiRateLimiter
{
    public function __construct(private readonly Database $db)
    {
    }

    /** True if $identifier has already made more than $limitPerMinute requests in the current one-minute window (this call's own request counts toward that window). */
    public function tooManyRequests(string $identifier, int $limitPerMinute): bool
    {
        $windowStart = date('Y-m-d H:i:00');
        $table = $this->db->table('api_rate_limits');

        $this->db->execute(
            "INSERT INTO {$table} (identifier, window_start, request_count)
             VALUES (:identifier, :window_start, 1)
             ON DUPLICATE KEY UPDATE request_count = request_count + 1",
            ['identifier' => $identifier, 'window_start' => $windowStart]
        );

        $row = $this->db->fetchOne(
            "SELECT request_count FROM {$table} WHERE identifier = :identifier AND window_start = :window_start",
            ['identifier' => $identifier, 'window_start' => $windowStart]
        );

        return $row !== null && (int) $row['request_count'] > $limitPerMinute;
    }

    /**
     * Deletes windows older than a day — this table has much higher
     * row-churn than `login_attempts` (one row per identifier per *minute*
     * of API traffic, not just failed logins), so unlike that table it
     * needs an active prune rather than being left to grow. Called from
     * `bin/cron.php`'s `cron.daily` hook.
     */
    public function pruneOldWindows(): int
    {
        return $this->db->execute(
            'DELETE FROM ' . $this->db->table('api_rate_limits') . ' WHERE window_start < :cutoff',
            ['cutoff' => date('Y-m-d H:i:00', time() - 86400)]
        );
    }
}
