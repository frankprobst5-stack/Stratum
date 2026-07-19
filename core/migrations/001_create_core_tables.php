<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            CREATE TABLE ' . $db->table('core_modules') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                module_id VARCHAR(64) NOT NULL,
                is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_module_id (module_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('core_settings') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `key` VARCHAR(191) NOT NULL,
                `value` TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_setting_key (`key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('core_logs') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                level VARCHAR(16) NOT NULL,
                message TEXT NOT NULL,
                context TEXT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_level_created (level, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('login_attempts') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                identifier VARCHAR(191) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                succeeded TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                KEY idx_identifier_created (identifier, created_at),
                KEY idx_ip_created (ip_address, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('block_regions') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `key` VARCHAR(64) NOT NULL,
                label VARCHAR(191) NOT NULL,
                UNIQUE KEY uniq_region_key (`key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('block_placements') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                block_type VARCHAR(191) NOT NULL,
                region_id BIGINT UNSIGNED NOT NULL,
                page_scope VARCHAR(191) NOT NULL DEFAULT \'site_wide\',
                weight INT NOT NULL DEFAULT 0,
                config_json TEXT NULL,
                is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                KEY idx_region_scope (region_id, page_scope),
                CONSTRAINT fk_block_placements_region FOREIGN KEY (region_id)
                    REFERENCES ' . $db->table('block_regions') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        foreach ([
            ['header', 'Header'],
            ['sidebar_left', 'Left Sidebar'],
            ['sidebar_right', 'Right Sidebar'],
            ['footer', 'Footer'],
            ['front_feature', 'Front Page Feature'],
        ] as [$key, $label]) {
            $db->insert('block_regions', ['key' => $key, 'label' => $label]);
        }
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('block_placements'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('block_regions'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('login_attempts'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('core_logs'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('core_settings'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('core_modules'));
    }
};
