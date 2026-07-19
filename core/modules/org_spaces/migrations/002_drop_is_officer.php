<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * Officer status moves from a stored flag to computed scoped-role
 * membership (PermissionEngine's org_spaces.moderate grant, scoped per
 * org) — see the retrofit plan's Decisions. Single source of truth, no
 * risk of the display flag and the real permission drifting apart.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('ALTER TABLE ' . $db->table('org_spaces_members') . ' DROP COLUMN is_officer');
    }

    public function down(Database $db): void
    {
        $db->execute(
            'ALTER TABLE ' . $db->table('org_spaces_members') . ' ADD COLUMN is_officer TINYINT(1) NOT NULL DEFAULT 0'
        );
    }
};
