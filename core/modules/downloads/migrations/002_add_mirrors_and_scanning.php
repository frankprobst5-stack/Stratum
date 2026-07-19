<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * Two open items from Media & commerce parity: mirrors (alternate
 * external locations to get a file, e.g. a club's own Google Drive
 * backup of a large file) and virus scanning on upload.
 *
 * scan_status starts 'pending' and gets set by ClamAvScanner right after
 * the file lands on disk — 'clean'/'infected' if a scanner was actually
 * available, 'unavailable' if not (this app can't assume ClamAV exists
 * on an unknown shared host, so "no scanner installed" has to be a
 * distinct, non-blocking state from "scanned and infected" — the whole
 * downloads module still has to work on hosts with no scanner, exactly
 * as it did before this feature existed).
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            ALTER TABLE ' . $db->table('downloads_versions') . '
            ADD COLUMN scan_status VARCHAR(16) NOT NULL DEFAULT \'pending\' AFTER size
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('downloads_mirrors') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                file_id BIGINT UNSIGNED NOT NULL,
                label VARCHAR(191) NOT NULL,
                url VARCHAR(500) NOT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_downloads_mirrors_file (file_id),
                CONSTRAINT fk_downloads_mirrors_file FOREIGN KEY (file_id)
                    REFERENCES ' . $db->table('downloads_files') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('downloads_mirrors'));
        $db->execute('ALTER TABLE ' . $db->table('downloads_versions') . ' DROP COLUMN scan_status');
    }
};
