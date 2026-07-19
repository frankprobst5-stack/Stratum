<?php

declare(strict_types=1);

namespace Stratum\Modules\Ticker;

use Stratum\Core\Database;

final class TickerService
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int, array<string, mixed>> every message, admin view, weight ASC */
    public function listAll(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('ticker_messages') . ' ORDER BY weight ASC, created_at ASC'
        );
    }

    /** @return array<int, array<string, mixed>> enabled messages within their active window, weight ASC */
    public function listActive(): array
    {
        $now = date('Y-m-d H:i:s');

        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('ticker_messages') . '
             WHERE is_enabled = 1
               AND (starts_at IS NULL OR starts_at <= :now_starts)
               AND (ends_at IS NULL OR ends_at >= :now_ends)
             ORDER BY weight ASC, created_at ASC',
            ['now_starts' => $now, 'now_ends' => $now]
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('ticker_messages') . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    public function createMessage(
        string $message,
        ?string $url,
        string $level,
        ?string $startsAt,
        ?string $endsAt,
        int $weight,
        ?int $authorId
    ): void {
        $now = date('Y-m-d H:i:s');
        $this->db->insert('ticker_messages', [
            'message' => $message,
            'url' => $url,
            'level' => $level,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'weight' => $weight,
            'is_enabled' => 1,
            'author_id' => $authorId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function updateMessage(
        int $id,
        string $message,
        ?string $url,
        string $level,
        ?string $startsAt,
        ?string $endsAt,
        int $weight
    ): void {
        $this->db->execute(
            'UPDATE ' . $this->db->table('ticker_messages') . '
             SET message = :message, url = :url, level = :level, starts_at = :starts_at,
                 ends_at = :ends_at, weight = :weight, updated_at = :now
             WHERE id = :id',
            [
                'message' => $message,
                'url' => $url,
                'level' => $level,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'weight' => $weight,
                'now' => date('Y-m-d H:i:s'),
                'id' => $id,
            ]
        );
    }

    public function toggleEnabled(int $id): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('ticker_messages') . '
             SET is_enabled = 1 - is_enabled, updated_at = :now
             WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    public function deleteMessage(int $id): void
    {
        $this->db->execute(
            'DELETE FROM ' . $this->db->table('ticker_messages') . ' WHERE id = :id',
            ['id' => $id]
        );
    }
}
