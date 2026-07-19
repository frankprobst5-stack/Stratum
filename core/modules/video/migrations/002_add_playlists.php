<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * Video playlists — already flagged in the roadmap as a deliberate
 * Stage 5b cut. An admin-curated ordered set of existing videos (not a
 * new video type), same "container + ordered membership" shape as
 * gallery albums, just for videos instead of photos.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $users = $db->table('users');

        $db->execute('
            CREATE TABLE ' . $db->table('video_playlists') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(191) NOT NULL,
                slug VARCHAR(191) NOT NULL,
                description TEXT NULL,
                created_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,
                UNIQUE KEY uniq_video_playlist_slug (slug),
                CONSTRAINT fk_video_playlists_created_by FOREIGN KEY (created_by)
                    REFERENCES ' . $users . ' (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('video_playlist_items') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                playlist_id BIGINT UNSIGNED NOT NULL,
                video_id BIGINT UNSIGNED NOT NULL,
                position INT NOT NULL DEFAULT 0,
                UNIQUE KEY uniq_video_playlist_item (playlist_id, video_id),
                KEY idx_video_playlist_items_playlist (playlist_id, position),
                CONSTRAINT fk_video_playlist_items_playlist FOREIGN KEY (playlist_id)
                    REFERENCES ' . $db->table('video_playlists') . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_video_playlist_items_video FOREIGN KEY (video_id)
                    REFERENCES ' . $db->table('videos') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('video_playlist_items'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('video_playlists'));
    }
};
