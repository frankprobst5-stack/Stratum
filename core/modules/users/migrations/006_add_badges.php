<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * Achievement badges — a member can hold many at once (many-to-many),
 * unlike `ranks` (one at a time, points-driven, already built). Admin-
 * awarded only for v1, not auto-triggered by rules ("award on 10th
 * post," "award on 1-year anniversary") — that's a real, separate rules-
 * engine feature this pass deliberately doesn't build, the same
 * "narrower first pass" reasoning `reputation`'s own backlog entry
 * explicitly calls for a deliberate decision on rather than silently
 * building or dropping.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $users = $db->table('users');

        $db->execute('
            CREATE TABLE ' . $db->table('badges') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(191) NOT NULL,
                description VARCHAR(500) NULL,
                icon_url VARCHAR(255) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('member_badges') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                badge_id BIGINT UNSIGNED NOT NULL,
                awarded_by BIGINT UNSIGNED NULL,
                awarded_at DATETIME NOT NULL,
                UNIQUE KEY uniq_member_badge (user_id, badge_id),
                KEY idx_member_badges_badge (badge_id),
                CONSTRAINT fk_member_badges_user FOREIGN KEY (user_id)
                    REFERENCES ' . $users . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_member_badges_badge FOREIGN KEY (badge_id)
                    REFERENCES ' . $db->table('badges') . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_member_badges_awarded_by FOREIGN KEY (awarded_by)
                    REFERENCES ' . $users . ' (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('member_badges'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('badges'));
    }
};
