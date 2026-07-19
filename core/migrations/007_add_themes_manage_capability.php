<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * A dedicated capability for theme management, separate from
 * `modules.manage` — theme switching/uploading is a visual-presentation
 * concern, not a functional/capability one, and a club may reasonably
 * want a designer/webmaster role that can manage themes without also
 * being able to enable/disable modules (a materially more consequential
 * capability). Addon (module) upload/delete deliberately reuses the
 * existing `modules.manage` instead — an uploaded addon IS a module, so
 * managing it is already the same concern the modules screen already
 * gates, not a separate one.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $rolesTable = $db->table('roles');
        $capsTable = $db->table('capabilities');

        $existing = $db->fetchOne("SELECT id FROM {$capsTable} WHERE `key` = 'themes.manage'");
        if ($existing !== null) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $capabilityId = $db->insert('capabilities', [
            'key' => 'themes.manage',
            'module_id' => 'core',
            'label' => 'Manage and upload themes',
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
        $capability = $db->fetchOne("SELECT id FROM {$capsTable} WHERE `key` = 'themes.manage'");
        if ($capability !== null) {
            $db->execute('DELETE FROM ' . $db->table('role_capabilities') . ' WHERE capability_id = :id', ['id' => $capability['id']]);
            $db->execute("DELETE FROM {$capsTable} WHERE id = :id", ['id' => $capability['id']]);
        }
    }
};
