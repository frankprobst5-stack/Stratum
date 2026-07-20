<?php

declare(strict_types=1);

namespace Stratum\Core;

final class MigrationRunner
{
    public function __construct(private readonly Database $db)
    {
    }

    /** Bootstraps the tracking table itself — not tracked as a migration of its own. */
    public function ensureMigrationsTable(): void
    {
        $table = $this->db->table('core_migrations');

        $this->db->execute("
            CREATE TABLE IF NOT EXISTS {$table} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                module_id VARCHAR(64) NOT NULL,
                migration VARCHAR(191) NOT NULL,
                run_at DATETIME NOT NULL,
                UNIQUE KEY uniq_module_migration (module_id, migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Applies pending migrations found in $directory, in filename order, for $moduleId.
     *
     * $only, when given, restricts this run to just those basenames — used
     * by runAll() to split 'core' into two passes around 'users' (see that
     * method's docblock for why).
     *
     * @param string[]|null $only
     * @return string[] filenames applied
     */
    public function run(string $moduleId, string $directory, ?array $only = null): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $applied = [];
        $files = glob($directory . '/*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            $migration = basename($file);

            if ($only !== null && !in_array($migration, $only, true)) {
                continue;
            }

            if ($this->alreadyRan($moduleId, $migration)) {
                continue;
            }

            /** @var Migration $instance */
            $instance = require $file;
            if (!$instance instanceof Migration) {
                throw new \RuntimeException("Migration file does not return a Migration instance: {$file}");
            }

            $instance->up($this->db);
            $this->record($moduleId, $migration);
            $applied[] = $migration;
        }

        return $applied;
    }

    private function alreadyRan(string $moduleId, string $migration): bool
    {
        $table = $this->db->table('core_migrations');

        $row = $this->db->fetchOne(
            "SELECT id FROM {$table} WHERE module_id = :module_id AND migration = :migration",
            ['module_id' => $moduleId, 'migration' => $migration]
        );

        return $row !== null;
    }

    private function record(string $moduleId, string $migration): void
    {
        $this->db->insert('core_migrations', [
            'module_id' => $moduleId,
            'migration' => $migration,
            'run_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Three core migrations (member_notes, audit_log, admin_notes) declare a
     * hard FK to users(id), but core has always run entirely before any
     * module — including 'users', which is what creates that table. On the
     * real dev/prod databases this never surfaced: migrations were applied
     * incrementally over many sessions, and by the time these three were
     * authored, 'users' already existed from a much earlier install. A
     * genuinely fresh install — a brand-new club site, or this project's own
     * first-ever automated test run against a from-scratch database
     * (2026-07-20) — hits it immediately. Listed explicitly by filename
     * rather than solved with a generic "core migrations can declare
     * requires" mechanism, since exactly three files need this and a whole
     * dependency system for a three-item, rarely-growing list would be
     * over-engineering; a future core migration that needs 'users' just adds
     * itself here.
     */
    private const CORE_MIGRATIONS_AFTER_USERS = [
        '006_add_member_notes.php',
        '010_create_audit_log.php',
        '011_create_admin_notes.php',
    ];

    /**
     * Runs 'core' migrations that don't depend on 'users', then every
     * discovered module's migrations in dependency order — 'users' always
     * first (everything else may assume it exists), then a simple
     * Kahn's-algorithm pass over module.json `requires` so a module's
     * dependency is always migrated before it is — then the handful of core
     * migrations deferred above, now that 'users' exists. The one shared
     * implementation for both `bin/install.php` (CLI) and the web installer —
     * no parallel ordering logic to drift out of sync.
     *
     * @return array<string, string[]> module_id (with 'core' first) => filenames applied
     */
    public function runAll(string $rootDir): array
    {
        $this->ensureMigrationsTable();

        $coreDir = $rootDir . '/core/migrations';
        $coreFiles = array_map('basename', glob($coreDir . '/*.php') ?: []);
        $coreFirstPass = array_diff($coreFiles, self::CORE_MIGRATIONS_AFTER_USERS);

        $results = ['core' => $this->run('core', $coreDir, $coreFirstPass)];

        $modulesDir = $rootDir . '/core/modules';
        $manifests = [];
        foreach (glob("{$modulesDir}/*/module.json") ?: [] as $path) {
            $manifest = json_decode((string) file_get_contents($path), true);
            if (is_array($manifest) && isset($manifest['id'])) {
                $manifests[$manifest['id']] = $manifest;
            }
        }

        $order = ['users'];
        $remaining = array_diff(array_keys($manifests), $order);

        while ($remaining !== []) {
            $progressed = false;

            foreach ($remaining as $id) {
                $requires = $manifests[$id]['requires'] ?? [];
                if (array_diff($requires, $order) === []) {
                    $order[] = $id;
                    $remaining = array_diff($remaining, [$id]);
                    $progressed = true;
                }
            }

            if (!$progressed) {
                // Unresolvable (missing/circular dependency) — fall back to discovery order
                // rather than looping forever; a misconfigured module.json shouldn't block install.
                $order = array_merge($order, $remaining);
                break;
            }
        }

        foreach ($order as $id) {
            $results[$id] = $this->run($id, "{$modulesDir}/{$id}/migrations");
        }

        $results['core'] = array_merge(
            $results['core'],
            $this->run('core', $coreDir, self::CORE_MIGRATIONS_AFTER_USERS)
        );

        return $results;
    }
}
