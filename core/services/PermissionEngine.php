<?php

declare(strict_types=1);

namespace Stratum\Core;

final class PermissionEngine
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * True if $userId holds $capabilityKey via any of their roles.
     *
     * A site-wide grant (scope_type IS NULL) always satisfies the check,
     * scoped or not. A scoped grant only satisfies a check for that exact
     * scope. See docs/permission-model.md.
     */
    public function userCan(int $userId, string $capabilityKey, ?string $scopeType = null, ?int $scopeId = null): bool
    {
        $usersRolesTable = $this->db->table('users_roles');
        $roleCapsTable = $this->db->table('role_capabilities');
        $capsTable = $this->db->table('capabilities');

        $params = ['user_id' => $userId, 'capability_key' => $capabilityKey];

        $scopeCondition = 'rc.scope_type IS NULL';
        if ($scopeType !== null) {
            $scopeCondition = '(rc.scope_type IS NULL OR (rc.scope_type = :scope_type AND rc.scope_id = :scope_id))';
            $params['scope_type'] = $scopeType;
            $params['scope_id'] = $scopeId;
        }

        $row = $this->db->fetchOne(
            "SELECT 1
             FROM {$usersRolesTable} ur
             JOIN {$roleCapsTable} rc ON rc.role_id = ur.role_id
             JOIN {$capsTable} c ON c.id = rc.capability_id
             WHERE ur.user_id = :user_id AND c.`key` = :capability_key AND {$scopeCondition}
             LIMIT 1",
            $params
        );

        return $row !== null;
    }

    /**
     * @param bool $siteWideOnly when true (default) excludes auto-provisioned
     *     per-object roles (e.g. "Moderators — board #3") so the global
     *     /admin/roles matrix stays exactly what it's always been.
     * @return array<int, array{id: int, name: string, is_builtin: bool}>
     */
    public function listRoles(bool $siteWideOnly = true): array
    {
        $sql = 'SELECT id, name, is_builtin FROM ' . $this->db->table('roles');
        if ($siteWideOnly) {
            $sql .= ' WHERE scope_type IS NULL';
        }
        $sql .= ' ORDER BY name';

        $rows = $this->db->fetchAll($sql);

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'is_builtin' => (bool) $r['is_builtin'],
        ], $rows);
    }

    /** @return array<string, mixed>|null */
    public function findRoleForScope(string $scopeType, int $scopeId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('roles') . ' WHERE scope_type = :scope_type AND scope_id = :scope_id',
            ['scope_type' => $scopeType, 'scope_id' => $scopeId]
        );
    }

    /** @return array<string, mixed>|null */
    public function findCapabilityByKey(string $key): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('capabilities') . ' WHERE `key` = :key',
            ['key' => $key]
        );
    }

    /** @return array<int, array{id: int, key: string, module_id: string, label: string}> */
    public function listCapabilities(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT id, `key`, module_id, label FROM ' . $this->db->table('capabilities') . ' ORDER BY module_id, `key`'
        );

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'key' => $r['key'],
            'module_id' => $r['module_id'],
            'label' => $r['label'],
        ], $rows);
    }

    /** @return array<int, array{role_id: int, capability_id: int}> site-wide grants only */
    public function listGrants(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT role_id, capability_id FROM ' . $this->db->table('role_capabilities') . ' WHERE scope_type IS NULL'
        );

        return array_map(static fn (array $r): array => [
            'role_id' => (int) $r['role_id'],
            'capability_id' => (int) $r['capability_id'],
        ], $rows);
    }

    /**
     * Null-safe (`<=>`) scope matching throughout so an unscoped grant/revoke
     * never touches a scoped row for the same role+capability, and vice versa.
     */
    public function grant(int $roleId, int $capabilityId, ?string $scopeType = null, ?int $scopeId = null): void
    {
        $table = $this->db->table('role_capabilities');
        $existing = $this->db->fetchOne(
            "SELECT id FROM {$table}
             WHERE role_id = :role_id AND capability_id = :capability_id
             AND scope_type <=> :scope_type AND scope_id <=> :scope_id",
            ['role_id' => $roleId, 'capability_id' => $capabilityId, 'scope_type' => $scopeType, 'scope_id' => $scopeId]
        );

        if ($existing !== null) {
            return;
        }

        $this->db->insert('role_capabilities', [
            'role_id' => $roleId,
            'capability_id' => $capabilityId,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function revoke(int $roleId, int $capabilityId, ?string $scopeType = null, ?int $scopeId = null): void
    {
        $this->db->execute(
            'DELETE FROM ' . $this->db->table('role_capabilities') . '
             WHERE role_id = :role_id AND capability_id = :capability_id
             AND scope_type <=> :scope_type AND scope_id <=> :scope_id',
            ['role_id' => $roleId, 'capability_id' => $capabilityId, 'scope_type' => $scopeType, 'scope_id' => $scopeId]
        );
    }

    public function createRole(string $name, ?string $scopeType = null, ?int $scopeId = null): string
    {
        $now = date('Y-m-d H:i:s');

        return $this->db->insert('roles', [
            'name' => $name,
            'is_builtin' => 0,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return int[] role ids assigned to the user */
    public function rolesForUser(int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT role_id FROM ' . $this->db->table('users_roles') . ' WHERE user_id = :user_id',
            ['user_id' => $userId]
        );

        return array_map(static fn (array $r): int => (int) $r['role_id'], $rows);
    }

    /** @param int[] $roleIds */
    public function setRolesForUser(int $userId, array $roleIds): void
    {
        $table = $this->db->table('users_roles');
        $this->db->execute("DELETE FROM {$table} WHERE user_id = :user_id", ['user_id' => $userId]);

        $now = date('Y-m-d H:i:s');
        foreach (array_unique($roleIds) as $roleId) {
            $this->db->insert('users_roles', ['user_id' => $userId, 'role_id' => $roleId, 'created_at' => $now]);
        }
    }

    /** @return int[] user ids holding $roleId */
    public function usersInRole(int $roleId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT user_id FROM ' . $this->db->table('users_roles') . ' WHERE role_id = :role_id',
            ['role_id' => $roleId]
        );

        return array_map(static fn (array $r): int => (int) $r['user_id'], $rows);
    }

    /**
     * Adds one role to a user without disturbing their other role
     * assignments — distinct from setRolesForUser()'s bulk replace, needed
     * for scoped roles (e.g. a board's moderator role) that live alongside
     * whatever site-wide roles the user already has.
     */
    public function addRoleToUser(int $userId, int $roleId): void
    {
        $table = $this->db->table('users_roles');
        $existing = $this->db->fetchOne(
            "SELECT id FROM {$table} WHERE user_id = :user_id AND role_id = :role_id",
            ['user_id' => $userId, 'role_id' => $roleId]
        );

        if ($existing !== null) {
            return;
        }

        $this->db->insert('users_roles', ['user_id' => $userId, 'role_id' => $roleId, 'created_at' => date('Y-m-d H:i:s')]);
    }

    public function removeRoleFromUser(int $userId, int $roleId): void
    {
        $this->db->execute(
            'DELETE FROM ' . $this->db->table('users_roles') . ' WHERE user_id = :user_id AND role_id = :role_id',
            ['user_id' => $userId, 'role_id' => $roleId]
        );
    }
}
