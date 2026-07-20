<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            CREATE TABLE ' . $db->table('message_conversations') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_one_id BIGINT UNSIGNED NOT NULL,
                user_two_id BIGINT UNSIGNED NOT NULL,
                last_message_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_message_conversations_pair (user_one_id, user_two_id),
                KEY idx_message_conversations_user_two (user_two_id),
                CONSTRAINT fk_message_conversations_user_one FOREIGN KEY (user_one_id)
                    REFERENCES ' . $db->table('users') . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_message_conversations_user_two FOREIGN KEY (user_two_id)
                    REFERENCES ' . $db->table('users') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('direct_messages') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                conversation_id BIGINT UNSIGNED NOT NULL,
                sender_id BIGINT UNSIGNED NOT NULL,
                body TEXT NOT NULL,
                read_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                KEY idx_direct_messages_conversation (conversation_id, created_at),
                CONSTRAINT fk_direct_messages_conversation FOREIGN KEY (conversation_id)
                    REFERENCES ' . $db->table('message_conversations') . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_direct_messages_sender FOREIGN KEY (sender_id)
                    REFERENCES ' . $db->table('users') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('direct_messages'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('message_conversations'));
    }
};
