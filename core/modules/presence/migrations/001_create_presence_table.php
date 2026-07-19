<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * One row per active session (logged-in or guest), keyed by PHP's own
 * session id — a guest and a member are the same kind of row here,
 * user_id just tells them apart. "Online" is computed at read time as
 * "seen in the last N minutes" (see PresenceService::ONLINE_WINDOW_MINUTES),
 * not a separate flag — same "compute, don't cache" discipline as every
 * other live-status feature in this app (forum/wiki counts, gallery
 * likes, donation progress). Rows aren't deleted on logout/session end;
 * a stale row simply ages out of the online window and gets overwritten
 * by the next visit to the same session id, or is harmless dead weight
 * otherwise — cheap enough at club scale not to need active cleanup.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            CREATE TABLE ' . $db->table('presence') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                session_id VARCHAR(191) NOT NULL,
                user_id BIGINT UNSIGNED NULL,
                last_seen_at DATETIME NOT NULL,
                UNIQUE KEY uniq_presence_session (session_id),
                KEY idx_presence_last_seen (last_seen_at),
                CONSTRAINT fk_presence_user FOREIGN KEY (user_id)
                    REFERENCES ' . $db->table('users') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('presence'));
    }
};
