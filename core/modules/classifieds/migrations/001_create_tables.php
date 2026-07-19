<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            CREATE TABLE ' . $db->table('classifieds_categories') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(191) NOT NULL,
                slug VARCHAR(191) NOT NULL,
                weight INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_classifieds_category_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('classifieds_listings') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                category_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NULL,
                title VARCHAR(191) NOT NULL,
                description TEXT NULL,
                price DECIMAL(10,2) NULL,
                status VARCHAR(16) NOT NULL DEFAULT \'active\',
                filename VARCHAR(191) NULL,
                thumbnail_filename VARCHAR(191) NULL,
                mime_type VARCHAR(127) NULL,
                size INT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,
                KEY idx_classifieds_listings_category (category_id),
                KEY idx_classifieds_listings_user (user_id),
                CONSTRAINT fk_classifieds_listings_category FOREIGN KEY (category_id)
                    REFERENCES ' . $db->table('classifieds_categories') . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_classifieds_listings_user FOREIGN KEY (user_id)
                    REFERENCES ' . $db->table('users') . ' (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('classifieds_listings'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('classifieds_categories'));
    }
};
