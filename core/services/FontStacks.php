<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * A small curated set of real font stacks for the Stage 8 color/
 * typography manager — deliberately not a free-text field. Two reasons:
 * (1) it's rendered directly inside a `<style>` block in layout.php, and
 * while `e()` already prevents breaking out of that block, a free-text
 * value could still be genuinely invalid CSS a non-technical admin has
 * no way to debug; (2) a curated picker matches how this kind of
 * customizer normally works (WordPress's own font controls are a preset
 * list too), not a design shortcut.
 */
final class FontStacks
{
    /** @var array<string, array{label: string, css: string}> */
    public const OPTIONS = [
        'system' => ['label' => 'System Default', 'css' => 'system-ui, -apple-system, "Segoe UI", sans-serif'],
        'serif' => ['label' => 'Classic Serif', 'css' => 'Georgia, "Times New Roman", serif'],
        'rounded' => ['label' => 'Friendly Sans-Serif', 'css' => '"Trebuchet MS", Verdana, sans-serif'],
        'mono' => ['label' => 'Monospace', 'css' => '"Courier New", monospace'],
    ];

    public static function isValid(string $key): bool
    {
        return isset(self::OPTIONS[$key]);
    }

    public static function cssFor(string $key): string
    {
        return self::OPTIONS[$key]['css'] ?? self::OPTIONS['system']['css'];
    }
}
