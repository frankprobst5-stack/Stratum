<?php

declare(strict_types=1);

namespace Stratum\Modules\Users;

use Stratum\Core\AuthServiceInterface;
use Stratum\Core\Database;

final class AuthService implements AuthServiceInterface
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function findByCredentials(string $login, string $password): ?array
    {
        $user = $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('users') . '
             WHERE (username = :username OR email = :email) AND deleted_at IS NULL',
            ['username' => $login, 'email' => $login]
        );

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        return $user;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('users') . ' WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    /** @return array<string, mixed>|null */
    public function findByUsername(string $username): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('users') . ' WHERE username = :username AND deleted_at IS NULL',
            ['username' => $username]
        );
    }

    public function usernameOrEmailExists(string $username, string $email): bool
    {
        $row = $this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('users') . ' WHERE username = :username OR email = :email',
            ['username' => $username, 'email' => $email]
        );

        return $row !== null;
    }

    public function createUser(string $username, string $email, string $password): string
    {
        return $this->createUserWithHash($username, $email, password_hash($password, PASSWORD_ARGON2ID));
    }

    public function createUserWithHash(string $username, string $email, string $passwordHash): string
    {
        $now = date('Y-m-d H:i:s');

        $defaultRank = $this->db->fetchOne("SELECT id FROM " . $this->db->table('ranks') . " WHERE name = 'New Member'");

        return $this->db->insert('users', [
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'rank_id' => $defaultRank['id'] ?? null,
            'points' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function listUsers(): array
    {
        return $this->db->fetchAll(
            'SELECT id, username, email, about_me, avatar_url, created_at FROM ' . $this->db->table('users') . '
             WHERE deleted_at IS NULL ORDER BY username'
        );
    }

    /**
     * Most-recently-registered members — listUsers() sorts by username,
     * not signup order, so this is a separate query rather than a reuse.
     * Backs the "Newest Members" front-page block.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listNewest(int $limit): array
    {
        return $this->db->fetchAll(
            'SELECT id, username, avatar_url, created_at FROM ' . $this->db->table('users') . '
             WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT ' . max(1, $limit)
        );
    }

    /** Promoted here once both ProfileController (your own page) and MemberProfileController (viewing someone else's) needed the exact same rank_id -> name lookup. */
    public function rankName(?int $rankId): ?string
    {
        if ($rankId === null) {
            return null;
        }

        $row = $this->db->fetchOne(
            'SELECT name FROM ' . $this->db->table('ranks') . ' WHERE id = :id',
            ['id' => $rankId]
        );

        return $row['name'] ?? null;
    }

    public function updateProfile(int $userId, string $aboutMe, string $avatarUrl, string $signature = '', string $bannerUrl = ''): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('users') . '
             SET about_me = :about_me, avatar_url = :avatar_url, signature = :signature, banner_url = :banner_url, updated_at = :now
             WHERE id = :id',
            [
                'about_me' => $aboutMe === '' ? null : $aboutMe,
                'avatar_url' => $avatarUrl === '' ? null : $avatarUrl,
                'signature' => $signature === '' ? null : mb_substr($signature, 0, 500),
                'banner_url' => $bannerUrl === '' ? null : $bannerUrl,
                'now' => date('Y-m-d H:i:s'),
                'id' => $userId,
            ]
        );
    }

    /**
     * True if deleting $userId would leave the site with zero non-deleted
     * admins/founders — refuses the last-admin case, not any other. A
     * cheap, targeted guard against a genuinely bricking mistake (an
     * empty admin panel with no way back in short of a database console),
     * the same spirit `ModuleManager::NON_DISABLEABLE` already protects
     * for the `users` module itself.
     */
    public function isLastAdmin(int $userId): bool
    {
        $usersRoles = $this->db->table('users_roles');
        $roles = $this->db->table('roles');
        $users = $this->db->table('users');

        $row = $this->db->fetchOne(
            "SELECT COUNT(DISTINCT ur.user_id) AS c
             FROM {$usersRoles} ur
             INNER JOIN {$roles} r ON r.id = ur.role_id
             INNER JOIN {$users} u ON u.id = ur.user_id
             WHERE r.name IN ('admin', 'founder') AND u.deleted_at IS NULL"
        );
        $adminCount = $row !== null ? (int) $row['c'] : 0;

        $targetIsAdmin = $this->db->fetchOne(
            "SELECT ur.id FROM {$usersRoles} ur
             INNER JOIN {$roles} r ON r.id = ur.role_id
             WHERE ur.user_id = :user_id AND r.name IN ('admin', 'founder')",
            ['user_id' => $userId]
        ) !== null;

        return $targetIsAdmin && $adminCount <= 1;
    }

    /**
     * Soft-delete only — matches this app's universal discipline
     * everywhere else (never hard-delete). Their authored content is
     * deliberately left untouched, not cascaded or anonymized: every
     * author-name lookup in this app already falls back to "Unknown"
     * gracefully when `findById()` can't find a non-deleted user (that
     * fallback already existed for the "the account was deleted" case
     * before this feature — see MemberNoteService's authorName() helper
     * for one example among many), so this needed no new code to degrade
     * correctly. `Auth::user()` re-resolves the account fresh on every
     * request rather than trusting a cached session value, so an active
     * session is locked out on its very next request, no explicit
     * session-kill step required.
     */
    public function softDeleteAccount(int $userId): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->execute(
            'UPDATE ' . $this->db->table('users') . ' SET deleted_at = :deleted_at, updated_at = :updated_at WHERE id = :id',
            ['deleted_at' => $now, 'updated_at' => $now, 'id' => $userId]
        );
    }

    /** True if a deleted account existed and was restored. */
    public function restoreAccount(int $userId): bool
    {
        return $this->db->execute(
            'UPDATE ' . $this->db->table('users') . ' SET deleted_at = NULL WHERE id = :id AND deleted_at IS NOT NULL',
            ['id' => $userId]
        ) > 0;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listDeletedUsers(): array
    {
        return $this->db->fetchAll(
            'SELECT id, username, email, deleted_at FROM ' . $this->db->table('users') . '
             WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC'
        );
    }
}
