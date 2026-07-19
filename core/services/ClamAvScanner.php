<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Shells out to the system `clamscan` binary if one is actually present
 * and callable — this app can't assume ClamAV exists on an unknown
 * shared host (the whole hosting premise for the 8 migrating clubs), so
 * "no scanner available" has to be its own distinct, non-blocking
 * result rather than defaulting to either "clean" (false confidence) or
 * "infected" (breaks uploads entirely on most shared hosts). Only a
 * real positive hit from a real scanner run ever returns 'infected'.
 */
final class ClamAvScanner
{
    public function scan(string $filePath): string
    {
        $binary = $this->locateBinary();
        if ($binary === null) {
            return 'unavailable';
        }

        exec($binary . ' --no-summary ' . escapeshellarg($filePath) . ' 2>&1', $output, $exitCode);

        // clamscan exit codes: 0 = clean, 1 = virus found, 2 = scan error
        return match ($exitCode) {
            0 => 'clean',
            1 => 'infected',
            default => 'unavailable',
        };
    }

    private function locateBinary(): ?string
    {
        if (!$this->execCallable()) {
            return null;
        }

        $path = trim((string) @shell_exec('command -v clamscan 2>/dev/null'));

        return $path !== '' ? $path : null;
    }

    private function execCallable(): bool
    {
        if (!function_exists('exec') || !function_exists('shell_exec')) {
            return false;
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return !in_array('exec', $disabled, true) && !in_array('shell_exec', $disabled, true);
    }
}
