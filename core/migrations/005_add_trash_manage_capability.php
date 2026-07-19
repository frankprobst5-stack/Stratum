<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * One capability for the whole trash bin (spans many modules' content) —
 * same "one queue, one capability" precedent moderation.manage already
 * set, not a per-content-type capability check.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $rolesTable = $db->table('roles');
        $capsTable = $db->table('capabilities');

        $existing = $db->fetchOne("SELECT id FROM {$capsTable} WHERE `key` = 'trash.manage'");
        if ($existing !== null) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $capabilityId = $db->insert('capabilities', [
            'key' => 'trash.manage',
            'module_id' => 'core',
            'label' => 'View and restore deleted content',
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
        $capability = $db->fetchOne("SELECT id FROM {$capsTable} WHERE `key` = 'trash.manage'");
        if ($capability !== null) {
            $db->execute('DELETE FROM ' . $db->table('role_capabilities') . ' WHERE capability_id = :id', ['id' => $capability['id']]);
            $db->execute("DELETE FROM {$capsTable} WHERE id = :id", ['id' => $capability['id']]);
        }
    }
};
