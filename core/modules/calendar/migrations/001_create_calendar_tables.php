<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            CREATE TABLE ' . $db->table('calendars') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(191) NOT NULL,
                slug VARCHAR(191) NOT NULL,
                description VARCHAR(500) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_calendar_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('calendar_events') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                calendar_id BIGINT UNSIGNED NOT NULL,
                author_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(191) NOT NULL,
                description TEXT NULL,
                location VARCHAR(255) NULL,
                starts_at DATETIME NOT NULL,
                ends_at DATETIME NULL,
                is_all_day TINYINT(1) NOT NULL DEFAULT 0,
                series_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,
                KEY idx_event_calendar (calendar_id),
                KEY idx_event_starts (starts_at),
                KEY idx_event_series (series_id),
                CONSTRAINT fk_calendar_events_calendar FOREIGN KEY (calendar_id)
                    REFERENCES ' . $db->table('calendars') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('calendar_rsvps') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                status VARCHAR(16) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_rsvp_event_user (event_id, user_id),
                CONSTRAINT fk_calendar_rsvps_event FOREIGN KEY (event_id)
                    REFERENCES ' . $db->table('calendar_events') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('calendar_rsvps'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('calendar_events'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('calendars'));
    }
};
