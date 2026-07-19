<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * Per-chapter private forum, calendar, shared files, and shared gallery —
 * the fuller org_spaces slice confirmed as a real launch requirement
 * (docs/roadmap.md, "Organization Spaces parity"). Deliberately built as
 * dedicated tables here rather than adding a nullable org_id onto the
 * public forum/calendar/gallery/downloads tables: those modules' entire
 * read path assumes public visibility with no member check anywhere, and
 * retrofitting privacy onto an already-live, already-verified public
 * query surface is a much larger regression risk than a handful of new,
 * cleanly-separated tables. Content here is never public — every read is
 * gated on org membership, same as the roster already is.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $orgs = $db->table('org_spaces_orgs');
        $users = $db->table('users');

        // Author/uploader columns follow this codebase's existing split:
        // required-at-creation authors (forum topics/posts, calendar
        // events — matches forum_topics/calendar_events) are NOT NULL with
        // no FK; incidental uploader metadata (files, photos — matches
        // downloads_versions/gallery_photos) is nullable with ON DELETE SET NULL.

        $db->execute('
            CREATE TABLE ' . $db->table('org_spaces_forum_topics') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                org_id BIGINT UNSIGNED NOT NULL,
                author_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(191) NOT NULL,
                is_locked TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,
                KEY idx_org_forum_topics_org (org_id),
                CONSTRAINT fk_org_forum_topics_org FOREIGN KEY (org_id)
                    REFERENCES ' . $orgs . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('org_spaces_forum_posts') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                topic_id BIGINT UNSIGNED NOT NULL,
                author_id BIGINT UNSIGNED NOT NULL,
                body TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,
                KEY idx_org_forum_posts_topic (topic_id),
                CONSTRAINT fk_org_forum_posts_topic FOREIGN KEY (topic_id)
                    REFERENCES ' . $db->table('org_spaces_forum_topics') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('org_spaces_calendar_events') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                org_id BIGINT UNSIGNED NOT NULL,
                author_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(191) NOT NULL,
                description TEXT NULL,
                location VARCHAR(255) NULL,
                starts_at DATETIME NOT NULL,
                ends_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,
                KEY idx_org_calendar_events_org (org_id),
                KEY idx_org_calendar_events_starts (starts_at),
                CONSTRAINT fk_org_calendar_events_org FOREIGN KEY (org_id)
                    REFERENCES ' . $orgs . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('org_spaces_files') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                org_id BIGINT UNSIGNED NOT NULL,
                uploader_id BIGINT UNSIGNED NULL,
                title VARCHAR(191) NOT NULL,
                description TEXT NULL,
                filename VARCHAR(191) NOT NULL,
                original_name VARCHAR(191) NOT NULL,
                mime_type VARCHAR(127) NOT NULL,
                size INT UNSIGNED NOT NULL,
                download_count INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,
                KEY idx_org_files_org (org_id),
                CONSTRAINT fk_org_files_org FOREIGN KEY (org_id)
                    REFERENCES ' . $orgs . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_org_files_uploader FOREIGN KEY (uploader_id)
                    REFERENCES ' . $users . ' (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('org_spaces_gallery_albums') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                org_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(191) NOT NULL,
                description TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,
                KEY idx_org_gallery_albums_org (org_id),
                CONSTRAINT fk_org_gallery_albums_org FOREIGN KEY (org_id)
                    REFERENCES ' . $orgs . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('org_spaces_gallery_photos') . ' (
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
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,
                KEY idx_org_gallery_photos_album (album_id),
                CONSTRAINT fk_org_gallery_photos_album FOREIGN KEY (album_id)
                    REFERENCES ' . $db->table('org_spaces_gallery_albums') . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_org_gallery_photos_uploader FOREIGN KEY (uploader_id)
                    REFERENCES ' . $users . ' (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('org_spaces_gallery_photos'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('org_spaces_gallery_albums'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('org_spaces_files'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('org_spaces_calendar_events'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('org_spaces_forum_posts'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('org_spaces_forum_topics'));
    }
};
