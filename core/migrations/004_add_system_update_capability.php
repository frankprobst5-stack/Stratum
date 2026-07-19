<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * A new, narrow capability rather than reusing admin.access — applying an
 * update is meaningfully more dangerous than anything else admin.access
 * currently gates (it writes application code to disk and runs
 * migrations), so it gets its own grant, following core migration 002's
 * exact pattern for core (non-module) capabilities.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $rolesTable = $db->table('roles');
        $capsTable = $db->table('capabilities');

        $existing = $db->fetchOne("SELECT id FROM {$capsTable} WHERE `key` = 'system.update'");
        if ($existing !== null) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $capabilityId = $db->insert('capabilities', [
            'key' => 'system.update',
            'module_id' => 'core',
            'label' => 'Apply system updates',
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
        $capability = $db->fetchOne("SELECT id FROM {$capsTable} WHERE `key` = 'system.update'");
        if ($capability !== null) {
            $db->execute('DELETE FROM ' . $db->table('role_capabilities') . ' WHERE capability_id = :id', ['id' => $capability['id']]);
            $db->execute("DELETE FROM {$capsTable} WHERE id = :id", ['id' => $capability['id']]);
        }
    }
};
