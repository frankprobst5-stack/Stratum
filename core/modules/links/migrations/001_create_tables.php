<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * Link directory — categories + external links + descriptions, same
 * category/content shape `downloads` and `classifieds` already
 * established. A top-line item in the original vision notes, peer to
 * forum/downloads/calendar, that never became a tracked module until now.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            CREATE TABLE ' . $db->table('link_categories') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(191) NOT NULL,
                slug VARCHAR(191) NOT NULL,
                weight INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_link_category_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('links') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                category_id BIGINT UNSIGNED NOT NULL,
                submitted_by BIGINT UNSIGNED NULL,
                title VARCHAR(191) NOT NULL,
                url VARCHAR(500) NOT NULL,
                description TEXT NULL,
                click_count INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,
                KEY idx_links_category (category_id),
                CONSTRAINT fk_links_category FOREIGN KEY (category_id)
                    REFERENCES ' . $db->table('link_categories') . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_links_submitted_by FOREIGN KEY (submitted_by)
                    REFERENCES ' . $db->table('users') . ' (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('links'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('link_categories'));
    }
};
