<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Installs/removes admin-uploaded addons — zips shaped exactly like a
 * built-in module (a `module.json` at the root plus whichever of
 * services/, controllers/, migrations/, templates/, Module.php,
 * routes.php it needs), so an addon IS a module in every way
 * `ModuleManager` already understands; the only thing new here is where
 * it physically lives (`storage/addons/{id}/`, scanned as
 * ModuleManager's second, custom source directory) and how it got there
 * (an upload, not part of the codebase).
 */
final class AddonPackageInstaller
{
    private const ID_PATTERN = '/^[a-z][a-z0-9_]*$/';

    public function __construct(
        private readonly string $coreModulesDir,
        private readonly string $customModulesDir
    ) {
    }

    /** Returns the new addon's id on success. */
    public function install(string $zipPath): string
    {
        $stagingDir = $this->customModulesDir . '/.staging-' . bin2hex(random_bytes(8));
        $extractor = new SafeZipExtractor();

        $result = $extractor->extractValidated($zipPath, 'module.json', self::ID_PATTERN, $stagingDir);
        $id = $result['id'];

        if (is_dir("{$this->coreModulesDir}/{$id}") || is_dir("{$this->customModulesDir}/{$id}")) {
            $extractor->removeDirectory($stagingDir);
            throw new PackageInstallException("A module with the id \"{$id}\" already exists — remove it first or choose a different id in module.json.");
        }

        // $this->customModulesDir is guaranteed to exist here — extraction
        // above already created it (recursively) as the staging dir's parent.
        rename($stagingDir, "{$this->customModulesDir}/{$id}");

        return $id;
    }

    /** True if a custom addon existed with this id and was removed. Refuses to touch anything outside the custom addons directory. */
    public function remove(string $id): bool
    {
        if (preg_match(self::ID_PATTERN, $id) !== 1) {
            return false;
        }

        $path = "{$this->customModulesDir}/{$id}";
        if (!is_dir($path)) {
            return false;
        }

        (new SafeZipExtractor())->removeDirectory($path);

        return true;
    }
}
