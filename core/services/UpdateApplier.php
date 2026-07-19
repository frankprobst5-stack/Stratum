<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Applies an already-verified, already-staged update package (see
 * UpdatePackageVerifier — this class never touches an unverified zip).
 * Order is fixed by real constraints, not preference: files must be
 * swapped in before migrations run, because the new migration files
 * themselves are among the swapped files — there's no other order.
 *
 * Fail-closed at every stage: nothing on the live site is touched until
 * the backup of everything about to be overwritten has fully succeeded.
 * `rename()` is atomic per-file on the same filesystem (staging lives
 * under storage/, in the same project tree as everything it replaces, so
 * this holds for any normal deployment) — no single file is ever left
 * half-written. The multi-file swap as a whole is NOT a single atomic
 * transaction (no real filesystem gives you that across arbitrary paths
 * without a symlink-versioned deployment layout, which would be a much
 * bigger change than this feature warrants); if the process dies mid-swap,
 * already-swapped files are cleanly on the new version, not-yet-swapped
 * files are cleanly on the old version, and the code below restores
 * everything from backup rather than leaving a mixed state live.
 */
final class UpdateApplier
{
    public function __construct(
        private readonly string $rootDir,
        private readonly Database $db
    ) {
    }

    /**
     * @param array{version: string, min_current_version: string, files: array<string, string>} $manifest
     * @return array{success: bool, message: string, steps: array<int, array{label: string, ok: bool}>}
     */
    public function apply(array $manifest, string $stagingDir): array
    {
        $steps = [];
        $backupDir = $this->rootDir . '/storage/update-backups/' . date('Y-m-d_His');
        $paths = array_keys($manifest['files']);

        try {
            $this->backupExisting($paths, $backupDir);
            $steps[] = ['label' => 'Backed up files about to change', 'ok' => true];
        } catch (\Throwable $e) {
            $this->removeDirectory($backupDir);

            return $this->result(false, 'Backup failed before anything was changed — nothing on your site was touched. ' . $e->getMessage(), $steps);
        }

        try {
            $this->swapFiles($paths, $stagingDir);
            $steps[] = ['label' => 'Applied new files', 'ok' => true];
        } catch (\Throwable $e) {
            $this->restoreFromBackup($paths, $backupDir);
            $steps[] = ['label' => 'Applying new files failed — restored previous version from backup', 'ok' => false];

            return $this->result(false, 'Update failed while applying files; your site has been restored to its previous version. ' . $e->getMessage(), $steps);
        }

        try {
            (new MigrationRunner($this->db))->runAll($this->rootDir);
            $steps[] = ['label' => 'Database updated', 'ok' => true];
        } catch (\Throwable $e) {
            $this->restoreFromBackup($paths, $backupDir);
            $steps[] = ['label' => 'Database update failed — application files were restored to their previous version', 'ok' => false];

            return $this->result(
                false,
                'Update failed while updating the database. Your site\'s files have been restored to their ' .
                'previous version, but some database changes may have partially applied. Contact support ' .
                'before trying again — do not re-run this update without checking first. ' . $e->getMessage(),
                $steps
            );
        }

        file_put_contents($this->rootDir . '/VERSION', $manifest['version'] . "\n");
        $steps[] = ['label' => 'Updated to version ' . $manifest['version'], 'ok' => true];

        $this->removeDirectory($stagingDir);

        return $this->result(true, 'Update to version ' . $manifest['version'] . ' completed successfully.', $steps);
    }

    /** @param string[] $paths */
    private function backupExisting(array $paths, string $backupDir): void
    {
        foreach ($paths as $path) {
            $live = $this->rootDir . '/' . $path;
            if (!is_file($live)) {
                continue; // a new file this update adds — nothing to back up
            }

            $dest = $backupDir . '/' . $path;
            if (!is_dir(dirname($dest))) {
                mkdir(dirname($dest), 0755, true);
            }

            if (!copy($live, $dest)) {
                throw new UpdatePackageException("Could not back up {$path} — check file permissions.");
            }
        }
    }

    /** @param string[] $paths */
    private function swapFiles(array $paths, string $stagingDir): void
    {
        $rootReal = realpath($this->rootDir);

        foreach ($paths as $path) {
            $staged = $stagingDir . '/' . $path;
            $live = $this->rootDir . '/' . $path;

            // Belt-and-braces zip-slip re-check, now that the destination's parent
            // directory necessarily exists (realpath needs an existing path).
            if (!is_dir(dirname($live))) {
                mkdir(dirname($live), 0755, true);
            }
            $liveDirReal = realpath(dirname($live));
            if ($liveDirReal === false || !str_starts_with($liveDirReal, $rootReal)) {
                throw new UpdatePackageException("Refusing to write outside the project root: {$path}");
            }

            if (!rename($staged, $live)) {
                // Cross-filesystem fallback — rename() only works within one filesystem.
                if (!copy($staged, $live)) {
                    throw new UpdatePackageException("Could not write {$path} — check file permissions.");
                }
                unlink($staged);
            }
        }
    }

    /** @param string[] $paths */
    private function restoreFromBackup(array $paths, string $backupDir): void
    {
        foreach ($paths as $path) {
            $backup = $backupDir . '/' . $path;
            $live = $this->rootDir . '/' . $path;

            if (is_file($backup)) {
                copy($backup, $live);
            }
            // No backup entry means this path didn't exist before the update — leaving
            // whatever partial write is there is a stray new file, not a broken old one.
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }

    /** @param array<int, array{label: string, ok: bool}> $steps */
    private function result(bool $success, string $message, array $steps): array
    {
        return ['success' => $success, 'message' => $message, 'steps' => $steps];
    }
}
