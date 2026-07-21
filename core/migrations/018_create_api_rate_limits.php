<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * Fixed-window request counter for the REST API (Stage 10) — one row per
 * (identifier, minute), incremented atomically via `ON DUPLICATE KEY
 * UPDATE` rather than a read-then-write, so concurrent requests in the
 * same window can't race past the limit. `identifier` is a personal API
 * token's hash (same SHA-256 `ApiTokenService` already stores, never the
 * raw token) for authenticated requests, or the caller's IP for guest
 * reads — see `ApiRateLimiter`.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            CREATE TABLE ' . $db->table('api_rate_limits') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                identifier VARCHAR(191) NOT NULL,
                window_start DATETIME NOT NULL,
                request_count INT UNSIGNED NOT NULL DEFAULT 0,
                UNIQUE KEY uniq_identifier_window (identifier, window_start),
                KEY idx_window_start (window_start)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('api_rate_limits'));
    }
};
