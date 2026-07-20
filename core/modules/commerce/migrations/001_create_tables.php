<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            CREATE TABLE ' . $db->table('commerce_products') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                download_file_id BIGINT UNSIGNED NOT NULL,
                price DECIMAL(10,2) NOT NULL,
                payment_url VARCHAR(500) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                KEY idx_commerce_products_file (download_file_id),
                CONSTRAINT fk_commerce_products_file FOREIGN KEY (download_file_id)
                    REFERENCES ' . $db->table('downloads_files') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('commerce_purchases') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                product_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NULL,
                status VARCHAR(16) NOT NULL DEFAULT \'pending\',
                amount DECIMAL(10,2) NULL,
                notes VARCHAR(500) NULL,
                recorded_by BIGINT UNSIGNED NULL,
                confirmed_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                KEY idx_commerce_purchases_product (product_id),
                KEY idx_commerce_purchases_status (status),
                KEY idx_commerce_purchases_user_product (user_id, product_id),
                CONSTRAINT fk_commerce_purchases_product FOREIGN KEY (product_id)
                    REFERENCES ' . $db->table('commerce_products') . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_commerce_purchases_user FOREIGN KEY (user_id)
                    REFERENCES ' . $db->table('users') . ' (id) ON DELETE SET NULL,
                CONSTRAINT fk_commerce_purchases_recorder FOREIGN KEY (recorded_by)
                    REFERENCES ' . $db->table('users') . ' (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('commerce_purchases'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('commerce_products'));
    }
};
