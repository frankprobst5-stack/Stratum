<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Read-only server/environment diagnostics for the admin panel — a live
 * counterpart to the web installer's own `checkRequirements()`, not a
 * shared call into it: `public/install.php` is deliberately standalone
 * (no app bootstrap, see its own docblock), so this small amount of
 * duplication between the two is the same accepted tradeoff, not an
 * oversight.
 */
final class SystemHealthService
{
    private const REQUIRED_PHP_VERSION = '8.2.0';
    private const REQUIRED_EXTENSIONS = ['pdo_mysql', 'gd', 'exif', 'fileinfo', 'mbstring'];
    private const WRITABLE_DIRS = ['storage', 'storage/uploads', 'storage/cache', 'storage/logs'];

    public function __construct(
        private readonly Database $db,
        private readonly string $rootDir
    ) {
    }

    /** @return array<int, array{label: string, ok: bool}> */
    public function checks(): array
    {
        $checks = [
            [
                'label' => 'PHP version ' . PHP_VERSION . ' (>= ' . self::REQUIRED_PHP_VERSION . ' required)',
                'ok' => version_compare(PHP_VERSION, self::REQUIRED_PHP_VERSION, '>='),
            ],
        ];

        foreach (self::REQUIRED_EXTENSIONS as $ext) {
            $checks[] = ['label' => "PHP extension: {$ext}", 'ok' => extension_loaded($ext)];
        }

        foreach (self::WRITABLE_DIRS as $dir) {
            $checks[] = [
                'label' => "{$dir}/ is writable",
                'ok' => is_dir("{$this->rootDir}/{$dir}") && is_writable("{$this->rootDir}/{$dir}"),
            ];
        }

        $checks[] = ['label' => 'Database connection', 'ok' => $this->databaseOk()];

        return $checks;
    }

    /** @return array{freeBytes: int, totalBytes: int, freePercent: float} */
    public function diskSpace(): array
    {
        $free = disk_free_space($this->rootDir);
        $total = disk_total_space($this->rootDir);

        $free = $free !== false ? (int) $free : 0;
        $total = $total !== false ? (int) $total : 0;

        return [
            'freeBytes' => $free,
            'totalBytes' => $total,
            'freePercent' => $total > 0 ? round(($free / $total) * 100, 1) : 0.0,
        ];
    }

    /** @return array<string, string> a handful of upload/execution-relevant php.ini values, display-only */
    public function phpLimits(): array
    {
        return [
            'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
            'post_max_size' => (string) ini_get('post_max_size'),
            'memory_limit' => (string) ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time') . 's',
        ];
    }

    /** Most recent cron.daily run, read back from the log sink cron.php already writes to — null if cron has never run. */
    public function lastCronRun(): ?string
    {
        $row = $this->db->fetchOne(
            'SELECT created_at FROM ' . $this->db->table('core_logs') . "
             WHERE message = 'cron.daily starting.' ORDER BY created_at DESC LIMIT 1"
        );

        return $row['created_at'] ?? null;
    }

    public function recentErrorCount(int $hours = 24): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS c FROM ' . $this->db->table('core_logs') . "
             WHERE level = 'error' AND created_at >= :cutoff",
            ['cutoff' => date('Y-m-d H:i:s', strtotime("-{$hours} hours"))]
        );

        return $row !== null ? (int) $row['c'] : 0;
    }

    private function databaseOk(): bool
    {
        try {
            $this->db->fetchOne('SELECT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
