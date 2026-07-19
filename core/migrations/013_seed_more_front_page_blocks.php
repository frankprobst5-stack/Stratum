<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * Second wave of front-page block placements — the 11 blocks built in the
 * Stage 8 follow-up batch (2026-07-18), stacked into the same three
 * columns core migration 012 introduced, alongside the three blocks
 * already seeded there (activity.feed, presence.whosonline, tags.cloud).
 * Weight-ordered within each column, same multi-placement-per-region
 * shape sidebar_left/sidebar_right already support.
 */
return new class implements Migration {
    private const PLACEMENTS = [
        ['front_col_1', 'custom.welcome_cta', 5, null],
        ['front_col_1', 'forum.recent_topics', 20, null],
        ['front_col_1', 'comments.recent', 30, null],
        ['front_col_1', 'video.recent', 40, null],
        ['front_col_2', 'calendar.upcoming_events', 20, null],
        ['front_col_2', 'downloads.recent', 30, null],
        ['front_col_2', 'org_spaces.featured_club', 40, null],
        ['front_col_2', 'custom.quick_links', 50, null],
        ['front_col_3', 'users.newest_members', 20, null],
        ['front_col_3', 'gallery.highlights', 30, null],
        ['front_col_3', 'site_stats.summary', 40, null],
    ];

    public function up(Database $db): void
    {
        $now = date('Y-m-d H:i:s');

        foreach (self::PLACEMENTS as [$regionKey, $blockType, $weight, $config]) {
            $db->execute(
                'INSERT INTO ' . $db->table('block_placements') . '
                    (block_type, region_id, page_scope, weight, config_json, is_enabled, created_at, updated_at)
                 SELECT :block_type, id, \'front_page_only\', :weight, :config, 1, :created_at, :updated_at
                 FROM ' . $db->table('block_regions') . ' WHERE `key` = :zone',
                [
                    'block_type' => $blockType,
                    'weight' => $weight,
                    'config' => $config,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'zone' => $regionKey,
                ]
            );
        }
    }

    public function down(Database $db): void
    {
        foreach (self::PLACEMENTS as [, $blockType]) {
            $db->execute(
                'DELETE FROM ' . $db->table('block_placements') . ' WHERE block_type = :block_type',
                ['block_type' => $blockType]
            );
        }
    }
};
