<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Shared zip-slip-safe extraction for admin-uploaded packages that are
 * NOT developer-signed — addons and themes, uploaded by a club's own
 * admin to their own install. Deliberately not `UpdatePackageVerifier`:
 * that class verifies a signature and a manifest-declared per-file hash
 * list against the developer's private key, a fundamentally different
 * trust model (protects every install from a single bad update reaching
 * all of them). An addon/theme upload only ever affects the uploading
 * admin's own site — the same trust level as FTP/cPanel access already
 * implicitly grants on any e107/SMF/WordPress install, not something a
 * signature check would meaningfully add to. What both installers still
 * need, and what lives here: the zip-slip path-safety discipline
 * (independent of whatever ZipArchive itself does or doesn't sanitize)
 * and manifest presence/shape validation, promoted here once themes
 * became this logic's second real consumer alongside addons.
 */
final class SafeZipExtractor
{
    /**
     * Extracts every entry in $zipPath into a fresh $stagingDir (wiped
     * first if it already exists), after validating every entry name is
     * path-safe and that $manifestFilename exists at the zip root and
     * parses as a JSON object with a string `id` matching $idPattern.
     * Nothing is left behind in $stagingDir on failure.
     *
     * @return array{manifest: array<string, mixed>, id: string}
     */
    public function extractValidated(
        string $zipPath,
        string $manifestFilename,
        string $idPattern,
        string $stagingDir
    ): array {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new PackageInstallException('Could not open the uploaded file as a zip archive.');
        }

        try {
            $entryNames = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name === false) {
                    continue;
                }
                $this->assertSafeEntryName($name);
                $entryNames[] = $name;
            }

            $manifestBytes = $zip->getFromName($manifestFilename);
            if ($manifestBytes === false) {
                throw new PackageInstallException("Not a valid package — missing {$manifestFilename} at the zip root.");
            }

            $manifest = json_decode($manifestBytes, true);
            if (!is_array($manifest) || !isset($manifest['id']) || !is_string($manifest['id'])) {
                throw new PackageInstallException("{$manifestFilename} is malformed or missing an \"id\" field.");
            }

            if (preg_match($idPattern, $manifest['id']) !== 1) {
                throw new PackageInstallException("\"{$manifest['id']}\" is not a valid id — lowercase letters, numbers, and underscores only, must start with a letter.");
            }

            $this->extractAll($zip, $entryNames, $stagingDir);

            /** @var array<string, mixed> $manifest */
            return ['manifest' => $manifest, 'id' => $manifest['id']];
        } finally {
            $zip->close();
        }
    }

    /**
     * Same defense `UpdatePackageVerifier::assertPathAllowed()` applies —
     * reject `..`, absolute paths, and null bytes — but simpler: there's
     * no pre-declared allow-list of top-level app directories to check
     * against here, since every entry lands inside a fresh, isolated
     * staging directory that's about to become the whole package's home,
     * not somewhere inside the live app tree.
     */
    private function assertSafeEntryName(string $name): void
    {
        if ($name === '' || str_contains($name, "\0") || str_starts_with($name, '/') || str_contains($name, '..')) {
            throw new PackageInstallException("Package contains a disallowed file path: {$name}");
        }
    }

    /** @param array<int, string> $entryNames */
    private function extractAll(\ZipArchive $zip, array $entryNames, string $stagingDir): void
    {
        if (is_dir($stagingDir)) {
            $this->removeDirectory($stagingDir);
        }
        mkdir($stagingDir, 0755, true);

        foreach ($entryNames as $name) {
            // Directory entries end in '/' — just ensure the directory exists, nothing to write.
            $destPath = $stagingDir . '/' . $name;
            if (str_ends_with($name, '/')) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
                continue;
            }

            $contents = $zip->getFromName($name);
            if ($contents === false) {
                $this->removeDirectory($stagingDir);
                throw new PackageInstallException("Failed to read {$name} from the zip.");
            }

            if (!is_dir(dirname($destPath))) {
                mkdir(dirname($destPath), 0755, true);
            }
            file_put_contents($destPath, $contents);
        }
    }

    /**
     * Public — AddonPackageInstaller/ThemePackageInstaller both reuse this
     * exact recursive-delete for their own remove() methods (uninstalling
     * a package is the same filesystem operation as cleaning up a failed
     * extraction), rather than each keeping a third copy of it.
     */
    public function removeDirectory(string $dir): void
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
}
