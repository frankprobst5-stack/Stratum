<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        // No admin UI for block placements yet (Stage 8) — same "seed our
        // own default directly" pattern ticker/search/notifications used.
        // Weight 30: after ticker (0), search box (10), notification bell (20).
        $now = date('Y-m-d H:i:s');
        $db->execute(
            'INSERT INTO ' . $db->table('block_placements') . '
                (block_type, region_id, page_scope, weight, config_json, is_enabled, created_at, updated_at)
             SELECT \'presence.whosonline\', id, \'site_wide\', 30, NULL, 1, :created_at, :updated_at
             FROM ' . $db->table('block_regions') . ' WHERE `key` = \'header\'',
            ['created_at' => $now, 'updated_at' => $now]
        );
    }

    public function down(Database $db): void
    {
        $db->execute(
            'DELETE FROM ' . $db->table('block_placements') . ' WHERE block_type = :type',
            ['type' => 'presence.whosonline']
        );
    }
};
