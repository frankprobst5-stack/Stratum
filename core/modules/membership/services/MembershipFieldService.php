<?php

declare(strict_types=1);

namespace Stratum\Modules\Membership;

use Stratum\Core\Database;

final class MembershipFieldService
{
    private const TYPES = ['text', 'textarea', 'checkbox', 'select'];

    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function listFields(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('membership_fields') . ' ORDER BY weight ASC, id ASC'
        );
    }

    /** @param string[] $options only used when $fieldType === 'select' */
    public function createField(string $label, string $fieldType, array $options, bool $isRequired, int $weight): bool
    {
        if (!in_array($fieldType, self::TYPES, true)) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->insert('membership_fields', [
            'label' => $label,
            'field_type' => $fieldType,
            'options_json' => $fieldType === 'select' ? json_encode(array_values($options)) : null,
            'is_required' => $isRequired ? 1 : 0,
            'weight' => $weight,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return true;
    }

    public function deleteField(int $id): void
    {
        $this->db->execute(
            'DELETE FROM ' . $this->db->table('membership_fields') . ' WHERE id = :id',
            ['id' => $id]
        );
    }
}
