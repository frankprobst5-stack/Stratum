<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * Generic surveys/forms — the vision notes' "forms: volunteer,
 * registration, surveys, applications, custom fields" bucket, the part
 * of it `membership` (custom sign-up fields + applications) doesn't
 * already cover: a standalone, reusable form an admin builds ad hoc and
 * any logged-in member can fill out.
 *
 * form_fields carries `options` as newline-separated plain text rather
 * than JSON — this app's admin UI is server-rendered with no dynamic
 * field-count JS, so fields get added one at a time (mirrors how forum
 * polls' options are built), and a plain textarea-per-line is the
 * simplest thing that works for that flow.
 *
 * form_submission_answers stores one row per (submission, field) for
 * text/textarea/select, and one row per selected option for checkbox
 * (same field_id repeated) — avoids inventing a JSON-array value column
 * just for the one multi-select type, and makes tallying results a
 * plain GROUP BY.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $users = $db->table('users');

        $db->execute('
            CREATE TABLE ' . $db->table('forms') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(191) NOT NULL,
                slug VARCHAR(191) NOT NULL,
                description TEXT NULL,
                status VARCHAR(16) NOT NULL DEFAULT \'draft\',
                created_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                deleted_at DATETIME NULL,
                UNIQUE KEY uniq_form_slug (slug),
                CONSTRAINT fk_forms_created_by FOREIGN KEY (created_by)
                    REFERENCES ' . $users . ' (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('form_fields') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                form_id BIGINT UNSIGNED NOT NULL,
                label VARCHAR(191) NOT NULL,
                type VARCHAR(16) NOT NULL,
                options TEXT NULL,
                required TINYINT(1) NOT NULL DEFAULT 0,
                position INT NOT NULL DEFAULT 0,
                KEY idx_form_fields_form (form_id, position),
                CONSTRAINT fk_form_fields_form FOREIGN KEY (form_id)
                    REFERENCES ' . $db->table('forms') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('form_submissions') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                form_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                submitted_at DATETIME NOT NULL,
                UNIQUE KEY uniq_form_submission (form_id, user_id),
                CONSTRAINT fk_form_submissions_form FOREIGN KEY (form_id)
                    REFERENCES ' . $db->table('forms') . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_form_submissions_user FOREIGN KEY (user_id)
                    REFERENCES ' . $users . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('form_submission_answers') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                submission_id BIGINT UNSIGNED NOT NULL,
                field_id BIGINT UNSIGNED NOT NULL,
                value TEXT NOT NULL,
                KEY idx_form_answers_submission (submission_id),
                KEY idx_form_answers_field (field_id),
                CONSTRAINT fk_form_answers_submission FOREIGN KEY (submission_id)
                    REFERENCES ' . $db->table('form_submissions') . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_form_answers_field FOREIGN KEY (field_id)
                    REFERENCES ' . $db->table('form_fields') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('form_submission_answers'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('form_submissions'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('form_fields'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('forms'));
    }
};
