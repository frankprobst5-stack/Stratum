<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * Bookkeeping only — the actual permission scoping already lives on
 * strat_role_capabilities.scope_type/scope_id (added in migration 002 and
 * wired into PermissionEngine::userCan() since Stage 2, but never written
 * to until now). These columns exist so an auto-provisioned per-object role
 * (e.g. "Moderators — General Discussion (#3)") can be identified and
 * excluded from the site-wide /admin/roles matrix. Existing roles all get
 * NULL/NULL, meaning "site-wide role" — identical to today's behavior.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            ALTER TABLE ' . $db->table('roles') . '
            ADD COLUMN scope_type VARCHAR(64) NULL AFTER is_builtin,
            ADD COLUMN scope_id BIGINT UNSIGNED NULL AFTER scope_type,
            ADD KEY idx_role_scope (scope_type, scope_id)
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('ALTER TABLE ' . $db->table('roles') . ' DROP KEY idx_role_scope');
        $db->execute('ALTER TABLE ' . $db->table('roles') . ' DROP COLUMN scope_type, DROP COLUMN scope_id');
    }
};
