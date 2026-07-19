<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            CREATE TABLE ' . $db->table('membership_fields') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                label VARCHAR(191) NOT NULL,
                field_type VARCHAR(16) NOT NULL,
                options_json TEXT NULL,
                is_required TINYINT(1) NOT NULL DEFAULT 0,
                weight INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $usersTable = $db->table('users');

        $db->execute('
            CREATE TABLE ' . $db->table('membership_applications') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(64) NOT NULL,
                email VARCHAR(191) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                answers_json TEXT NULL,
                status VARCHAR(16) NOT NULL DEFAULT \'pending\',
                reviewed_by BIGINT UNSIGNED NULL,
                reviewed_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                KEY idx_status (status),
                CONSTRAINT fk_membership_applications_reviewer FOREIGN KEY (reviewed_by)
                    REFERENCES ' . $usersTable . ' (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('membership_applications'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('membership_fields'));
    }
};
