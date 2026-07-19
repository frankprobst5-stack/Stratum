<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            CREATE TABLE ' . $db->table('video_categories') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(191) NOT NULL,
                slug VARCHAR(191) NOT NULL,
                weight INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_video_category_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('videos') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                category_id BIGINT UNSIGNED NOT NULL,
                uploader_id BIGINT UNSIGNED NULL,
                title VARCHAR(191) NOT NULL,
                description TEXT NULL,
                source_type VARCHAR(16) NOT NULL,
                external_id VARCHAR(64) NULL,
                filename VARCHAR(191) NULL,
                mime_type VARCHAR(127) NULL,
                size INT UNSIGNED NULL,
                view_count INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,
                KEY idx_videos_category (category_id),
                CONSTRAINT fk_videos_category FOREIGN KEY (category_id)
                    REFERENCES ' . $db->table('video_categories') . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_videos_uploader FOREIGN KEY (uploader_id)
                    REFERENCES ' . $db->table('users') . ' (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('videos'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('video_categories'));
    }
};
