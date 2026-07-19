<?php

declare(strict_types=1);

namespace Stratum\Modules\OrgSpaces;

use Stratum\Core\Database;
use Stratum\Core\PermissionEngine;
use Stratum\Core\Slug;

final class OrgSpaceService
{
    private const ORG_SCOPE = 'org';

    public function __construct(
        private readonly Database $db,
        private readonly PermissionEngine $permissions
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function listOrgs(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM ' . $this->db->table('org_spaces_orgs');
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY name';

        return $this->db->fetchAll($sql);
    }

    /** @return array<string, mixed>|null */
    public function findOrgBySlug(string $slug): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('org_spaces_orgs') . ' WHERE slug = :slug',
            ['slug' => $slug]
        );
    }

    /** @return array<string, mixed>|null */
    public function findOrg(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('org_spaces_orgs') . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    public function createOrg(string $name, string $description): string
    {
        $now = date('Y-m-d H:i:s');

        return $this->db->insert('org_spaces_orgs', [
            'name' => $name,
            'slug' => $this->uniqueOrgSlug($name),
            'description' => $description !== '' ? $description : null,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function setOrgActive(int $orgId, bool $isActive): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('org_spaces_orgs') . ' SET is_active = :is_active, updated_at = :now WHERE id = :id',
            ['is_active' => $isActive ? 1 : 0, 'now' => date('Y-m-d H:i:s'), 'id' => $orgId]
        );
    }

    /**
     * @return array<int, array<string, mixed>> roster rows joined with username,
     *     officers first then by join date. 'is_officer' is computed from scoped
     *     role membership (PermissionEngine), not a stored column — see the
     *     retrofit plan's Decisions.
     */
    public function listRoster(int $orgId): array
    {
        $membersTable = $this->db->table('org_spaces_members');
        $usersTable = $this->db->table('users');

        $rows = $this->db->fetchAll(
            "SELECT m.*, u.username
             FROM {$membersTable} m
             JOIN {$usersTable} u ON u.id = m.user_id
             WHERE m.org_id = :org_id
             ORDER BY m.joined_at ASC",
            ['org_id' => $orgId]
        );

        $officerIds = $this->officerUserIds($orgId);

        $rows = array_map(static function (array $row) use ($officerIds): array {
            $row['is_officer'] = in_array((int) $row['user_id'], $officerIds, true);

            return $row;
        }, $rows);

        usort($rows, static fn (array $a, array $b): int => $b['is_officer'] <=> $a['is_officer']);

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    public function listOfficers(int $orgId): array
    {
        return array_values(array_filter(
            $this->listRoster($orgId),
            static fn (array $row): bool => (bool) $row['is_officer']
        ));
    }

    public function isMember(int $userId, int $orgId): bool
    {
        return $this->membershipRow($userId, $orgId) !== null;
    }

    /** True on success; false if the user is already on the roster. */
    public function addMember(int $orgId, int $userId, ?string $title, bool $isOfficer): bool
    {
        if ($this->membershipRow($userId, $orgId) !== null) {
            return false;
        }

        $this->db->insert('org_spaces_members', [
            'org_id' => $orgId,
            'user_id' => $userId,
            'title' => $title !== null && $title !== '' ? $title : null,
            'joined_at' => date('Y-m-d H:i:s'),
        ]);

        if ($isOfficer) {
            $this->setOfficer($orgId, $userId, true);
        }

        return true;
    }

    public function updateMember(int $orgId, int $userId, ?string $title, bool $isOfficer): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('org_spaces_members') . '
             SET title = :title
             WHERE org_id = :org_id AND user_id = :user_id',
            [
                'title' => $title !== null && $title !== '' ? $title : null,
                'org_id' => $orgId,
                'user_id' => $userId,
            ]
        );

        $this->setOfficer($orgId, $userId, $isOfficer);
    }

    public function removeMember(int $orgId, int $userId): void
    {
        $this->setOfficer($orgId, $userId, false);

        $this->db->execute(
            'DELETE FROM ' . $this->db->table('org_spaces_members') . ' WHERE org_id = :org_id AND user_id = :user_id',
            ['org_id' => $orgId, 'user_id' => $userId]
        );
    }

    /** @return int[] */
    private function officerUserIds(int $orgId): array
    {
        $role = $this->permissions->findRoleForScope(self::ORG_SCOPE, $orgId);
        if ($role === null) {
            return [];
        }

        return $this->permissions->usersInRole((int) $role['id']);
    }

    private function setOfficer(int $orgId, int $userId, bool $isOfficer): void
    {
        if (!$isOfficer) {
            $role = $this->permissions->findRoleForScope(self::ORG_SCOPE, $orgId);
            if ($role !== null) {
                $this->permissions->removeRoleFromUser($userId, (int) $role['id']);
            }

            return;
        }

        $org = $this->findOrg($orgId);
        if ($org === null) {
            return;
        }

        $role = $this->officerRoleForOrg($org);
        $this->permissions->addRoleToUser($userId, (int) $role['id']);
    }

    /**
     * Finds this org's dedicated officer role, creating it (and its scoped
     * org_spaces.moderate grant) on first use — self-heals for orgs that
     * existed before this retrofit, same as orgs created from now on. See
     * ForumAdminController::moderatorRoleForBoard() for the same pattern.
     *
     * @param array<string, mixed> $org
     * @return array<string, mixed>
     */
    private function officerRoleForOrg(array $org): array
    {
        $orgId = (int) $org['id'];
        $existing = $this->permissions->findRoleForScope(self::ORG_SCOPE, $orgId);
        if ($existing !== null) {
            return $existing;
        }

        $roleId = $this->permissions->createRole(
            "Officers — {$org['name']} (#{$orgId})",
            self::ORG_SCOPE,
            $orgId
        );

        $capability = $this->permissions->findCapabilityByKey('org_spaces.moderate');
        if ($capability !== null) {
            $this->permissions->grant((int) $roleId, (int) $capability['id'], self::ORG_SCOPE, $orgId);
        }

        return ['id' => $roleId, 'name' => "Officers — {$org['name']} (#{$orgId})"];
    }

    /**
     * @return array<int, array<string, mixed>> newest first. Author display name is
     *     resolved by the controller via AuthService::findById() (soft-delete-aware,
     *     same "Unknown" fallback as forum/articles/wiki) — not joined here, since a
     *     raw SQL join can't see past deleted_at the way that service does.
     */
    public function listAnnouncements(int $orgId, int $limit = 50): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('org_spaces_announcements') . '
             WHERE org_id = :org_id
             ORDER BY created_at DESC
             LIMIT ' . max(1, $limit),
            ['org_id' => $orgId]
        );
    }

    public function postAnnouncement(int $orgId, int $authorId, string $title, string $body): string
    {
        $now = date('Y-m-d H:i:s');

        return $this->db->insert('org_spaces_announcements', [
            'org_id' => $orgId,
            'author_id' => $authorId,
            'title' => $title,
            'body' => $body,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function findAnnouncement(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('org_spaces_announcements') . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    public function deleteAnnouncement(int $id): void
    {
        $this->db->execute(
            'DELETE FROM ' . $this->db->table('org_spaces_announcements') . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    /** @return array<string, mixed>|null */
    private function membershipRow(int $userId, int $orgId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('org_spaces_members') . ' WHERE user_id = :user_id AND org_id = :org_id',
            ['user_id' => $userId, 'org_id' => $orgId]
        );
    }

    private function uniqueOrgSlug(string $name): string
    {
        $base = Slug::make($name, 'org');
        $slug = $base;
        $suffix = 2;

        while ($this->findOrgBySlug($slug) !== null) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
