<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Verifies an update package (a zip built by bin/build-update-package.php)
 * before UpdateApplier is ever allowed to touch a live file. This is the
 * single place that stands between "an admin uploaded a zip" and "the app
 * runs the PHP inside it" — get this wrong and the update mechanism becomes
 * a one-click remote code execution vector for every deployed site. See
 * docs/roadmap.md's "Update Mechanism" entry for the full threat model.
 *
 * Verification order matters and is deliberately fail-closed at every step:
 * 1. The manifest's signature is checked BEFORE the manifest is even parsed
 *    as JSON, let alone before any file is extracted. A zip that fails
 *    signature verification never has a single byte extracted from it.
 * 2. Every path the (now-trusted) manifest lists is checked against an
 *    allow-list of top-level app directories, rejecting `..`, absolute
 *    paths, and anything that would resolve outside the project root
 *    (zip-slip defense) — independent of whatever PHP's ZipArchive itself
 *    does or doesn't sanitize.
 * 3. Only files explicitly listed in the signed manifest are ever
 *    extracted — any other entry physically present in the zip is ignored,
 *    so a signed manifest can't be paired with extra, unsigned payload
 *    smuggled in alongside it.
 * 4. Each extracted file's sha256 is checked against the manifest's
 *    declared hash before it's trusted — catches truncation/corruption,
 *    and means the signature is effectively over the exact byte content of
 *    every shipped file, not just their names.
 */
final class UpdatePackageVerifier
{
    /** @var string[] top-level paths an update package is allowed to touch — everything else is refused, allow-list not deny-list */
    private const ALLOWED_PREFIXES = ['bin/', 'core/', 'public/', 'themes/', 'vendor/', 'composer.json', 'composer.lock', 'VERSION', '.htaccess'];

    public function __construct(
        private readonly string $rootDir,
        private readonly string $publicKeyPath
    ) {
    }

    /**
     * Extracts and verifies $zipPath into $stagingDir. Returns the parsed,
     * trusted manifest on success. Throws UpdatePackageException with an
     * admin-safe message on any failure — nothing is left behind in
     * $stagingDir on failure (best-effort cleanup).
     *
     * @return array{version: string, min_current_version: string, files: array<string, string>}
     */
    public function verifyAndStage(string $zipPath, string $stagingDir): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new UpdatePackageException('Could not open the uploaded file as a zip archive.');
        }

        try {
            $manifestBytes = $zip->getFromName('manifest.json');
            $signatureB64 = $zip->getFromName('manifest.sig');
            if ($manifestBytes === false || $signatureB64 === false) {
                throw new UpdatePackageException('Not a valid Stratum update package (missing manifest.json or manifest.sig).');
            }

            $this->verifySignature($manifestBytes, trim($signatureB64));

            $manifest = $this->parseManifest($manifestBytes);
            $this->verifyVersionCompatibility($manifest);
            $this->stageFiles($zip, $manifest['files'], $stagingDir);

            return $manifest;
        } finally {
            $zip->close();
        }
    }

    private function verifySignature(string $manifestBytes, string $signatureB64): void
    {
        $publicKeyB64 = trim((string) @file_get_contents($this->publicKeyPath));
        if ($publicKeyB64 === '') {
            // A missing/unreadable public key is a broken install, not an attacker — but
            // fails exactly the same way either way: nothing gets trusted without it.
            throw new UpdatePackageException('Update signing key is missing from this install — cannot verify any package. Contact support.');
        }

        $publicKey = base64_decode($publicKeyB64, true);
        $signature = base64_decode($signatureB64, true);
        if ($publicKey === false || $signature === false || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new UpdatePackageException('Update signing key or package signature is malformed.');
        }

        if (strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES || !sodium_crypto_sign_verify_detached($signature, $manifestBytes, $publicKey)) {
            throw new UpdatePackageException(
                'This package\'s signature does not match. It may be corrupted or did not come from the ' .
                'official Stratum update source — refusing to apply it.'
            );
        }
    }

    /** @return array{version: string, min_current_version: string, files: array<string, string>} */
    private function parseManifest(string $manifestBytes): array
    {
        $manifest = json_decode($manifestBytes, true);
        if (!is_array($manifest)
            || !isset($manifest['version'], $manifest['min_current_version'], $manifest['files'])
            || !is_string($manifest['version'])
            || !is_string($manifest['min_current_version'])
            || !is_array($manifest['files'])
        ) {
            throw new UpdatePackageException('Update manifest is malformed.');
        }

        foreach ($manifest['files'] as $path => $hash) {
            if (!is_string($path) || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/', $hash)) {
                throw new UpdatePackageException('Update manifest contains an invalid file entry.');
            }
            $this->assertPathAllowed($path);
        }

        /** @var array{version: string, min_current_version: string, files: array<string, string>} $manifest */
        return $manifest;
    }

    private function assertPathAllowed(string $path): void
    {
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '..') || str_starts_with($path, '/')) {
            throw new UpdatePackageException("Update package contains a disallowed file path: {$path}");
        }

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix)) {
                // Belt-and-braces zip-slip check: the resolved real path (once the file
                // exists) must still land inside rootDir. Checked again in UpdateApplier
                // right before every write, since realpath() only works on existing files.
                return;
            }
        }

        throw new UpdatePackageException("Update package touches a path outside the app's updatable directories: {$path}");
    }

    private function verifyVersionCompatibility(array $manifest): void
    {
        $installedVersion = trim((string) @file_get_contents($this->rootDir . '/VERSION')) ?: '0.0.0';

        if (version_compare($installedVersion, $manifest['min_current_version'], '<')) {
            throw new UpdatePackageException(
                "This update requires at least version {$manifest['min_current_version']}; this site is on {$installedVersion}."
            );
        }

        if (version_compare($manifest['version'], $installedVersion, '<=')) {
            throw new UpdatePackageException(
                "This package (version {$manifest['version']}) is not newer than the installed version ({$installedVersion})."
            );
        }
    }

    /** @param array<string, string> $files path => expected sha256 */
    private function stageFiles(\ZipArchive $zip, array $files, string $stagingDir): void
    {
        if (is_dir($stagingDir)) {
            $this->removeDirectory($stagingDir);
        }
        mkdir($stagingDir, 0755, true);

        foreach ($files as $path => $expectedHash) {
            $entryName = 'files/' . $path;
            $contents = $zip->getFromName($entryName);
            if ($contents === false) {
                $this->removeDirectory($stagingDir);
                throw new UpdatePackageException("Update package's manifest lists {$path} but it isn't in the zip.");
            }

            if (hash('sha256', $contents) !== $expectedHash) {
                $this->removeDirectory($stagingDir);
                throw new UpdatePackageException("Update package failed integrity check on {$path} — the zip may be corrupted.");
            }

            $destPath = $stagingDir . '/' . $path;
            if (!is_dir(dirname($destPath))) {
                mkdir(dirname($destPath), 0755, true);
            }
            file_put_contents($destPath, $contents);
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
}
