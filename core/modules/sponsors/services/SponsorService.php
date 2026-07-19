<?php

declare(strict_types=1);

namespace Stratum\Modules\Sponsors;

use Stratum\Core\Database;

final class SponsorService
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int, array<string, mixed>> all active sponsors, ordered for display — unlike ads.banner, every active sponsor renders at once, not one random pick. */
    public function listActive(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('sponsors') . '
             WHERE deleted_at IS NULL AND is_active = 1
             ORDER BY weight, name'
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function listAll(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('sponsors') . '
             WHERE deleted_at IS NULL
             ORDER BY weight, name'
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('sponsors') . ' WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    public function create(string $name, string $logoUrl, string $linkUrl, int $weight): int
    {
        $now = date('Y-m-d H:i:s');

        return (int) $this->db->insert('sponsors', [
            'name' => $name,
            'logo_url' => $logoUrl,
            'link_url' => $linkUrl,
            'weight' => $weight,
            'is_active' => 1,
            'click_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ]);
    }

    public function setActive(int $id, bool $isActive): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('sponsors') . ' SET is_active = :is_active, updated_at = :now WHERE id = :id',
            ['is_active' => $isActive ? 1 : 0, 'now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    public function incrementClickCount(int $id): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('sponsors') . ' SET click_count = click_count + 1 WHERE id = :id',
            ['id' => $id]
        );
    }

    public function softDelete(int $id): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('sponsors') . ' SET deleted_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }
}
