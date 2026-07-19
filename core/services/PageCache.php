<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Full-page HTML cache for guest (logged-out) GET requests — file-based,
 * not Redis/Memcached, same "don't assume an external service exists on
 * unknown shared hosting" posture this app has followed everywhere else
 * (ClamAvScanner, UpdateChecker, Cash App over Stripe). A cache hit is
 * checked in `public/index.php` before `ModuleManager::boot()` runs, so
 * it skips essentially the entire request pipeline — no module loading,
 * no routing, no controller logic, no DB content queries, no template
 * rendering — not just a faster version of the same work.
 *
 * Deliberately refuses to store any page containing a CSRF token
 * (`name="_csrf"`) — a cached page is later served to a *different*
 * visitor than whoever's request generated it, and a CSRF token is
 * bound to one specific session; caching one and handing it to everyone
 * would either break their form submissions or, worse, quietly leak one
 * visitor's session-bound token to others. This is a runtime content
 * check, not a route allow-list, so it self-defends against any future
 * page that happens to embed a form without needing to be told about it.
 */
final class PageCache
{
    public function __construct(
        private readonly string $cacheDir,
        private readonly int $ttlSeconds
    ) {
    }

    public function get(string $key): ?string
    {
        $path = $this->pathFor($key);
        if (!is_file($path)) {
            return null;
        }

        $mtime = filemtime($path);
        if ($mtime === false || $mtime + $this->ttlSeconds < time()) {
            @unlink($path);

            return null;
        }

        $body = file_get_contents($path);

        return $body !== false ? $body : null;
    }

    public function put(string $key, string $html): void
    {
        if (str_contains($html, 'name="_csrf"')) {
            return;
        }

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }

        file_put_contents($this->pathFor($key), $html);
    }

    public function clear(): void
    {
        foreach (glob("{$this->cacheDir}/*.html") ?: [] as $file) {
            @unlink($file);
        }
    }

    /** @return array{fileCount: int, totalBytes: int} */
    public function stats(): array
    {
        $files = glob("{$this->cacheDir}/*.html") ?: [];
        $totalBytes = 0;
        foreach ($files as $file) {
            $totalBytes += filesize($file) ?: 0;
        }

        return ['fileCount' => count($files), 'totalBytes' => $totalBytes];
    }

    /** Cache key is the exact request path + query string — different query strings are different pages (e.g. ?page=2). */
    private function pathFor(string $key): string
    {
        return $this->cacheDir . '/' . hash('sha256', $key) . '.html';
    }
}
