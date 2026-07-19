<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * Stage 9 chat, first (and deliberately scoped-down) slice — confirmed
 * with the user 2026-07-19: admin-created rooms are permanent and can be
 * public or private (private = an admin-managed membership list, no
 * self-serve join); user-created rooms are always public and self-delete
 * the moment their last member leaves (`ChatService::leaveRoom()`, not a
 * cron sweep — simpler and immediate). `chat_room_members` does double
 * duty: it's the private-room ACL AND the live "is this user room empty
 * yet" signal, the same table serving both purposes rather than two.
 * Cut from the original, much larger 2026-07-18 design notes on purpose
 * (reactions, uploads, operator roles, bans, SSE/WebSocket transport,
 * Markdown, typing indicators) — see the roadmap entry for the full
 * "deliberately not built" list.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $usersTable = $db->table('users');

        $db->execute('
            CREATE TABLE ' . $db->table('chat_rooms') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(191) NOT NULL,
                topic VARCHAR(255) NULL,
                source VARCHAR(16) NOT NULL,
                visibility VARCHAR(16) NOT NULL DEFAULT \'public\',
                owner_user_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                KEY idx_source_visibility (source, visibility),
                CONSTRAINT fk_chat_rooms_owner FOREIGN KEY (owner_user_id)
                    REFERENCES ' . $usersTable . ' (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('chat_room_members') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                room_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                joined_at DATETIME NOT NULL,
                UNIQUE KEY uniq_chat_room_user (room_id, user_id),
                CONSTRAINT fk_chat_room_members_room FOREIGN KEY (room_id)
                    REFERENCES ' . $db->table('chat_rooms') . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_chat_room_members_user FOREIGN KEY (user_id)
                    REFERENCES ' . $usersTable . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('chat_messages') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                room_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                body TEXT NOT NULL,
                is_action TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                KEY idx_chat_messages_room_created (room_id, created_at),
                CONSTRAINT fk_chat_messages_room FOREIGN KEY (room_id)
                    REFERENCES ' . $db->table('chat_rooms') . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_chat_messages_user FOREIGN KEY (user_id)
                    REFERENCES ' . $usersTable . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('chat_messages'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('chat_room_members'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('chat_rooms'));
    }
};
