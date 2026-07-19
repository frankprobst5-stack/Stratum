<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            CREATE TABLE ' . $db->table('ad_advertisers') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(191) NOT NULL,
                contact_name VARCHAR(191) NULL,
                contact_email VARCHAR(191) NULL,
                notes TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('ad_campaigns') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                advertiser_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(191) NOT NULL,
                starts_at DATETIME NULL,
                ends_at DATETIME NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                KEY idx_ad_campaigns_advertiser (advertiser_id),
                CONSTRAINT fk_ad_campaigns_advertiser FOREIGN KEY (advertiser_id)
                    REFERENCES ' . $db->table('ad_advertisers') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('ad_banners') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                campaign_id BIGINT UNSIGNED NOT NULL,
                zone VARCHAR(32) NOT NULL,
                image_url VARCHAR(500) NOT NULL,
                link_url VARCHAR(500) NOT NULL,
                alt_text VARCHAR(191) NOT NULL DEFAULT \'\',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                impression_count INT UNSIGNED NOT NULL DEFAULT 0,
                click_count INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                KEY idx_ad_banners_campaign (campaign_id),
                KEY idx_ad_banners_zone (zone),
                CONSTRAINT fk_ad_banners_campaign FOREIGN KEY (campaign_id)
                    REFERENCES ' . $db->table('ad_campaigns') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        // Seed one placement per existing region so a banner "just shows up"
        // once an admin creates one for that zone — same "seed our own
        // default directly" pattern ticker/search used, since there's no
        // admin placement UI yet (Stage 8). config_json's `zone` tells the
        // single registered `ads.banner` block type which zone to render;
        // the region key and the zone key happen to share the same
        // vocabulary (header/sidebar_left/sidebar_right/footer) but they're
        // independent — the zone is what AdService actually queries on.
        $now = date('Y-m-d H:i:s');
        foreach (['header', 'sidebar_left', 'sidebar_right', 'footer'] as $zone) {
            $db->execute(
                'INSERT INTO ' . $db->table('block_placements') . '
                    (block_type, region_id, page_scope, weight, config_json, is_enabled, created_at, updated_at)
                 SELECT \'ads.banner\', id, \'site_wide\', 20, :config, 1, :created_at, :updated_at
                 FROM ' . $db->table('block_regions') . ' WHERE `key` = :zone',
                [
                    'config' => json_encode(['zone' => $zone]),
                    'created_at' => $now,
                    'updated_at' => $now,
                    'zone' => $zone,
                ]
            );
        }
    }

    public function down(Database $db): void
    {
        $db->execute('DELETE FROM ' . $db->table('block_placements') . ' WHERE block_type = :type', ['type' => 'ads.banner']);
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('ad_banners'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('ad_campaigns'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('ad_advertisers'));
    }
};
