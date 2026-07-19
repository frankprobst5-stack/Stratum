<?php

declare(strict_types=1);

namespace Stratum\Modules\Membership;

use Stratum\Core\Database;
use Stratum\Core\PermissionEngine;
use Stratum\Modules\Users\AuthService;

final class MembershipApplicationService
{
    public function __construct(private readonly Database $db)
    {
    }

    public function isUsernameOrEmailTaken(string $username, string $email, AuthService $authService): bool
    {
        if ($authService->usernameOrEmailExists($username, $email)) {
            return true;
        }

        $row = $this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('membership_applications') . '
             WHERE status = :status AND (username = :username OR email = :email)',
            ['status' => 'pending', 'username' => $username, 'email' => $email]
        );

        return $row !== null;
    }

    /** @param array<int, string> $answers field_id => answer value */
    public function submitApplication(string $username, string $email, string $passwordHash, array $answers): string
    {
        $now = date('Y-m-d H:i:s');

        return $this->db->insert('membership_applications', [
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'answers_json' => json_encode($answers),
            'status' => 'pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function listPending(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('membership_applications') . '
             WHERE status = :status ORDER BY created_at ASC',
            ['status' => 'pending']
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function listReviewed(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('membership_applications') . '
             WHERE status != :status ORDER BY reviewed_at DESC',
            ['status' => 'pending']
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('membership_applications') . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    /** @return int|null the new user id, or null if the application wasn't pending */
    public function approve(int $id, int $reviewerId, AuthService $authService, PermissionEngine $permissions): ?int
    {
        $application = $this->find($id);
        if ($application === null || $application['status'] !== 'pending') {
            return null;
        }

        $userId = (int) $authService->createUserWithHash(
            $application['username'],
            $application['email'],
            $application['password_hash']
        );

        $memberRole = $this->db->fetchOne("SELECT id FROM " . $this->db->table('roles') . " WHERE name = 'member'");
        if ($memberRole !== null) {
            $permissions->setRolesForUser($userId, [(int) $memberRole['id']]);
        }

        $this->markReviewed($id, 'approved', $reviewerId);

        return $userId;
    }

    public function reject(int $id, int $reviewerId): void
    {
        $application = $this->find($id);
        if ($application === null || $application['status'] !== 'pending') {
            return;
        }

        $this->markReviewed($id, 'rejected', $reviewerId);
    }

    private function markReviewed(int $id, string $status, int $reviewerId): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('membership_applications') . '
             SET status = :status, reviewed_by = :reviewed_by, reviewed_at = :reviewed_at, updated_at = :updated_at
             WHERE id = :id',
            [
                'status' => $status,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $id,
            ]
        );
    }
}
