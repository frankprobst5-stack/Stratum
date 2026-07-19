<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

return new class implements Migration {
    public function up(Database $db): void
    {
        $usersTable = $db->table('users');
        $ranksTable = $db->table('ranks');

        $db->execute("
            ALTER TABLE {$usersTable}
                ADD COLUMN about_me TEXT NULL AFTER email,
                ADD COLUMN avatar_url VARCHAR(255) NULL AFTER about_me,
                ADD COLUMN rank_id BIGINT UNSIGNED NULL AFTER avatar_url,
                ADD COLUMN points INT UNSIGNED NOT NULL DEFAULT 0 AFTER rank_id,
                ADD CONSTRAINT fk_users_rank FOREIGN KEY (rank_id) REFERENCES {$ranksTable} (id) ON DELETE SET NULL
        ");

        $defaultRank = $db->fetchOne("SELECT id FROM {$ranksTable} WHERE name = 'New Member'");
        if ($defaultRank !== null) {
            $db->execute(
                "UPDATE {$usersTable} SET rank_id = :rank_id WHERE rank_id IS NULL",
                ['rank_id' => $defaultRank['id']]
            );
        }

        $this->migrateIsAdminToRoles($db);

        $db->execute("ALTER TABLE {$usersTable} DROP COLUMN is_admin");
    }

    private function migrateIsAdminToRoles(Database $db): void
    {
        $rolesTable = $db->table('roles');
        $adminRole = $db->fetchOne("SELECT id FROM {$rolesTable} WHERE name = 'admin'");
        $memberRole = $db->fetchOne("SELECT id FROM {$rolesTable} WHERE name = 'member'");

        if ($adminRole === null || $memberRole === null) {
            throw new \RuntimeException('Expected built-in roles to exist — did core migration 002 run first?');
        }

        $now = date('Y-m-d H:i:s');
        $users = $db->fetchAll('SELECT id, is_admin FROM ' . $db->table('users'));

        foreach ($users as $user) {
            $roleId = ((int) $user['is_admin']) === 1 ? $adminRole['id'] : $memberRole['id'];

            $db->insert('users_roles', [
                'user_id' => $user['id'],
                'role_id' => $roleId,
                'created_at' => $now,
            ]);
        }
    }

    public function down(Database $db): void
    {
        $usersTable = $db->table('users');

        $db->execute("ALTER TABLE {$usersTable} ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0");

        $rolesTable = $db->table('roles');
        $usersRolesTable = $db->table('users_roles');
        $adminRole = $db->fetchOne("SELECT id FROM {$rolesTable} WHERE name = 'admin'");

        if ($adminRole !== null) {
            $db->execute(
                "UPDATE {$usersTable} u
                 JOIN {$usersRolesTable} ur ON ur.user_id = u.id AND ur.role_id = :role_id
                 SET u.is_admin = 1",
                ['role_id' => $adminRole['id']]
            );
        }

        $db->execute("
            ALTER TABLE {$usersTable}
                DROP FOREIGN KEY fk_users_rank,
                DROP COLUMN rank_id,
                DROP COLUMN points,
                DROP COLUMN avatar_url,
                DROP COLUMN about_me
        ");
    }
};
