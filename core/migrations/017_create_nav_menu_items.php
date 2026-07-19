<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * Stage 8 menu builder — until now, public nav was purely dynamic
 * (`ModuleManager::navItems()` re-derives it from every enabled module's
 * `module.json` on every single request, with zero admin control over
 * order/labels/primary-vs-"More" placement — the split was even hardcoded
 * directly in layout.php's own `$navIcons`/`$primaryRoutes` arrays).
 * This table is a DB-backed overlay, not a replacement for module-
 * contributed nav: `NavMenuService::orderedItems()` reconciles the live
 * module nav list against these rows on every read (lazily inserting a
 * default row — placement 'more', appended weight — for any module nav
 * item that doesn't have one yet), so enabling a new module still makes
 * its nav item appear automatically with zero admin action required,
 * exactly like today, without the admin's own customizations of
 * everything else being disturbed. `source = 'custom'` rows (admin-added
 * links, internal or external) have no corresponding module item and are
 * therefore never touched by that reconciliation step.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            CREATE TABLE ' . $db->table('nav_menu_items') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source VARCHAR(16) NOT NULL,
                route VARCHAR(255) NOT NULL,
                label VARCHAR(191) NOT NULL,
                placement VARCHAR(16) NOT NULL DEFAULT \'more\',
                weight INT NOT NULL DEFAULT 100,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_source_route (source, route),
                KEY idx_placement_weight (placement, weight)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        // Seed today's actual behavior exactly as it stood before this
        // feature existed (layout.php's own hardcoded primary-route list)
        // so activating the menu builder is a no-op for every existing
        // install's visible nav until an admin deliberately changes
        // something — not a silent nav reshuffle on upgrade.
        // '/' (Home) is seeded as source='custom', not 'module' — no
        // module actually contributes a "/" nav entry (it's implicit,
        // layout.php used to prepend it by hand), and NavMenuService's
        // render-time check skips any 'module' row whose route isn't in
        // the *current* live module nav list; a 'module'-sourced Home row
        // would therefore never actually render. 'custom' rows are always
        // shown regardless of live module state, which is what Home
        // actually needs.
        $now = date('Y-m-d H:i:s');
        $db->insert('nav_menu_items', [
            'source' => 'custom', 'route' => '/', 'label' => 'Home',
            'placement' => 'primary', 'weight' => 10,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $primary = ['/forum' => 'Forum', '/articles' => 'Articles', '/calendar' => 'Calendar', '/downloads' => 'Downloads'];
        $weight = 20;
        foreach ($primary as $route => $label) {
            $db->insert('nav_menu_items', [
                'source' => 'module', 'route' => $route, 'label' => $label,
                'placement' => 'primary', 'weight' => $weight,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $weight += 10;
        }

        $rolesTable = $db->table('roles');
        $capsTable = $db->table('capabilities');
        $existing = $db->fetchOne("SELECT id FROM {$capsTable} WHERE `key` = 'nav.manage'");
        if ($existing !== null) {
            return;
        }

        $capabilityId = $db->insert('capabilities', [
            'key' => 'nav.manage',
            'module_id' => 'core',
            'label' => 'Manage site navigation',
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
        $capability = $db->fetchOne("SELECT id FROM {$capsTable} WHERE `key` = 'nav.manage'");
        if ($capability !== null) {
            $db->execute('DELETE FROM ' . $db->table('role_capabilities') . ' WHERE capability_id = :id', ['id' => $capability['id']]);
            $db->execute("DELETE FROM {$capsTable} WHERE id = :id", ['id' => $capability['id']]);
        }

        $db->execute('DROP TABLE IF EXISTS ' . $db->table('nav_menu_items'));
    }
};
