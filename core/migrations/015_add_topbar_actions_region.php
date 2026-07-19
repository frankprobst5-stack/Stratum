<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * New dedicated region for the compact icon cluster in the redesigned top
 * nav bar (search/messages/notifications), 2026-07-18 — split out of the
 * generic `header` region because that region is also genuinely used for
 * wider, unrelated content (ticker.messages, ads.banner,
 * presence.whosonline all render there too), which would look broken
 * crammed into a tight icon row. Moves the two placements that actually
 * belong in the new topbar (search.searchbox, notifications.bell) rather
 * than duplicating them; ticker/ads/whosonline stay in `header` exactly
 * as they were, unaffected.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $regionId = (int) $db->insert('block_regions', ['key' => 'topbar_actions', 'label' => 'Topbar Actions']);

        $db->execute(
            'UPDATE ' . $db->table('block_placements') . '
             SET region_id = :region_id
             WHERE block_type IN (\'search.searchbox\', \'notifications.bell\')',
            ['region_id' => $regionId]
        );

        $now = date('Y-m-d H:i:s');
        $db->insert('block_placements', [
            'block_type' => 'messages.icon',
            'region_id' => $regionId,
            'page_scope' => 'site_wide',
            'weight' => 15,
            'config_json' => null,
            'is_enabled' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(Database $db): void
    {
        $regionsTable = $db->table('block_regions');
        $placementsTable = $db->table('block_placements');

        $db->execute("DELETE FROM {$placementsTable} WHERE block_type = 'messages.icon'");

        $headerRegion = $db->fetchOne("SELECT id FROM {$regionsTable} WHERE `key` = 'header'");
        if ($headerRegion !== null) {
            $db->execute(
                "UPDATE {$placementsTable} SET region_id = :region_id WHERE block_type IN ('search.searchbox', 'notifications.bell')",
                ['region_id' => $headerRegion['id']]
            );
        }

        $db->execute("DELETE FROM {$regionsTable} WHERE `key` = 'topbar_actions'");
    }
};
