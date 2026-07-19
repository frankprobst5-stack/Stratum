<?php

declare(strict_types=1);

namespace Stratum\Modules\CustomBlocks;

use Stratum\Core\CardBlock;
use Stratum\Core\ConfigurableBlock;

/**
 * Rebuilt 2026-07-19 (design-system pass) — the original version was a
 * vertical list of 5 hardcoded admin-only nav shortcuts, gated on
 * `admin.access`, and rendered nothing at all for a regular visitor.
 * That didn't match `look.png`'s "Quick Links" card (a public 2x2
 * colored-icon-tile grid pointing at real site sections) and duplicated
 * what the admin dashboard's own "Quick Actions" panel already covers —
 * two different jobs were living in one block. This is now that public
 * block: four tiles, each admin-picked from a small curated list of real
 * destinations (not free-text — icon/color per destination is a fixed,
 * known mapping, avoiding a 16-field config form for four tiles).
 */
final class QuickLinksBlock implements ConfigurableBlock, CardBlock
{
    /** @var array<string, array{label: string, route: string, icon: string, accent: string}> */
    private const DESTINATIONS = [
        'articles' => ['label' => 'Articles', 'route' => '/articles', 'icon' => "\u{1F4F0}", 'accent' => 'blue'],
        'forum' => ['label' => 'Forum', 'route' => '/forum', 'icon' => "\u{1F4AC}", 'accent' => 'green'],
        'downloads' => ['label' => 'Downloads', 'route' => '/downloads', 'icon' => "\u{2B07}\u{FE0F}", 'accent' => 'orange'],
        'calendar' => ['label' => 'Calendar', 'route' => '/calendar', 'icon' => "\u{1F4C5}", 'accent' => 'purple'],
        'gallery' => ['label' => 'Gallery', 'route' => '/gallery', 'icon' => "\u{1F5BC}\u{FE0F}", 'accent' => 'purple'],
        'wiki' => ['label' => 'Wiki', 'route' => '/wiki', 'icon' => "\u{1F4D6}", 'accent' => 'teal'],
        'chat' => ['label' => 'Chat', 'route' => '/chat', 'icon' => "\u{1F5E8}\u{FE0F}", 'accent' => 'cyan'],
        'classifieds' => ['label' => 'Classifieds', 'route' => '/classifieds', 'icon' => "\u{1F3F7}\u{FE0F}", 'accent' => 'gold'],
    ];

    /** @return array<int, array<string, mixed>> */
    public function configFields(): array
    {
        $options = array_combine(array_keys(self::DESTINATIONS), array_column(self::DESTINATIONS, 'label'));
        $defaults = ['tile1' => 'articles', 'tile2' => 'forum', 'tile3' => 'downloads', 'tile4' => 'calendar'];

        $fields = [];
        foreach ($defaults as $name => $default) {
            $fields[] = ['name' => $name, 'label' => 'Tile ' . substr($name, -1), 'type' => 'select', 'options' => $options, 'default' => $default];
        }

        return $fields;
    }

    /** @param array<string, mixed> $config */
    public function render(array $config): string
    {
        $keys = [
            (string) ($config['tile1'] ?? 'articles'),
            (string) ($config['tile2'] ?? 'forum'),
            (string) ($config['tile3'] ?? 'downloads'),
            (string) ($config['tile4'] ?? 'calendar'),
        ];

        $tiles = '';
        foreach ($keys as $key) {
            $destination = self::DESTINATIONS[$key] ?? null;
            if ($destination === null) {
                continue;
            }

            $tiles .= '<a class="strat-quick-link-tile" href="' . e(route($destination['route'])) . '">'
                . '<span class="strat-icon-badge" data-accent="' . e($destination['accent']) . '">' . $destination['icon'] . '</span>'
                . e($destination['label'])
                . '</a>';
        }

        if ($tiles === '') {
            return '';
        }

        return '<div class="strat-quick-link-grid">' . $tiles . '</div>';
    }

    public function cardTitle(): string
    {
        return 'Quick Links';
    }

    public function cardIcon(): string
    {
        return "\u{1F517}";
    }

    public function cardAccent(): string
    {
        return 'blue';
    }

    public function viewAllUrl(): ?string
    {
        return null;
    }
}
