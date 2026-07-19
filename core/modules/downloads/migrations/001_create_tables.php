<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            CREATE TABLE ' . $db->table('downloads_categories') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(191) NOT NULL,
                slug VARCHAR(191) NOT NULL,
                weight INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_downloads_category_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('downloads_files') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                category_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(191) NOT NULL,
                description TEXT NULL,
                download_count INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,
                KEY idx_downloads_files_category (category_id),
                CONSTRAINT fk_downloads_files_category FOREIGN KEY (category_id)
                    REFERENCES ' . $db->table('downloads_categories') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('downloads_versions') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                file_id BIGINT UNSIGNED NOT NULL,
                uploader_id BIGINT UNSIGNED NULL,
                filename VARCHAR(191) NOT NULL,
                original_name VARCHAR(191) NOT NULL,
                mime_type VARCHAR(127) NOT NULL,
                size INT UNSIGNED NOT NULL,
                version_number INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_downloads_versions_file (file_id),
                CONSTRAINT fk_downloads_versions_file FOREIGN KEY (file_id)
                    REFERENCES ' . $db->table('downloads_files') . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_downloads_versions_uploader FOREIGN KEY (uploader_id)
                    REFERENCES ' . $db->table('users') . ' (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('downloads_versions'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('downloads_files'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('downloads_categories'));
    }
};
