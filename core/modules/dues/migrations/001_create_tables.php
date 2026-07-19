<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            CREATE TABLE ' . $db->table('dues_plans') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(191) NOT NULL,
                description TEXT NULL,
                amount DECIMAL(10,2) NOT NULL,
                currency_code VARCHAR(3) NOT NULL DEFAULT \'USD\',
                period VARCHAR(16) NOT NULL,
                payment_url VARCHAR(500) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('dues_payments') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                plan_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NULL,
                status VARCHAR(16) NOT NULL DEFAULT \'pending\',
                amount_paid DECIMAL(10,2) NULL,
                period_label VARCHAR(50) NULL,
                notes VARCHAR(500) NULL,
                recorded_by BIGINT UNSIGNED NULL,
                confirmed_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                KEY idx_dues_payments_plan (plan_id),
                KEY idx_dues_payments_user (user_id),
                KEY idx_dues_payments_status (status),
                CONSTRAINT fk_dues_payments_plan FOREIGN KEY (plan_id)
                    REFERENCES ' . $db->table('dues_plans') . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_dues_payments_user FOREIGN KEY (user_id)
                    REFERENCES ' . $db->table('users') . ' (id) ON DELETE SET NULL,
                CONSTRAINT fk_dues_payments_recorder FOREIGN KEY (recorded_by)
                    REFERENCES ' . $db->table('users') . ' (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('dues_payments'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('dues_plans'));
    }
};
