<?php

declare(strict_types=1);

namespace Stratum\Modules\Affiliates;

use Stratum\Core\Database;

final class AffiliateService
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int, array<string, mixed>> active links, ordered for display */
    public function listActive(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('affiliate_links') . '
             WHERE deleted_at IS NULL AND is_active = 1
             ORDER BY weight, label'
        );
    }

    /** @return array<int, array<string, mixed>> all non-deleted links, for the admin list */
    public function listAll(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('affiliate_links') . '
             WHERE deleted_at IS NULL
             ORDER BY weight, label'
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('affiliate_links') . ' WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    public function create(string $label, string $url, string $description, int $weight): int
    {
        $now = date('Y-m-d H:i:s');

        return (int) $this->db->insert('affiliate_links', [
            'label' => $label,
            'url' => $url,
            'description' => $description !== '' ? $description : null,
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
            'UPDATE ' . $this->db->table('affiliate_links') . ' SET is_active = :is_active, updated_at = :now WHERE id = :id',
            ['is_active' => $isActive ? 1 : 0, 'now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    public function incrementClickCount(int $id): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('affiliate_links') . ' SET click_count = click_count + 1 WHERE id = :id',
            ['id' => $id]
        );
    }

    public function softDelete(int $id): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('affiliate_links') . ' SET deleted_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }
}
