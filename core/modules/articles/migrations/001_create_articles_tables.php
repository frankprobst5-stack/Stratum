<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            CREATE TABLE ' . $db->table('articles_categories') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(191) NOT NULL,
                slug VARCHAR(191) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_category_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('articles') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                category_id BIGINT UNSIGNED NULL,
                author_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(191) NOT NULL,
                slug VARCHAR(191) NOT NULL,
                excerpt VARCHAR(500) NULL,
                body TEXT NOT NULL,
                is_published TINYINT(1) NOT NULL DEFAULT 0,
                published_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,
                UNIQUE KEY uniq_article_slug (slug),
                KEY idx_author (author_id),
                KEY idx_published (is_published, published_at),
                CONSTRAINT fk_articles_category FOREIGN KEY (category_id)
                    REFERENCES ' . $db->table('articles_categories') . ' (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('articles'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('articles_categories'));
    }
};
