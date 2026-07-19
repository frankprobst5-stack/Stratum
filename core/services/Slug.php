<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Pure slugify — no DB uniqueness check, that stays with each service since
 * the uniqueness scope (which table) legitimately differs per caller.
 * Duplicated across articles/pages/forum before this; promoted here once
 * wiki became a fourth consumer doing the exact same thing. See
 * docs/coding-standards.md and the Stage 3c plan.
 */
final class Slug
{
    public static function make(string $value, string $fallback = 'item'): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : $fallback;
    }
}
