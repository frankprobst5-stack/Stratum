<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * Admin action history — "who changed what, when," distinct from
 * `core_logs` (app/error logging, see the Log Viewer entry in
 * docs/roadmap.md). Lives in core, not a module, since it captures
 * every admin mutation across every module generically (see
 * public/index.php's dispatch wrapper) rather than being owned by any
 * one feature.
 *
 * `username` is captured at write time, not joined from `users` on
 * read — the same "denormalize so the log stays readable after the
 * account is gone" reasoning already used elsewhere (RssItem titles,
 * TrashService labels), so a deleted/merged admin's history doesn't
 * turn into a wall of "Unknown."
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $users = $db->table('users');

        $db->execute('
            CREATE TABLE ' . $db->table('audit_log') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NULL,
                username VARCHAR(191) NOT NULL,
                method VARCHAR(8) NOT NULL,
                path VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_audit_log_created (created_at),
                KEY idx_audit_log_user (user_id),
                CONSTRAINT fk_audit_log_user FOREIGN KEY (user_id)
                    REFERENCES ' . $users . ' (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('audit_log'));
    }
};
