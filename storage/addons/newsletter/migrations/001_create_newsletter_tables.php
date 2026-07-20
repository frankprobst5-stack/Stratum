<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            CREATE TABLE ' . $db->table('newsletter_issues') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(191) NOT NULL,
                slug VARCHAR(191) NOT NULL,
                is_published TINYINT(1) NOT NULL DEFAULT 0,
                published_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_newsletter_issues_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('newsletter_pages') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                issue_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(191) NOT NULL,
                body TEXT NOT NULL,
                position INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                KEY idx_newsletter_pages_issue (issue_id, position),
                CONSTRAINT fk_newsletter_pages_issue FOREIGN KEY (issue_id)
                    REFERENCES ' . $db->table('newsletter_issues') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('newsletter_pages'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('newsletter_issues'));
    }
};
