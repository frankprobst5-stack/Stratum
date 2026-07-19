<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * Admin-to-admin scratchpad — reminders, handoff notes, ongoing-issue
 * tracking shared between site staff. Distinct from `member_notes`
 * (notes *on a specific member's account*) — this is a general
 * admin-facing space, not attached to any content or member. Same
 * append-only, no-edit shape MemberNoteService already established: a
 * note is a point-in-time scratch entry, not a document needing
 * revision history.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $users = $db->table('users');

        $db->execute('
            CREATE TABLE ' . $db->table('admin_notes') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                author_id BIGINT UNSIGNED NULL,
                body TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                CONSTRAINT fk_admin_notes_author FOREIGN KEY (author_id)
                    REFERENCES ' . $users . ' (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('admin_notes'));
    }
};
