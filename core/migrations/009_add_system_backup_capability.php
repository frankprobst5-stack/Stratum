<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * A new, narrow capability rather than reusing admin.access — same
 * reasoning migration 004 already established for system.update: a
 * full database backup contains every member's password hash and PII
 * in one file, meaningfully more sensitive than anything else
 * admin.access currently gates.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $rolesTable = $db->table('roles');
        $capsTable = $db->table('capabilities');

        $existing = $db->fetchOne("SELECT id FROM {$capsTable} WHERE `key` = 'system.backup'");
        if ($existing !== null) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $capabilityId = $db->insert('capabilities', [
            'key' => 'system.backup',
            'module_id' => 'core',
            'label' => 'Create and download backups',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $grantRoles = $db->fetchAll("SELECT id FROM {$rolesTable} WHERE name IN ('admin', 'founder')");
        foreach ($grantRoles as $role) {
            $db->insert('role_capabilities', [
                'role_id' => $role['id'],
                'capability_id' => $capabilityId,
                'scope_type' => null,
                'scope_id' => null,
                'created_at' => $now,
            ]);
        }
    }

    public function down(Database $db): void
    {
        $capsTable = $db->table('capabilities');
        $capability = $db->fetchOne("SELECT id FROM {$capsTable} WHERE `key` = 'system.backup'");
        if ($capability !== null) {
            $db->execute('DELETE FROM ' . $db->table('role_capabilities') . ' WHERE capability_id = :id', ['id' => $capability['id']]);
            $db->execute("DELETE FROM {$capsTable} WHERE id = :id", ['id' => $capability['id']]);
        }
    }
};
