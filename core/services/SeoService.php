<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Small, focused home for the one piece of SEO logic that's genuinely
 * shared across content types: turning a raw BBCode/plain-text body into a
 * clean meta-description-length excerpt. Everything else SEO-related
 * (fallback title/canonical/OG-image resolution) lives directly in
 * App::renderPage() — it's one consumer, not worth a service method.
 * Promoted here once wiki, pages, and forum topics all needed the same
 * "strip markup, truncate on a word boundary" logic for pages with no
 * dedicated excerpt column (articles already has one).
 */
final class SeoService
{
    public function excerpt(string $rawBody, int $length = 160): string
    {
        $plain = preg_replace('#\[/?[a-z][a-z0-9]*(=[^\]]*)?\]#i', '', $rawBody) ?? $rawBody;
        $plain = trim(preg_replace('/\s+/', ' ', $plain) ?? $plain);

        if ($plain === '' || mb_strlen($plain) <= $length) {
            return $plain;
        }

        $truncated = mb_substr($plain, 0, $length);
        $lastSpace = mb_strrpos($truncated, ' ');
        if ($lastSpace !== false) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return $truncated . '…';
    }
}
