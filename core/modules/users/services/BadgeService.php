<?php

declare(strict_types=1);

namespace Stratum\Modules\Users;

use Stratum\Core\Database;

final class BadgeService
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function listBadges(): array
    {
        return $this->db->fetchAll('SELECT * FROM ' . $this->db->table('badges') . ' ORDER BY name');
    }

    /** @return array<string, mixed>|null */
    public function findBadge(int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM ' . $this->db->table('badges') . ' WHERE id = :id', ['id' => $id]);
    }

    public function createBadge(string $name, string $description, string $iconUrl): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->insert('badges', [
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'icon_url' => $iconUrl !== '' ? $iconUrl : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** True if the badge existed and wasn't already awarded to this member. */
    public function award(int $userId, int $badgeId, ?int $awardedBy): bool
    {
        $existing = $this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('member_badges') . '
             WHERE user_id = :user_id AND badge_id = :badge_id',
            ['user_id' => $userId, 'badge_id' => $badgeId]
        );

        if ($existing !== null) {
            return false;
        }

        $this->db->insert('member_badges', [
            'user_id' => $userId,
            'badge_id' => $badgeId,
            'awarded_by' => $awardedBy,
            'awarded_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    public function revoke(int $userId, int $badgeId): void
    {
        $this->db->execute(
            'DELETE FROM ' . $this->db->table('member_badges') . '
             WHERE user_id = :user_id AND badge_id = :badge_id',
            ['user_id' => $userId, 'badge_id' => $badgeId]
        );
    }

    /** @return array<int, array<string, mixed>> this member's badges, each a badges row plus 'awarded_at' */
    public function listForUser(int $userId): array
    {
        $badges = $this->db->table('badges');
        $memberBadges = $this->db->table('member_badges');

        return $this->db->fetchAll(
            "SELECT b.*, mb.awarded_at FROM {$memberBadges} mb
             INNER JOIN {$badges} b ON b.id = mb.badge_id
             WHERE mb.user_id = :user_id
             ORDER BY mb.awarded_at DESC",
            ['user_id' => $userId]
        );
    }
}
