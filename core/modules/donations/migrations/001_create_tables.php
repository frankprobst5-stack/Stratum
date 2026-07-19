<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            CREATE TABLE ' . $db->table('donation_campaigns') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(191) NOT NULL,
                description TEXT NULL,
                goal_amount DECIMAL(10,2) NOT NULL,
                currency_code VARCHAR(3) NOT NULL DEFAULT \'USD\',
                payment_url VARCHAR(500) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('donation_contributions') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                campaign_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NULL,
                donor_name VARCHAR(191) NULL,
                status VARCHAR(16) NOT NULL DEFAULT \'pending\',
                amount DECIMAL(10,2) NULL,
                notes VARCHAR(500) NULL,
                recorded_by BIGINT UNSIGNED NULL,
                confirmed_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                KEY idx_donation_contributions_campaign (campaign_id),
                KEY idx_donation_contributions_status (status),
                CONSTRAINT fk_donation_contributions_campaign FOREIGN KEY (campaign_id)
                    REFERENCES ' . $db->table('donation_campaigns') . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_donation_contributions_user FOREIGN KEY (user_id)
                    REFERENCES ' . $db->table('users') . ' (id) ON DELETE SET NULL,
                CONSTRAINT fk_donation_contributions_recorder FOREIGN KEY (recorded_by)
                    REFERENCES ' . $db->table('users') . ' (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('donation_contributions'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('donation_campaigns'));
    }
};
