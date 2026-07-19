<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Admin action history — see the `audit_log` migration's own docblock
 * for why this is a distinct concern from Logger/core_logs. Written
 * from one place (public/index.php, right after dispatch) rather than
 * scattered calls across ~30 admin controllers: every admin mutation,
 * present and future, is captured automatically with zero risk of a
 * new controller action forgetting to log itself.
 */
final class AuditLogService
{
    private const PER_PAGE = 50;

    public function __construct(private readonly Database $db)
    {
    }

    public function record(int $userId, string $username, string $method, string $path): void
    {
        $this->db->insert('audit_log', [
            'user_id' => $userId,
            'username' => $username,
            'method' => $method,
            'path' => $path,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return array<int, array<string, mixed>> newest first */
    public function list(int $page): array
    {
        $offset = max(0, $page - 1) * self::PER_PAGE;

        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('audit_log') . '
             ORDER BY created_at DESC, id DESC LIMIT ' . self::PER_PAGE . " OFFSET {$offset}"
        );
    }

    public function count(): int
    {
        $row = $this->db->fetchOne('SELECT COUNT(*) AS c FROM ' . $this->db->table('audit_log'));

        return $row !== null ? (int) $row['c'] : 0;
    }

    public function perPage(): int
    {
        return self::PER_PAGE;
    }
}
