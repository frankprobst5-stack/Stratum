<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * Attendance tracking — listed distinctly from RSVP in the vision notes:
 * RSVP is "who says they're coming" (calendar_rsvps, set by the member
 * themselves before the event); attendance is "who actually showed up"
 * (this table, a post-event roll call set by an organizer). Deliberately
 * a separate table rather than an `attended` column bolted onto
 * calendar_rsvps — someone can attend without ever having RSVP'd (a
 * walk-in), and the two concepts already have different owners (the
 * member sets their own RSVP; an organizer sets attendance for others).
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $users = $db->table('users');

        $db->execute('
            CREATE TABLE ' . $db->table('calendar_attendance') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                checked_in_at DATETIME NOT NULL,
                checked_in_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uniq_calendar_attendance (event_id, user_id),
                CONSTRAINT fk_calendar_attendance_event FOREIGN KEY (event_id)
                    REFERENCES ' . $db->table('calendar_events') . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_calendar_attendance_user FOREIGN KEY (user_id)
                    REFERENCES ' . $users . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_calendar_attendance_checked_in_by FOREIGN KEY (checked_in_by)
                    REFERENCES ' . $users . ' (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('calendar_attendance'));
    }
};
