<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * A generic polymorphic ratings table — same shape `comments`
 * (commentable_type/commentable_id) already established, applied to
 * 1-5 star scores instead of freeform text. Built as a shared module from
 * day one rather than a per-content-type bolt-on, since two real
 * consumers (articles, downloads) were confirmed wants at the same time —
 * unlike most polymorphic systems here that started with one consumer and
 * were promoted later, this one already qualified on arrival.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            CREATE TABLE ' . $db->table('ratings') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ratable_type VARCHAR(64) NOT NULL,
                ratable_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                score TINYINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_rating (ratable_type, ratable_id, user_id),
                KEY idx_ratable (ratable_type, ratable_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('ratings'));
    }
};
