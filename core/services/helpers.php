<?php

declare(strict_types=1);

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('raw')) {
    function raw(string $value): string
    {
        return $value;
    }
}

if (!function_exists('route')) {
    /**
     * Stage 1: routes are plain paths, no named-route registry yet.
     * Kept as a helper so templates already call route('/path') and won't
     * need editing when named routes are introduced.
     */
    function route(string $path): string
    {
        return $path;
    }
}

if (!function_exists('t')) {
    /**
     * @param array<string, string> $replacements
     */
    function t(string $key, array $replacements = []): string
    {
        return \Stratum\Core\Translator::get($key, $replacements);
    }
}
