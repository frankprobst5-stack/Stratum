<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            CREATE TABLE ' . $db->table('forum_categories') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(191) NOT NULL,
                slug VARCHAR(191) NOT NULL,
                weight INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_forum_category_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('forum_boards') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                category_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(191) NOT NULL,
                slug VARCHAR(191) NOT NULL,
                description VARCHAR(500) NULL,
                weight INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_forum_board_slug (slug),
                KEY idx_board_category (category_id),
                CONSTRAINT fk_forum_boards_category FOREIGN KEY (category_id)
                    REFERENCES ' . $db->table('forum_categories') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('forum_topics') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                board_id BIGINT UNSIGNED NOT NULL,
                author_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(191) NOT NULL,
                is_pinned TINYINT(1) NOT NULL DEFAULT 0,
                is_locked TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,
                KEY idx_topic_board (board_id),
                KEY idx_topic_author (author_id),
                CONSTRAINT fk_forum_topics_board FOREIGN KEY (board_id)
                    REFERENCES ' . $db->table('forum_boards') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('forum_posts') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                topic_id BIGINT UNSIGNED NOT NULL,
                author_id BIGINT UNSIGNED NOT NULL,
                body TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,
                KEY idx_post_topic (topic_id),
                KEY idx_post_author (author_id),
                CONSTRAINT fk_forum_posts_topic FOREIGN KEY (topic_id)
                    REFERENCES ' . $db->table('forum_topics') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('forum_attachments') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                post_id BIGINT UNSIGNED NOT NULL,
                filename VARCHAR(191) NOT NULL,
                original_name VARCHAR(191) NOT NULL,
                mime_type VARCHAR(127) NOT NULL,
                size INT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_attachment_post (post_id),
                CONSTRAINT fk_forum_attachments_post FOREIGN KEY (post_id)
                    REFERENCES ' . $db->table('forum_posts') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('forum_attachments'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('forum_posts'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('forum_topics'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('forum_boards'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('forum_categories'));
    }
};
