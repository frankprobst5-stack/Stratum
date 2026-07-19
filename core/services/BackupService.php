<?php

declare(strict_types=1);

namespace Stratum\Core;

use PDO;

/**
 * Pure-PHP database backups — deliberately not shelling out to
 * `mysqldump`, since this app can't assume that binary (or `exec()`
 * itself) is available on unknown shared hosting, the same reasoning
 * `ClamAvScanner` already established for virus scanning. Streams
 * directly to a file handle (gzip-compressed when the `zlib` extension
 * is available, plain `.sql` otherwise) rather than building the whole
 * dump as one in-memory string, so club-scale data doesn't risk hitting
 * `memory_limit` the way a naive "build a string, then write it" approach
 * would.
 *
 * v1 is database-only, not a full-site archive — `storage/uploads/`
 * can run into the gigabytes for an active club (gallery/downloads
 * content), and streaming a ZIP of that alongside a SQL dump through a
 * single web request is a meaningfully bigger feature than this pass
 * scopes, not an oversight. See docs/roadmap.md's Backup Manager entry.
 */
final class BackupService
{
    private const BATCH_SIZE = 500;
    private const FILENAME_PATTERN = '/^stratum-backup-\d{4}-\d{2}-\d{2}-\d{6}\.sql(\.gz)?$/';

    public function __construct(
        private readonly Database $db,
        private readonly string $backupDir
    ) {
    }

    public function create(): string
    {
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }

        $useGzip = function_exists('gzopen');
        $filename = 'stratum-backup-' . date('Y-m-d-His') . ($useGzip ? '.sql.gz' : '.sql');
        $path = "{$this->backupDir}/{$filename}";

        $handle = $useGzip ? gzopen($path, 'wb9') : fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Could not open backup file for writing.');
        }

        $write = static function (string $data) use ($handle, $useGzip): void {
            $useGzip ? gzwrite($handle, $data) : fwrite($handle, $data);
        };

        $write("-- Stratum CMS database backup\n-- Generated " . date('Y-m-d H:i:s') . "\n\n");
        $write("SET FOREIGN_KEY_CHECKS=0;\n\n");

        $pdo = $this->db->pdo();
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $this->dumpTable($pdo, $table, $write);
        }

        $write("SET FOREIGN_KEY_CHECKS=1;\n");

        $useGzip ? gzclose($handle) : fclose($handle);

        return $filename;
    }

    /** @return array<int, array{filename: string, size: int, createdAt: int}> newest first */
    public function list(): array
    {
        if (!is_dir($this->backupDir)) {
            return [];
        }

        $files = [];
        foreach (glob("{$this->backupDir}/stratum-backup-*.sql*") ?: [] as $path) {
            $filename = basename($path);
            if (preg_match(self::FILENAME_PATTERN, $filename) !== 1) {
                continue;
            }

            $files[] = [
                'filename' => $filename,
                'size' => filesize($path) ?: 0,
                'createdAt' => filemtime($path) ?: 0,
            ];
        }

        usort($files, static fn (array $a, array $b): int => $b['createdAt'] <=> $a['createdAt']);

        return $files;
    }

    /** Null if $filename doesn't match the expected backup naming pattern — refuses to resolve a path outside backupDir. */
    public function path(string $filename): ?string
    {
        if (preg_match(self::FILENAME_PATTERN, $filename) !== 1) {
            return null;
        }

        $path = "{$this->backupDir}/{$filename}";

        return is_file($path) ? $path : null;
    }

    public function delete(string $filename): bool
    {
        $path = $this->path($filename);
        if ($path === null) {
            return false;
        }

        return unlink($path);
    }

    /** @param callable(string): void $write */
    private function dumpTable(PDO $pdo, string $table, callable $write): void
    {
        $createRow = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
        $write("DROP TABLE IF EXISTS `{$table}`;\n");
        $write($createRow['Create Table'] . ";\n\n");

        $stmt = $pdo->query("SELECT * FROM `{$table}`");
        $columns = null;
        $batch = [];

        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $columns ??= array_keys($row);
            $batch[] = '(' . implode(',', array_map(
                static fn (mixed $v): string => $v === null ? 'NULL' : $pdo->quote((string) $v),
                $row
            )) . ')';

            if (count($batch) >= self::BATCH_SIZE) {
                $this->flushBatch($write, $table, $columns, $batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $this->flushBatch($write, $table, $columns, $batch);
        }

        $write("\n");
    }

    /**
     * @param callable(string): void $write
     * @param array<int, string> $columns
     * @param array<int, string> $batch
     */
    private function flushBatch(callable $write, string $table, array $columns, array $batch): void
    {
        $write("INSERT INTO `{$table}` (`" . implode('`,`', $columns) . "`) VALUES\n" . implode(",\n", $batch) . ";\n");
    }
}
