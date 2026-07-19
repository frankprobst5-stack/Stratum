<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * Polls attached to topics — the other open Forum parity item alongside
 * sub-boards. One poll per topic (UNIQUE on topic_id), created only at
 * topic-creation time — no "add a poll to an existing topic" flow, a
 * deliberate v1 cut matching this app's usual narrower-than-everything
 * first pass. Single-choice voting (UNIQUE on poll_id+user_id, not
 * poll_id+option_id+user_id) — simpler ballot UI (radio buttons, not
 * checkboxes) and simpler vote-changing semantics (update the one row
 * rather than reconciling a set); multi-select was not asked for.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $topics = $db->table('forum_topics');
        $users = $db->table('users');

        $db->execute('
            CREATE TABLE ' . $db->table('forum_polls') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                topic_id BIGINT UNSIGNED NOT NULL,
                question VARCHAR(255) NOT NULL,
                closes_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uniq_forum_poll_topic (topic_id),
                CONSTRAINT fk_forum_polls_topic FOREIGN KEY (topic_id)
                    REFERENCES ' . $topics . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('forum_poll_options') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                poll_id BIGINT UNSIGNED NOT NULL,
                label VARCHAR(191) NOT NULL,
                position INT NOT NULL DEFAULT 0,
                KEY idx_forum_poll_options_poll (poll_id),
                CONSTRAINT fk_forum_poll_options_poll FOREIGN KEY (poll_id)
                    REFERENCES ' . $db->table('forum_polls') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('forum_poll_votes') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                poll_id BIGINT UNSIGNED NOT NULL,
                option_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uniq_forum_poll_vote (poll_id, user_id),
                KEY idx_forum_poll_votes_option (option_id),
                CONSTRAINT fk_forum_poll_votes_poll FOREIGN KEY (poll_id)
                    REFERENCES ' . $db->table('forum_polls') . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_forum_poll_votes_option FOREIGN KEY (option_id)
                    REFERENCES ' . $db->table('forum_poll_options') . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_forum_poll_votes_user FOREIGN KEY (user_id)
                    REFERENCES ' . $users . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('forum_poll_votes'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('forum_poll_options'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('forum_polls'));
    }
};
