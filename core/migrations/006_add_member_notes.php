<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * Admin-visible notes on a member's account — reminders, handoff notes,
 * ongoing-issue tracking staff keep about a specific member (distinct from
 * the separately-tracked "admin scratchpad" backlog item, which is a
 * general staff-to-staff space not tied to any one member). Lives in core
 * migrations, not a toggleable module, since it's an extension of user
 * management (`core/admin/controllers/UsersController.php`), which itself
 * isn't module-gated. Gated by the existing `users.manage` capability —
 * one more capability just for notes would be granularity nobody asked
 * for, same "one queue, one capability" reasoning trash/moderation used.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $users = $db->table('users');

        $db->execute('
            CREATE TABLE ' . $db->table('member_notes') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                author_id BIGINT UNSIGNED NOT NULL,
                body TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_member_notes_user (user_id),
                CONSTRAINT fk_member_notes_user FOREIGN KEY (user_id)
                    REFERENCES ' . $users . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_member_notes_author FOREIGN KEY (author_id)
                    REFERENCES ' . $users . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('member_notes'));
    }
};
