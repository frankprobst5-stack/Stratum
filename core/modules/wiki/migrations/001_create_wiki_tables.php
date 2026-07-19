<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            CREATE TABLE ' . $db->table('wiki_categories') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(191) NOT NULL,
                slug VARCHAR(191) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_wiki_category_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('wiki_pages') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                category_id BIGINT UNSIGNED NULL,
                slug VARCHAR(191) NOT NULL,
                title VARCHAR(191) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,
                UNIQUE KEY uniq_wiki_page_slug (slug),
                KEY idx_wiki_page_category (category_id),
                CONSTRAINT fk_wiki_pages_category FOREIGN KEY (category_id)
                    REFERENCES ' . $db->table('wiki_categories') . ' (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        // Body lives here, never on strat_wiki_pages — "current" content is
        // simply the latest revision for a page. See the Stage 3c plan.
        $db->execute('
            CREATE TABLE ' . $db->table('wiki_revisions') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                page_id BIGINT UNSIGNED NOT NULL,
                author_id BIGINT UNSIGNED NOT NULL,
                body TEXT NOT NULL,
                comment VARCHAR(255) NULL,
                created_at DATETIME NOT NULL,
                KEY idx_wiki_revision_page (page_id, created_at),
                CONSTRAINT fk_wiki_revisions_page FOREIGN KEY (page_id)
                    REFERENCES ' . $db->table('wiki_pages') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('wiki_revisions'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('wiki_pages'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('wiki_categories'));
    }
};
