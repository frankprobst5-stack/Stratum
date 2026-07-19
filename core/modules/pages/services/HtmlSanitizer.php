<?php

declare(strict_types=1);

namespace Stratum\Modules\Pages;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Allow-listed HTML sanitizer for page bodies. Pages are the one place in
 * Stratum that stores real HTML instead of escaped text + BBCode — page
 * authorship is gated behind the pages.manage capability (admin/founder by
 * default), a materially different trust boundary than member-authored
 * forum posts or articles. Even so, admin-authored isn't zero-risk (a
 * compromised account, a pasted snippet), so this runs as defense in depth
 * rather than trusting the client-side editor alone. See
 * docs/coding-standards.md.
 */
final class HtmlSanitizer
{
    /** @var string[] */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u',
        'h1', 'h2', 'h3', 'h4',
        'ul', 'ol', 'li',
        'a', 'blockquote', 'pre', 'code',
        'table', 'thead', 'tbody', 'tr', 'td', 'th',
        'img', 'span', 'div',
    ];

    /** @var array<string, string[]> tag => allowed attribute names, plus a '*' entry applied to every tag */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title', 'target'],
        'img' => ['src', 'alt', 'width', 'height'],
        '*' => ['style'],
    ];

    /** @var string[] removed entirely, including their content */
    private const STRIPPED_WITH_CONTENT = ['script', 'iframe', 'object', 'embed', 'style', 'form'];

    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="utf-8"?><div id="stratum-sanitize-root">' . $html . '</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();

        $root = $doc->getElementById('stratum-sanitize-root');
        if ($root === null) {
            return '';
        }

        $this->cleanChildren($root);

        $output = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $doc->saveHTML($child);
        }

        return $output;
    }

    private function cleanChildren(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }

            if (!$child instanceof DOMElement) {
                $node->removeChild($child); // comments, processing instructions, etc.
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::STRIPPED_WITH_CONTENT, true)) {
                $node->removeChild($child);
                continue;
            }

            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                // Unwrap: keep the (still-to-be-cleaned) children, drop the wrapping tag itself.
                while ($child->firstChild !== null) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            $this->cleanAttributes($child, $tag);
            $this->cleanChildren($child);
        }
    }

    private function cleanAttributes(DOMElement $element, string $tag): void
    {
        $allowed = array_merge(self::ALLOWED_ATTRIBUTES[$tag] ?? [], self::ALLOWED_ATTRIBUTES['*']);

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);

            if (!in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->name);
                continue;
            }

            if (in_array($name, ['href', 'src'], true) && !$this->isSafeUrl($attribute->value)) {
                $element->removeAttribute($attribute->name);
                continue;
            }

            if ($name === 'style' && $this->isSuspiciousStyle($attribute->value)) {
                $element->removeAttribute($attribute->name);
            }
        }

        if ($tag === 'a' && $element->hasAttribute('href')) {
            $element->setAttribute('rel', 'nofollow noopener');
        }
    }

    private function isSafeUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        if ($url[0] === '/' || $url[0] === '#') {
            return true; // relative/local — never a javascript:/data: scheme
        }

        return (bool) preg_match('#^(https?|mailto):#i', $url);
    }

    private function isSuspiciousStyle(string $style): bool
    {
        return stripos($style, 'expression(') !== false || stripos($style, 'javascript:') !== false;
    }
}
