<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            CREATE TABLE ' . $db->table('sponsors') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(191) NOT NULL,
                logo_url VARCHAR(500) NOT NULL,
                link_url VARCHAR(500) NOT NULL,
                weight INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                click_count INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        // Sponsor acknowledgment is an "always-on" footer strip, not a
        // rotating per-zone placement like ads.banner — one seeded
        // placement is enough, matching the same "seed our own default
        // directly" pattern (no admin placement UI yet, Stage 8).
        $now = date('Y-m-d H:i:s');
        $db->execute(
            'INSERT INTO ' . $db->table('block_placements') . '
                (block_type, region_id, page_scope, weight, config_json, is_enabled, created_at, updated_at)
             SELECT \'sponsors.strip\', id, \'site_wide\', 30, NULL, 1, :created_at, :updated_at
             FROM ' . $db->table('block_regions') . ' WHERE `key` = \'footer\'',
            ['created_at' => $now, 'updated_at' => $now]
        );
    }

    public function down(Database $db): void
    {
        $db->execute('DELETE FROM ' . $db->table('block_placements') . ' WHERE block_type = :type', ['type' => 'sponsors.strip']);
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('sponsors'));
    }
};
