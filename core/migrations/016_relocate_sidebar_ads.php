<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * Relocates ad banners out of sidebar_left/sidebar_right, 2026-07-18 —
 * confirmed with the user after the sidebar-collapse fix (see roadmap's
 * "Third correction" note): those two site-wide placements were the
 * actual cause of the reserved-but-empty 200px sidebar tracks whenever
 * no campaign was active. Rather than keep ads in a region that's now
 * designed to collapse around emptiness, they move into the front
 * page's already-adaptive 3-column block system, admin-placeable via
 * /admin/blocks like any other block. header/footer ad.banner
 * placements are untouched — those regions were never part of the
 * fixed-column grid, so they never had this problem.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $placementsTable = $db->table('block_placements');
        $regionsTable = $db->table('block_regions');

        $db->execute(
            "DELETE p FROM {$placementsTable} p
             JOIN {$regionsTable} r ON r.id = p.region_id
             WHERE p.block_type = 'ads.banner' AND r.`key` IN ('sidebar_left', 'sidebar_right')"
        );

        $frontCol2 = $db->fetchOne("SELECT id FROM {$regionsTable} WHERE `key` = 'front_col_2'");
        if ($frontCol2 === null) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $db->insert('block_placements', [
            'block_type' => 'ads.banner',
            'region_id' => $frontCol2['id'],
            'page_scope' => 'front_page_only',
            'weight' => 5,
            'config_json' => json_encode(['zone' => 'front_col_2']),
            'is_enabled' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(Database $db): void
    {
        $placementsTable = $db->table('block_placements');
        $regionsTable = $db->table('block_regions');

        $db->execute(
            "DELETE p FROM {$placementsTable} p
             JOIN {$regionsTable} r ON r.id = p.region_id
             WHERE p.block_type = 'ads.banner' AND r.`key` = 'front_col_2'
               AND p.config_json = '{\"zone\":\"front_col_2\"}'"
        );

        $now = date('Y-m-d H:i:s');
        foreach (['sidebar_left', 'sidebar_right'] as $key) {
            $db->execute(
                "INSERT INTO {$placementsTable}
                    (block_type, region_id, page_scope, weight, config_json, is_enabled, created_at, updated_at)
                 SELECT 'ads.banner', id, 'site_wide', 20, :config, 1, :created_at, :updated_at
                 FROM {$regionsTable} WHERE `key` = :zone",
                ['config' => json_encode(['zone' => $key]), 'created_at' => $now, 'updated_at' => $now, 'zone' => $key]
            );
        }
    }
};
