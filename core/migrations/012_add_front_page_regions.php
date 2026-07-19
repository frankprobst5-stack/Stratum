<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * The five front-page-specific regions from the Stage 8 design
 * (docs/theme-block-system.md's Layout section): a hero slider + compact
 * side list row, then a 3-column freeform area, all `page_scope =
 * 'front_page_only'` — that scope already existed in
 * BlockRegistry::appliesToPath() and was unused until now. Also seeds a
 * reasonable out-of-the-box default so a fresh install's homepage isn't
 * empty: same "seed our own default directly" pattern ads/sponsors/
 * ticker/search already used, since there's no admin placement UI yet.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        foreach ([
            ['front_hero_main', 'Front Page Hero'],
            ['front_hero_side', 'Front Page Hero Side'],
            ['front_col_1', 'Front Page Column 1'],
            ['front_col_2', 'Front Page Column 2'],
            ['front_col_3', 'Front Page Column 3'],
        ] as [$key, $label]) {
            $db->insert('block_regions', ['key' => $key, 'label' => $label]);
        }

        $now = date('Y-m-d H:i:s');

        $placements = [
            ['front_hero_main', 'articles.latest_content', json_encode(['display' => 'hero_slider', 'limit' => 5])],
            ['front_hero_side', 'articles.latest_content', json_encode(['display' => 'compact_list', 'limit' => 5])],
            ['front_col_1', 'activity.feed', null],
            ['front_col_2', 'presence.whosonline', null],
            ['front_col_3', 'tags.cloud', null],
        ];

        foreach ($placements as [$regionKey, $blockType, $config]) {
            $db->execute(
                'INSERT INTO ' . $db->table('block_placements') . '
                    (block_type, region_id, page_scope, weight, config_json, is_enabled, created_at, updated_at)
                 SELECT :block_type, id, \'front_page_only\', 10, :config, 1, :created_at, :updated_at
                 FROM ' . $db->table('block_regions') . ' WHERE `key` = :zone',
                [
                    'block_type' => $blockType,
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
        foreach (['front_hero_main', 'front_hero_side', 'front_col_1', 'front_col_2', 'front_col_3'] as $key) {
            $db->execute(
                'DELETE p FROM ' . $db->table('block_placements') . ' p
                 JOIN ' . $db->table('block_regions') . ' r ON r.id = p.region_id
                 WHERE r.`key` = :key',
                ['key' => $key]
            );
            $db->execute('DELETE FROM ' . $db->table('block_regions') . ' WHERE `key` = :key', ['key' => $key]);
        }
    }
};
