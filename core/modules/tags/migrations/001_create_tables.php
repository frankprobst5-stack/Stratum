<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * Cross-module content tagging — the same taggable_type/taggable_id
 * polymorphic shape `comments`/`bookmarks`/`ratings` already established.
 * `tags` and `taggables` are deliberately two tables, not one, since a
 * tag name is shared across every piece of content that uses it (a
 * single `tags` row per unique name, many `taggables` rows pointing at
 * it) — the standard normalized tagging shape, not a denormalized
 * comma-string column anywhere.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            CREATE TABLE ' . $db->table('tags') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(60) NOT NULL,
                slug VARCHAR(60) NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uniq_tag_name (name),
                UNIQUE KEY uniq_tag_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('taggables') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tag_id BIGINT UNSIGNED NOT NULL,
                taggable_type VARCHAR(32) NOT NULL,
                taggable_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uniq_taggable (tag_id, taggable_type, taggable_id),
                KEY idx_taggables_target (taggable_type, taggable_id),
                CONSTRAINT fk_taggables_tag FOREIGN KEY (tag_id)
                    REFERENCES ' . $db->table('tags') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('taggables'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('tags'));
    }
};
