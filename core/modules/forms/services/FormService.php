<?php

declare(strict_types=1);

namespace Stratum\Modules\Forms;

use Stratum\Core\Database;
use Stratum\Core\Slug;

final class FormService
{
    /** @var array<int, string> */
    private const CHOICE_TYPES = ['select', 'checkbox'];

    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function listPublished(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('forms') . "
             WHERE status = 'published' AND deleted_at IS NULL ORDER BY created_at DESC"
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function listAll(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('forms') . '
             WHERE deleted_at IS NULL ORDER BY created_at DESC'
        );
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('forms') . ' WHERE slug = :slug AND deleted_at IS NULL',
            ['slug' => $slug]
        );
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('forms') . ' WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    /** @return array<int, array{id: int, label: string, type: string, options: ?string, required: bool}> */
    public function listFields(int $formId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT id, label, type, options, required FROM ' . $this->db->table('form_fields') . '
             WHERE form_id = :form_id ORDER BY position, id',
            ['form_id' => $formId]
        );

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'label' => $r['label'],
            'type' => $r['type'],
            'options' => $r['options'],
            'required' => (bool) $r['required'],
        ], $rows);
    }

    public function createForm(string $title, string $description, ?int $createdBy): int
    {
        $now = date('Y-m-d H:i:s');

        return (int) $this->db->insert('forms', [
            'title' => $title,
            'slug' => $this->uniqueSlug($title),
            'description' => $description !== '' ? $description : null,
            'status' => 'draft',
            'created_by' => $createdBy,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function addField(int $formId, string $label, string $type, ?string $options, bool $required): void
    {
        $row = $this->db->fetchOne(
            'SELECT COALESCE(MAX(position), -1) AS max_position FROM ' . $this->db->table('form_fields') . '
             WHERE form_id = :form_id',
            ['form_id' => $formId]
        );
        $position = $row !== null ? ((int) $row['max_position']) + 1 : 0;

        $this->db->insert('form_fields', [
            'form_id' => $formId,
            'label' => $label,
            'type' => $type,
            'options' => in_array($type, self::CHOICE_TYPES, true) ? $options : null,
            'required' => $required ? 1 : 0,
            'position' => $position,
        ]);
    }

    public function deleteField(int $fieldId): void
    {
        $this->db->execute(
            'DELETE FROM ' . $this->db->table('form_fields') . ' WHERE id = :id',
            ['id' => $fieldId]
        );
    }

    public function setStatus(int $formId, string $status): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('forms') . ' SET status = :status, updated_at = :updated_at WHERE id = :id',
            ['status' => $status, 'updated_at' => date('Y-m-d H:i:s'), 'id' => $formId]
        );
    }

    public function softDeleteForm(int $formId): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->execute(
            'UPDATE ' . $this->db->table('forms') . ' SET deleted_at = :deleted_at, updated_at = :updated_at WHERE id = :id',
            ['deleted_at' => $now, 'updated_at' => $now, 'id' => $formId]
        );
    }

    public function hasSubmitted(int $formId, int $userId): bool
    {
        return $this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('form_submissions') . '
             WHERE form_id = :form_id AND user_id = :user_id',
            ['form_id' => $formId, 'user_id' => $userId]
        ) !== null;
    }

    /**
     * @param array<int, string|array<int, string>> $answers field id => value (or array of values for checkbox)
     */
    public function submit(int $formId, int $userId, array $answers): void
    {
        $submissionId = (int) $this->db->insert('form_submissions', [
            'form_id' => $formId,
            'user_id' => $userId,
            'submitted_at' => date('Y-m-d H:i:s'),
        ]);

        foreach ($answers as $fieldId => $value) {
            foreach ((array) $value as $single) {
                $single = trim((string) $single);
                if ($single === '') {
                    continue;
                }

                $this->db->insert('form_submission_answers', [
                    'submission_id' => $submissionId,
                    'field_id' => $fieldId,
                    'value' => $single,
                ]);
            }
        }
    }

    /** @return array<int, array{submittedAt: string, username: string, answers: array<int, string>}> */
    public function listSubmissions(int $formId): array
    {
        $submissions = $this->db->fetchAll(
            'SELECT s.id, s.submitted_at, u.username
             FROM ' . $this->db->table('form_submissions') . ' s
             INNER JOIN ' . $this->db->table('users') . ' u ON u.id = s.user_id
             WHERE s.form_id = :form_id
             ORDER BY s.submitted_at DESC',
            ['form_id' => $formId]
        );

        return array_map(function (array $s): array {
            $answers = $this->db->fetchAll(
                'SELECT field_id, value FROM ' . $this->db->table('form_submission_answers') . '
                 WHERE submission_id = :submission_id',
                ['submission_id' => $s['id']]
            );

            $byField = [];
            foreach ($answers as $a) {
                $fieldId = (int) $a['field_id'];
                $byField[$fieldId] = isset($byField[$fieldId])
                    ? $byField[$fieldId] . ', ' . $a['value']
                    : $a['value'];
            }

            return [
                'submittedAt' => $s['submitted_at'],
                'username' => $s['username'],
                'answers' => $byField,
            ];
        }, $submissions);
    }

    /** @return array<int, array{value: string, count: int}> tally for a single choice-type field, most-picked first */
    public function tally(int $fieldId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT value, COUNT(*) AS c FROM ' . $this->db->table('form_submission_answers') . '
             WHERE field_id = :field_id GROUP BY value ORDER BY c DESC',
            ['field_id' => $fieldId]
        );

        return array_map(static fn (array $r): array => ['value' => $r['value'], 'count' => (int) $r['c']], $rows);
    }

    public function submissionCount(int $formId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS c FROM ' . $this->db->table('form_submissions') . ' WHERE form_id = :form_id',
            ['form_id' => $formId]
        );

        return $row !== null ? (int) $row['c'] : 0;
    }

    private function uniqueSlug(string $value): string
    {
        $base = Slug::make($value, 'form');
        $slug = $base;
        $suffix = 2;

        while ($this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('forms') . ' WHERE slug = :slug',
            ['slug' => $slug]
        ) !== null) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
