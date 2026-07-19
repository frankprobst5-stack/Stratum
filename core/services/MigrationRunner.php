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
     * @return string[] filenames applied
     */
    public function run(string $moduleId, string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $applied = [];
        $files = glob($directory . '/*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            $migration = basename($file);

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
     * Runs 'core' migrations, then every discovered module's migrations in
     * dependency order — 'users' always first (everything else may assume
     * it exists), then a simple Kahn's-algorithm pass over module.json
     * `requires` so a module's dependency is always migrated before it is.
     * The one shared implementation for both `bin/install.php` (CLI) and
     * the web installer — no parallel ordering logic to drift out of sync.
     *
     * @return array<string, string[]> module_id (with 'core' first) => filenames applied
     */
    public function runAll(string $rootDir): array
    {
        $this->ensureMigrationsTable();

        $results = ['core' => $this->run('core', $rootDir . '/core/migrations')];

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

        return $results;
    }
}
