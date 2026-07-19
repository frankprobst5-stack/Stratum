<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Allow-listed BBCode -> HTML. The raw body is escaped first (via the
 * global e() helper); every regex below only ever matches and rewrites
 * bracket patterns inside that already-escaped text, so the content between
 * tags can never smuggle in an arbitrary HTML tag — only the fixed set of
 * tags this parser itself emits ever appears in the output. See
 * docs/coding-standards.md ("allow-listed BBCode/Markdown parser, never raw
 * HTML storage").
 *
 * Originally built for the forum module (Stage 3b); promoted to core when
 * articles became a second consumer, per the promotion rule set at the time
 * ("promote to core/services/ if a second module needs it").
 */
final class BBCodeParser
{
    public function render(string $raw): string
    {
        $html = e($raw);

        $html = preg_replace('#\[b\](.*?)\[/b\]#is', '<strong>$1</strong>', $html) ?? $html;
        $html = preg_replace('#\[i\](.*?)\[/i\]#is', '<em>$1</em>', $html) ?? $html;
        $html = preg_replace('#\[u\](.*?)\[/u\]#is', '<u>$1</u>', $html) ?? $html;
        $html = preg_replace('#\[code\](.*?)\[/code\]#is', '<pre><code>$1</code></pre>', $html) ?? $html;
        $html = preg_replace('#\[quote\](.*?)\[/quote\]#is', '<blockquote>$1</blockquote>', $html) ?? $html;

        $html = preg_replace_callback(
            '#\[url=(.*?)\](.*?)\[/url\]#is',
            fn (array $m): string => $this->renderUrlTag($m[1], $m[2]),
            $html
        ) ?? $html;

        $html = preg_replace_callback(
            '#\[url\](.*?)\[/url\]#is',
            fn (array $m): string => $this->renderUrlTag($m[1], $m[1]),
            $html
        ) ?? $html;

        return $html;
    }

    /**
     * $url and $label are already HTML-escaped (the upstream e() call in
     * render()) — decoding here is only to validate the scheme, never to
     * change what gets written to output. The escaped originals are used in
     * the returned markup, so this stays safe even placed in an href.
     */
    private function renderUrlTag(string $url, string $label): string
    {
        $decoded = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
        if (!preg_match('#^https?://#i', $decoded)) {
            return "[url={$url}]{$label}[/url]";
        }

        return '<a href="' . $url . '" rel="nofollow noopener" target="_blank">' . $label . '</a>';
    }
}
