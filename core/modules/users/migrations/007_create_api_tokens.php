<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * Personal API tokens (Stage 10, REST API foundation). Deliberately a
 * `users` module migration, not a core/migrations/ one — MigrationRunner
 * ::runAll() runs core migrations before any module (including users),
 * so a core-level FK to users(id) would fail on a genuinely fresh
 * install; living here guarantees it always runs after 001_create_users_
 * table.php within this module's own migration sequence. Also just a
 * better architectural fit — a token is fundamentally a user's own
 * credential, not site-wide configuration the way core_settings/
 * block_placements are.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            CREATE TABLE ' . $db->table('api_tokens') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                token_hash CHAR(64) NOT NULL,
                name VARCHAR(191) NOT NULL,
                last_used_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                revoked_at DATETIME NULL,
                UNIQUE KEY uniq_api_tokens_hash (token_hash),
                KEY idx_api_tokens_user (user_id),
                CONSTRAINT fk_api_tokens_user FOREIGN KEY (user_id)
                    REFERENCES ' . $db->table('users') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('api_tokens'));
    }
};
