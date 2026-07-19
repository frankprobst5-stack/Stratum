<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * Profile banner image — same shape as `avatar_url` (a plain pasted image
 * URL, not a file-upload subsystem; this app has no per-user upload
 * storage quota/moderation story yet, and the vision notes don't ask for
 * one here), distinct from it per the backlog note: a wide header banner
 * on the profile page, not the small avatar shown next to a username.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            ALTER TABLE ' . $db->table('users') . '
                ADD COLUMN banner_url VARCHAR(255) NULL AFTER signature
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('ALTER TABLE ' . $db->table('users') . ' DROP COLUMN banner_url');
    }
};
