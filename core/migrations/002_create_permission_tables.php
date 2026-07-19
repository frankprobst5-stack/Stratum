<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

return new class implements Migration {
    /** @var string[] */
    private const BUILTIN_ROLES = ['guest', 'member', 'moderator', 'admin', 'founder'];

    /** @var array<int, array{key: string, label: string}> */
    private const CORE_CAPABILITIES = [
        ['key' => 'admin.access', 'label' => 'Access admin panel'],
        ['key' => 'modules.manage', 'label' => 'Enable/disable modules'],
        ['key' => 'settings.manage', 'label' => 'Manage site settings'],
        ['key' => 'roles.manage', 'label' => 'Manage roles & permissions'],
    ];

    /** @var string[] roles that get every core capability granted by default */
    private const DEFAULT_GRANTEE_ROLES = ['admin', 'founder'];

    public function up(Database $db): void
    {
        $db->execute('
            CREATE TABLE ' . $db->table('roles') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(64) NOT NULL,
                is_builtin TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_role_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('capabilities') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `key` VARCHAR(191) NOT NULL,
                module_id VARCHAR(64) NOT NULL,
                label VARCHAR(191) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_capability_key (`key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('role_capabilities') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                role_id BIGINT UNSIGNED NOT NULL,
                capability_id BIGINT UNSIGNED NOT NULL,
                scope_type VARCHAR(64) NULL,
                scope_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                KEY idx_role (role_id),
                KEY idx_capability (capability_id),
                CONSTRAINT fk_role_capabilities_role FOREIGN KEY (role_id)
                    REFERENCES ' . $db->table('roles') . ' (id) ON DELETE CASCADE,
                CONSTRAINT fk_role_capabilities_capability FOREIGN KEY (capability_id)
                    REFERENCES ' . $db->table('capabilities') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $db->execute('
            CREATE TABLE ' . $db->table('ranks') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(64) NOT NULL,
                min_points INT UNSIGNED NOT NULL DEFAULT 0,
                icon VARCHAR(191) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_rank_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        // No FK to strat_users: that table belongs to the 'users' module and
        // doesn't exist yet when core migrations run. See Stage 2 plan.
        $db->execute('
            CREATE TABLE ' . $db->table('users_roles') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                role_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uniq_user_role (user_id, role_id),
                KEY idx_user (user_id),
                CONSTRAINT fk_users_roles_role FOREIGN KEY (role_id)
                    REFERENCES ' . $db->table('roles') . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $now = date('Y-m-d H:i:s');

        $roleIds = [];
        foreach (self::BUILTIN_ROLES as $name) {
            $roleIds[$name] = $db->insert('roles', [
                'name' => $name,
                'is_builtin' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $db->insert('ranks', [
            'name' => 'New Member',
            'min_points' => 0,
            'icon' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $capabilityIds = [];
        foreach (self::CORE_CAPABILITIES as $capability) {
            $capabilityIds[] = $db->insert('capabilities', [
                'key' => $capability['key'],
                'module_id' => 'core',
                'label' => $capability['label'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (self::DEFAULT_GRANTEE_ROLES as $roleName) {
            foreach ($capabilityIds as $capabilityId) {
                $db->insert('role_capabilities', [
                    'role_id' => $roleIds[$roleName],
                    'capability_id' => $capabilityId,
                    'scope_type' => null,
                    'scope_id' => null,
                    'created_at' => $now,
                ]);
            }
        }
    }

    public function down(Database $db): void
    {
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('users_roles'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('ranks'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('role_capabilities'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('capabilities'));
        $db->execute('DROP TABLE IF EXISTS ' . $db->table('roles'));
    }
};
