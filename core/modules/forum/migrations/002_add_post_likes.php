<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        // Same shape as gallery_likes: a real join table with a computed
        // COUNT(*), not an incrementing counter — likes toggle off, so only
        // a computed count is actually correct.
        $db->execute('
            CREATE TABLE ' . $db->table('forum_post_likes') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                post_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uniq_forum_post_like (post_id, user_id),
                CONSTRAINT fk_forum_post_likes_post FOREIGN KEY (post_id)
                    REFERENCES ' . $db->table('forum_posts') . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_forum_post_likes_user FOREIGN KEY (user_id)
                    REFERENCES ' . $db->table('users') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('forum_post_likes'));
    }
};
