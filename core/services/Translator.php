<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Translatable-strings framework — flat PHP array language files under
 * `lang/`, not a database-backed table: no per-string admin editing UI,
 * no query per lookup, git-diffable, and matches how the e107/SMF/
 * ocPortal systems this app replaces already do i18n. Site-wide only
 * (one active language for the whole install, public and admin alike)
 * — not a per-user preference; the real use case driving this (a club
 * whose members are predominantly Spanish-speaking, say) wants the
 * whole site in one language, not each member picking their own.
 *
 * State is static, not instance-based, so the global `t()` helper in
 * helpers.php can call it without every template needing a Translator
 * object threaded through — the same reasoning `e()`/`route()` are
 * bare global functions rather than methods on an injected object.
 * `load()` is called once per request, early, from public/index.php.
 */
final class Translator
{
    /** @var array<string, string> */
    private static array $strings = [];

    /** @var array<string, string> */
    private static array $fallback = [];

    public static function load(string $langDir, string $activeLanguage): void
    {
        self::$fallback = self::readFile("{$langDir}/en.php");
        self::$strings = $activeLanguage !== 'en' ? self::readFile("{$langDir}/{$activeLanguage}.php") : [];
    }

    /** @param array<string, string> $replacements */
    public static function get(string $key, array $replacements = []): string
    {
        $value = self::$strings[$key] ?? self::$fallback[$key] ?? $key;

        if ($replacements === []) {
            return $value;
        }

        $search = array_map(static fn (string $k): string => '{' . $k . '}', array_keys($replacements));

        return str_replace($search, array_values($replacements), $value);
    }

    /** @return array<string, string> language code => its own display name (e.g. 'es' => 'Español') */
    public static function availableLanguages(string $langDir): array
    {
        $languages = [];
        foreach (glob("{$langDir}/*.php") ?: [] as $path) {
            $code = basename($path, '.php');
            $strings = self::readFile($path);
            $languages[$code] = $strings['_language_name'] ?? $code;
        }

        return $languages;
    }

    /** @return array<string, string> */
    private static function readFile(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $strings = require $path;

        return is_array($strings) ? $strings : [];
    }
}
