<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $usersTable = $db->table('users');

        // content_title/content_url are denormalized at report time (resolved
        // server-side from the reportable allow-list, never client-supplied) —
        // same posture as notifications' message/url: the queue never needs
        // per-type joins to display a report, and a report on since-deleted
        // content just 404s its link instead of breaking the screen.
        $db->execute('
            CREATE TABLE ' . $db->table('moderation_reports') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                reportable_type VARCHAR(48) NOT NULL,
                reportable_id BIGINT UNSIGNED NOT NULL,
                reporter_id BIGINT UNSIGNED NOT NULL,
                reason VARCHAR(500) NOT NULL,
                content_title VARCHAR(191) NOT NULL,
                content_url VARCHAR(255) NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT \'open\',
                resolution_note VARCHAR(255) NULL,
                resolved_by BIGINT UNSIGNED NULL,
                resolved_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                KEY idx_moderation_reports_status (status, created_at),
                KEY idx_moderation_reports_target (reportable_type, reportable_id),
                CONSTRAINT fk_moderation_reports_reporter FOREIGN KEY (reporter_id)
                    REFERENCES ' . $usersTable . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_moderation_reports_resolver FOREIGN KEY (resolved_by)
                    REFERENCES ' . $usersTable . ' (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('moderation_reports'));
    }
};
