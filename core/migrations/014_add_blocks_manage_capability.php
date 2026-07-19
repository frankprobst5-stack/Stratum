<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * New capability for the admin block-placement manager (Stage 8) — not
 * folded into admin.access since block placement affects every visitor's
 * page, a more consequential blast radius than most admin.access-gated
 * screens, same reasoning migration 004/009 already applied to
 * system.update/system.backup.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $rolesTable = $db->table('roles');
        $capsTable = $db->table('capabilities');

        $existing = $db->fetchOne("SELECT id FROM {$capsTable} WHERE `key` = 'blocks.manage'");
        if ($existing !== null) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $capabilityId = $db->insert('capabilities', [
            'key' => 'blocks.manage',
            'module_id' => 'core',
            'label' => 'Manage block placements',
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
        $capability = $db->fetchOne("SELECT id FROM {$capsTable} WHERE `key` = 'blocks.manage'");
        if ($capability !== null) {
            $db->execute('DELETE FROM ' . $db->table('role_capabilities') . ' WHERE capability_id = :id', ['id' => $capability['id']]);
            $db->execute("DELETE FROM {$capsTable} WHERE id = :id", ['id' => $capability['id']]);
        }
    }
};
