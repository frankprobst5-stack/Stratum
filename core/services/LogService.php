<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Admin UI over `core_logs` — the DB sink Logger already dual-writes to
 * alongside storage/logs/app.log (see Logger::writeToDatabase()).
 * Reading the DB table rather than parsing the flat file: it's already
 * structured (level/message/context/created_at as real columns), so
 * filtering and pagination are plain SQL instead of a hand-rolled log
 * parser.
 */
final class LogService
{
    private const PER_PAGE = 50;

    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int, array<string, mixed>> newest first */
    public function list(?string $level, int $page): array
    {
        $table = $this->db->table('core_logs');
        $offset = max(0, $page - 1) * self::PER_PAGE;

        $where = '';
        $params = [];
        if ($level !== null) {
            $where = 'WHERE level = :level';
            $params['level'] = $level;
        }

        return $this->db->fetchAll(
            "SELECT * FROM {$table} {$where} ORDER BY created_at DESC, id DESC LIMIT " . self::PER_PAGE . " OFFSET {$offset}",
            $params
        );
    }

    public function count(?string $level): int
    {
        $table = $this->db->table('core_logs');
        $where = '';
        $params = [];
        if ($level !== null) {
            $where = 'WHERE level = :level';
            $params['level'] = $level;
        }

        $row = $this->db->fetchOne("SELECT COUNT(*) AS c FROM {$table} {$where}", $params);

        return $row !== null ? (int) $row['c'] : 0;
    }

    public function perPage(): int
    {
        return self::PER_PAGE;
    }

    public function clearAll(): void
    {
        $this->db->execute('DELETE FROM ' . $this->db->table('core_logs'));
    }
}
