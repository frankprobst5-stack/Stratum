<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $usersTable = $db->table('users');

        $db->execute('
            CREATE TABLE ' . $db->table('notifications') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                actor_id BIGINT UNSIGNED NULL,
                type VARCHAR(48) NOT NULL,
                message VARCHAR(255) NOT NULL,
                url VARCHAR(255) NULL,
                read_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                KEY idx_notifications_user (user_id, read_at, created_at),
                CONSTRAINT fk_notifications_user FOREIGN KEY (user_id)
                    REFERENCES ' . $usersTable . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_notifications_actor FOREIGN KEY (actor_id)
                    REFERENCES ' . $usersTable . ' (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        // Seeds the bell's header placement directly — no block-placement
        // admin UI exists yet (Stage 8), same pattern as ticker (weight 0)
        // and the search box (weight 10); the bell renders after both.
        $now = date('Y-m-d H:i:s');
        $db->execute(
            'INSERT INTO ' . $db->table('block_placements') . '
                (block_type, region_id, page_scope, weight, config_json, is_enabled, created_at, updated_at)
             SELECT \'notifications.bell\', id, \'site_wide\', 20, NULL, 1, :created_at, :updated_at
             FROM ' . $db->table('block_regions') . ' WHERE `key` = \'header\'',
            ['created_at' => $now, 'updated_at' => $now]
        );
    }

    public function down(Database $db): void
    {
        $db->execute(
            'DELETE FROM ' . $db->table('block_placements') . ' WHERE block_type = :type',
            ['type' => 'notifications.bell']
        );
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('notifications'));
    }
};
