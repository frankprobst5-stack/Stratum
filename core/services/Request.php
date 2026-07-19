<?php

declare(strict_types=1);

namespace Stratum\Core;

final class Request
{
    /** @var array<string, string> route parameters, set by the Router after matching */
    private array $routeParams = [];

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, string> $server
     * @param array<string, array{name: string, type: string, tmp_name: string, error: int, size: int}> $files
     */
    private function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $body,
        private readonly array $server,
        private readonly array $files
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        return new self($method, $path, $_GET, $_POST, $_SERVER, $_FILES);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(string $key, ?string $default = null): ?string
    {
        return isset($this->query[$key]) ? (string) $this->query[$key] : $default;
    }

    public function input(string $key, ?string $default = null): ?string
    {
        return isset($this->body[$key]) ? (string) $this->body[$key] : $default;
    }

    /**
     * For nested form fields, e.g. `<input name="grants[3][7]">` — PHP already
     * nests these into $_POST, this just exposes that structure typed.
     *
     * @return array<mixed>
     */
    public function inputArray(string $key): array
    {
        $value = $this->body[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    /**
     * Raw $_FILES entry for $key, or null if nothing was uploaded (including
     * the browser's own "no file selected" case — UPLOAD_ERR_NO_FILE — since
     * an optional attachment field submits empty most of the time).
     *
     * @return array{name: string, type: string, tmp_name: string, error: int, size: int}|null
     */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return $file;
    }

    /**
     * Reconstructs a `name="x[]" multiple` upload field into a list of
     * normal per-file arrays — PHP gives multi-file inputs as parallel
     * arrays ($_FILES['x']['name'][0..n], ['tmp_name'][0..n], ...) rather
     * than one entry per file the way file() expects. Empty slots (no file
     * selected in that row) are skipped, same as file()'s UPLOAD_ERR_NO_FILE
     * handling.
     *
     * @return array<int, array{name: string, type: string, tmp_name: string, error: int, size: int}>
     */
    public function files(string $key): array
    {
        $raw = $this->files[$key] ?? null;
        if (!is_array($raw) || !is_array($raw['name'] ?? null)) {
            return [];
        }

        $result = [];
        foreach (array_keys($raw['name']) as $i) {
            if (($raw['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $result[] = [
                'name' => $raw['name'][$i],
                'type' => $raw['type'][$i],
                'tmp_name' => $raw['tmp_name'][$i],
                'error' => $raw['error'][$i],
                'size' => $raw['size'][$i],
            ];
        }

        return $result;
    }

    public function server(string $key, ?string $default = null): ?string
    {
        return $this->server[$key] ?? $default;
    }

    public function ip(): string
    {
        return $this->server('REMOTE_ADDR', '0.0.0.0');
    }

    /** Scheme + host, no trailing slash — e.g. "https://example.org". */
    public function baseUrl(): string
    {
        $scheme = $this->server('HTTPS') !== null ? 'https' : 'http';
        $host = $this->server('HTTP_HOST', 'localhost');

        return "{$scheme}://{$host}";
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function param(string $key, ?string $default = null): ?string
    {
        return $this->routeParams[$key] ?? $default;
    }
}
