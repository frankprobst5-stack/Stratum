<?php

declare(strict_types=1);

namespace Stratum\Modules\SiteStats;

use Stratum\Core\Database;

/**
 * Real, already-tracked counts only — no page-view/visitor numbers, per
 * the explicit 2026-07-18 decision (see docs/roadmap.md's Stage 8 block-
 * library entry): both reference mockups this design was based on showed
 * a Page Views/Visitors stat, and the user confirmed real-data-only
 * instead of building new tracking infrastructure to fake it.
 * All "this week" comparisons use MySQL's own NOW() - INTERVAL, never a
 * PHP-computed date — the timezone-safety house rule established after
 * the scheduled-publishing bug.
 */
final class StatsService
{
    public function __construct(private readonly Database $db)
    {
    }

    public function memberCount(): int
    {
        return $this->count('users', 'deleted_at IS NULL');
    }

    public function newMembersThisWeek(): int
    {
        return $this->count('users', 'deleted_at IS NULL AND created_at >= NOW() - INTERVAL 7 DAY');
    }

    public function commentsThisWeek(): int
    {
        return $this->count('comments', 'deleted_at IS NULL AND created_at >= NOW() - INTERVAL 7 DAY');
    }

    private function count(string $table, string $where): int
    {
        $row = $this->db->fetchOne('SELECT COUNT(*) AS c FROM ' . $this->db->table($table) . " WHERE {$where}");

        return (int) ($row['c'] ?? 0);
    }
}
