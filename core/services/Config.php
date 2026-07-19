<?php

declare(strict_types=1);

namespace Stratum\Core;

final class Config
{
    /** @var array<string, string> */
    private array $values;

    public function __construct(string $envFile)
    {
        $this->values = self::parseEnvFile($envFile);
    }

    /** @return array<string, string> */
    private static function parseEnvFile(string $path): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Config file not found: {$path}");
        }

        $values = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key = trim($key);
            $value = trim($value);
            $value = trim($value, "\"'");
            $values[$key] = $value;
        }

        return $values;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->values[$key] ?? $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->values[$key] ?? null;
        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->values[$key] ?? null;

        return $value === null ? $default : (int) $value;
    }

    public function dbDsn(): string
    {
        $host = $this->get('DB_HOST', '127.0.0.1');
        $port = $this->get('DB_PORT', '3306');
        $database = $this->get('DB_DATABASE', 'stratum');
        $charset = $this->get('DB_CHARSET', 'utf8mb4');

        return "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
    }

    public function dbPrefix(): string
    {
        return $this->get('DB_PREFIX', 'strat_');
    }

    public function isDebug(): bool
    {
        return $this->getBool('APP_DEBUG', false);
    }
}
