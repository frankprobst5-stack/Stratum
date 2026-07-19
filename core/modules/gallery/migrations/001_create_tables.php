<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            CREATE TABLE ' . $db->table('gallery_albums') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(191) NOT NULL,
                description TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('gallery_photos') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                album_id BIGINT UNSIGNED NOT NULL,
                uploader_id BIGINT UNSIGNED NULL,
                caption VARCHAR(500) NULL,
                filename VARCHAR(191) NOT NULL,
                thumbnail_filename VARCHAR(191) NOT NULL,
                mime_type VARCHAR(127) NOT NULL,
                size INT UNSIGNED NOT NULL,
                width INT UNSIGNED NULL,
                height INT UNSIGNED NULL,
                exif_json TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,
                KEY idx_gallery_photos_album (album_id),
                CONSTRAINT fk_gallery_photos_album FOREIGN KEY (album_id)
                    REFERENCES ' . $db->table('gallery_albums') . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_gallery_photos_uploader FOREIGN KEY (uploader_id)
                    REFERENCES ' . $db->table('users') . ' (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('gallery_likes') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                photo_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uniq_gallery_like (photo_id, user_id),
                CONSTRAINT fk_gallery_likes_photo FOREIGN KEY (photo_id)
                    REFERENCES ' . $db->table('gallery_photos') . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_gallery_likes_user FOREIGN KEY (user_id)
                    REFERENCES ' . $db->table('users') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('gallery_likes'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('gallery_photos'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('gallery_albums'));
    }
};
