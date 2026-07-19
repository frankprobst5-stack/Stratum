<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $usersTable = $db->table('users');

        $db->execute('
            CREATE TABLE ' . $db->table('ticker_messages') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                message VARCHAR(280) NOT NULL,
                url VARCHAR(255) NULL,
                level VARCHAR(16) NOT NULL DEFAULT \'info\',
                starts_at DATETIME NULL,
                ends_at DATETIME NULL,
                weight INT NOT NULL DEFAULT 0,
                is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                author_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                KEY idx_ticker_active_window (is_enabled, starts_at, ends_at),
                CONSTRAINT fk_ticker_messages_author FOREIGN KEY (author_id)
                    REFERENCES ' . $usersTable . ' (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        // Seeds the one placement this module needs — there's no admin UI for
        // block placements yet (that's Stage 8), so the ticker wires itself
        // into the header region directly, same spirit as a fresh install
        // seeding its own defaults.
        $now = date('Y-m-d H:i:s');
        $db->execute(
            'INSERT INTO ' . $db->table('block_placements') . '
                (block_type, region_id, page_scope, weight, config_json, is_enabled, created_at, updated_at)
             SELECT \'ticker.messages\', id, \'site_wide\', 0, NULL, 1, :created_at, :updated_at
             FROM ' . $db->table('block_regions') . ' WHERE `key` = \'header\'',
            ['created_at' => $now, 'updated_at' => $now]
        );
    }

    public function down(Database $db): void
    {
        $db->execute(
            'DELETE FROM ' . $db->table('block_placements') . ' WHERE block_type = :type',
            ['type' => 'ticker.messages']
        );
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('ticker_messages'));
    }
};
