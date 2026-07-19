<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Builds a downloadable zip from one of the two starter skeletons
 * (core/starters/addon/, core/starters/theme/) on demand, rather than
 * shipping a pre-built binary that could silently drift out of sync with
 * the source starter files whenever they're edited. Both starters are
 * tiny, so rebuilding on every request is not worth trading correctness
 * for — this is the same "compute, don't cache" default this app applies
 * throughout, applied here to a zip file instead of a query result.
 */
final class StarterPackageBuilder
{
    /** Returns the built zip's contents as a string — the caller streams it directly, nothing is left on disk. */
    public function build(string $sourceDir): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'stratum-starter-');
        if ($tmpPath === false) {
            throw new \RuntimeException('Could not create a temporary file for the starter package.');
        }

        try {
            $zip = new \ZipArchive();
            if ($zip->open($tmpPath, \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Could not create the starter zip archive.');
            }

            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($items as $item) {
                $relative = substr($item->getPathname(), strlen($sourceDir) + 1);
                if ($item->isDir()) {
                    $zip->addEmptyDir($relative);
                } else {
                    $zip->addFile($item->getPathname(), $relative);
                }
            }

            $zip->close();

            return (string) file_get_contents($tmpPath);
        } finally {
            @unlink($tmpPath);
        }
    }
}
