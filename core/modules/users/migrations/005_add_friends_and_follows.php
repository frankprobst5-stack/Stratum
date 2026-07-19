<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * Two distinct member-system relationships, named separately in the
 * original vision notes (Stage 2's "Friends" and "Following" are listed
 * as two separate still-needed items, not one feature): Friends is
 * mutual and request-based (a named "Friend request" notification type
 * already exists in the notes), Following is one-directional and
 * unconfirmed, the same shape gallery_likes/forum_post_likes already use
 * for a toggleable relationship.
 *
 * friend_requests only ever needs two states — pending or accepted —
 * not a stored "declined": a decline is a delete, the same action as
 * unfriending, so a declined request doesn't permanently block someone
 * from requesting again later the way a lingering 'declined' row would
 * via the unique-pair constraint below.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $users = $db->table('users');

        $db->execute('
            CREATE TABLE ' . $db->table('friend_requests') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                sender_id BIGINT UNSIGNED NOT NULL,
                recipient_id BIGINT UNSIGNED NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT \'pending\',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_friend_request_pair (sender_id, recipient_id),
                KEY idx_friend_requests_recipient (recipient_id, status),
                CONSTRAINT fk_friend_requests_sender FOREIGN KEY (sender_id)
                    REFERENCES ' . $users . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_friend_requests_recipient FOREIGN KEY (recipient_id)
                    REFERENCES ' . $users . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('member_follows') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                follower_id BIGINT UNSIGNED NOT NULL,
                followed_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uniq_follow_pair (follower_id, followed_id),
                KEY idx_member_follows_followed (followed_id),
                CONSTRAINT fk_member_follows_follower FOREIGN KEY (follower_id)
                    REFERENCES ' . $users . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_member_follows_followed FOREIGN KEY (followed_id)
                    REFERENCES ' . $users . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('member_follows'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('friend_requests'));
    }
};
