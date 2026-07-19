<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            CREATE TABLE ' . $db->table('rss_sources') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(191) NOT NULL,
                feed_url VARCHAR(500) NOT NULL,
                is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                last_fetched_at DATETIME NULL,
                last_fetch_error VARCHAR(500) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('rss_items') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source_id BIGINT UNSIGNED NOT NULL,
                guid VARCHAR(500) NOT NULL,
                title VARCHAR(500) NOT NULL,
                url VARCHAR(500) NOT NULL,
                description TEXT NULL,
                published_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_source_guid (source_id, guid),
                KEY idx_items_published (published_at),
                CONSTRAINT fk_rss_items_source FOREIGN KEY (source_id)
                    REFERENCES ' . $db->table('rss_sources') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('rss_items'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('rss_sources'));
    }
};
