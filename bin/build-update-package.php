#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Stratum CMS — builds a signed update package (a zip) from a source
 * checkout, ready to hand to a club admin to upload via /admin/system/update.
 * Never run on a deployed site — this is developer-side tooling, paired
 * with bin/generate-update-keypair.php. The private key never leaves your
 * machine; only the signed zip does.
 *
 * Usage: php bin/build-update-package.php <source-dir> <output.zip> <version> <min-current-version> <private-key-path> [baseline-dir]
 * Example: php bin/build-update-package.php . stratum-1.1.0.zip 1.1.0 1.0.0 /secure/path/update-private.key /path/to/v1.0.0-checkout
 *
 * The optional baseline-dir is a checkout of the PREVIOUS shipped version —
 * when given, any file whose content is byte-identical to the same path in
 * the baseline is left out of the package entirely (still verified/trusted
 * as unchanged, just not re-shipped). Without it, every file under the
 * allowed prefixes goes in every time, which is simple but means large,
 * rarely-changing binary assets (brand images, vendor JS) bloat every
 * update — this app already has ~9MB of such images alone, comfortably
 * over the 2-8MB upload_max_filesize/post_max_size many shared hosts
 * default to. Always pass a baseline for any real release after the first.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

/** @var string[] must match UpdatePackageVerifier::ALLOWED_PREFIXES exactly — a mismatch here just means the built package gets rejected on upload, not a security issue, but keep them in sync. */
const ALLOWED_PREFIXES = ['bin/', 'core/', 'public/', 'themes/', 'vendor/', 'composer.json', 'composer.lock', '.htaccess'];

[$sourceDir, $outputZip, $version, $minCurrentVersion, $privateKeyPath, $baselineDir] = array_pad(array_slice($argv, 1), 6, null);

if ($sourceDir === null || $outputZip === null || $version === null || $minCurrentVersion === null || $privateKeyPath === null) {
    fwrite(STDERR, "Usage: php bin/build-update-package.php <source-dir> <output.zip> <version> <min-current-version> <private-key-path> [baseline-dir]\n");
    exit(1);
}

if ($baselineDir !== null) {
    $baselineDir = rtrim((string) realpath($baselineDir), '/');
    if ($baselineDir === '' || !is_dir($baselineDir)) {
        fwrite(STDERR, "Baseline directory not found.\n");
        exit(1);
    }
}

$sourceDir = rtrim((string) realpath($sourceDir), '/');
if ($sourceDir === '' || !is_dir($sourceDir)) {
    fwrite(STDERR, "Source directory not found.\n");
    exit(1);
}

if (!is_file($privateKeyPath)) {
    fwrite(STDERR, "Private key not found at {$privateKeyPath}.\n");
    exit(1);
}

if (!preg_match('/^\d+\.\d+\.\d+$/', $version) || !preg_match('/^\d+\.\d+\.\d+$/', $minCurrentVersion)) {
    fwrite(STDERR, "Versions must be plain semver, e.g. 1.1.0.\n");
    exit(1);
}

fwrite(STDOUT, "Scanning {$sourceDir}...\n");

$allPaths = [];
foreach (ALLOWED_PREFIXES as $prefix) {
    $fullPrefix = "{$sourceDir}/{$prefix}";

    if (is_file($fullPrefix)) {
        $allPaths[] = $prefix;
        continue;
    }

    if (!is_dir($fullPrefix)) {
        continue; // e.g. vendor/ might not exist if composer install was never run — fine, skip
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($fullPrefix, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $allPaths[] = substr($file->getPathname(), strlen($sourceDir) + 1);
        }
    }
}

if ($allPaths === []) {
    fwrite(STDERR, "No files found under the allowed prefixes — nothing to package.\n");
    exit(1);
}

$files = [];
$skippedUnchanged = 0;
foreach ($allPaths as $relativePath) {
    $hash = hash_file('sha256', "{$sourceDir}/{$relativePath}");

    if ($baselineDir !== null) {
        $baselinePath = "{$baselineDir}/{$relativePath}";
        if (is_file($baselinePath) && hash_file('sha256', $baselinePath) === $hash) {
            $skippedUnchanged++;
            continue; // byte-identical to the baseline — not shipped, UpdateApplier never touches it
        }
    }

    $files[$relativePath] = $hash;
}

if ($files === []) {
    fwrite(STDERR, "No changed files found relative to the baseline — nothing to package.\n");
    exit(1);
}

fwrite(STDOUT, 'Found ' . count($allPaths) . ' files, ' . count($files) . ' changed'
    . ($baselineDir !== null ? " ({$skippedUnchanged} unchanged, skipped)" : ' (no baseline given, shipping all)') . ".\n");

$manifest = [
    'version' => $version,
    'min_current_version' => $minCurrentVersion,
    'generated_at' => date('c'),
    'files' => $files,
];
$manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($manifestJson === false) {
    fwrite(STDERR, "Failed to encode manifest.\n");
    exit(1);
}

$secretKey = base64_decode(trim((string) file_get_contents($privateKeyPath)), true);
if ($secretKey === false || strlen($secretKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
    fwrite(STDERR, "Private key is malformed.\n");
    exit(1);
}

$signature = base64_encode(sodium_crypto_sign_detached($manifestJson, $secretKey));
sodium_memzero($secretKey);

if (is_file($outputZip)) {
    unlink($outputZip);
}

$zip = new ZipArchive();
if ($zip->open($outputZip, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Could not create {$outputZip}.\n");
    exit(1);
}

$zip->addFromString('manifest.json', $manifestJson);
$zip->addFromString('manifest.sig', $signature);

foreach (array_keys($files) as $relativePath) {
    $zip->addFile("{$sourceDir}/{$relativePath}", "files/{$relativePath}");
}

$zip->close();

fwrite(STDOUT, "Built and signed: {$outputZip} (version {$version}, requires >= {$minCurrentVersion})\n");
