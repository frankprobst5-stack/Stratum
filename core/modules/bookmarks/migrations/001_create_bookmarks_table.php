<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        // No title/url columns — unlike moderation_reports, a bookmark is a
        // saved pointer a user expects to always show the CURRENT title, not
        // a point-in-time snapshot. Resolved live via ContentResolver at
        // listing time; a bookmark whose content was since deleted just
        // vanishes from the list rather than showing a stale, broken entry.
        $db->execute('
            CREATE TABLE ' . $db->table('bookmarks') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                bookmarkable_type VARCHAR(64) NOT NULL,
                bookmarkable_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uniq_bookmark (bookmarkable_type, bookmarkable_id, user_id),
                KEY idx_bookmarks_user (user_id, created_at),
                CONSTRAINT fk_bookmarks_user FOREIGN KEY (user_id)
                    REFERENCES ' . $db->table('users') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('bookmarks'));
    }
};
