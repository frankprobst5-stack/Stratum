<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            CREATE TABLE ' . $db->table('org_spaces_orgs') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(191) NOT NULL,
                slug VARCHAR(191) NOT NULL,
                description TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_org_spaces_org_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('org_spaces_members') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                org_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(100) NULL,
                is_officer TINYINT(1) NOT NULL DEFAULT 0,
                joined_at DATETIME NOT NULL,
                UNIQUE KEY uniq_org_spaces_member (org_id, user_id),
                CONSTRAINT fk_org_spaces_members_org FOREIGN KEY (org_id)
                    REFERENCES ' . $db->table('org_spaces_orgs') . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_org_spaces_members_user FOREIGN KEY (user_id)
                    REFERENCES ' . $db->table('users') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('org_spaces_announcements') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                org_id BIGINT UNSIGNED NOT NULL,
                author_id BIGINT UNSIGNED NULL,
                title VARCHAR(150) NOT NULL,
                body TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                KEY idx_org_spaces_announcements_org (org_id),
                CONSTRAINT fk_org_spaces_announcements_org FOREIGN KEY (org_id)
                    REFERENCES ' . $db->table('org_spaces_orgs') . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_org_spaces_announcements_author FOREIGN KEY (author_id)
                    REFERENCES ' . $db->table('users') . ' (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('org_spaces_announcements'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('org_spaces_members'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('org_spaces_orgs'));
    }
};
