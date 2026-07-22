<?php

declare(strict_types=1);

namespace Stratum\Core;

final class BlockRegistry
{
    /** @var array<string, callable(): Block> */
    private array $factories = [];

    public function __construct(private readonly Database $db)
    {
    }

    /** @param callable(): Block $factory */
    public function register(string $blockType, callable $factory): void
    {
        $this->factories[$blockType] = $factory;
    }

    /** @return array<int, string> every block type registered so far — backs the admin placement UI's "add a block" dropdown. */
    public function registeredTypes(): array
    {
        return array_keys($this->factories);
    }

    /**
     * Constructs a block instance without rendering it — the admin
     * placement UI needs this to call `configFields()` on a
     * `ConfigurableBlock` (to generate its settings form) without also
     * running its `render()` side effects (e.g. `ads.banner`
     * incrementing an impression count just because an admin opened the
     * settings page). Null if the type isn't registered (owning module
     * disabled or removed).
     */
    public function make(string $blockType): ?Block
    {
        $factory = $this->factories[$blockType] ?? null;

        return $factory !== null ? $factory() : null;
    }

    /**
     * $wrapInCards: true wraps each individual block's rendered output in
     * a `.strat-block-card` div (background/border/spacing, see
     * layout.php's CSS) rather than letting stacked blocks in the same
     * region visually run together with nothing separating them —
     * requested 2026-07-18 for the front-page columns specifically.
     * Deliberately a per-call opt-in, not the default: compact regions
     * like `header`/`topbar_actions`/`footer` (ticker, ads, the search/
     * notifications icons) are meant to stay inline, not become cards.
     */
    public function renderRegion(string $regionKey, string $currentPath, bool $wrapInCards = false): string
    {
        $regionTable = $this->db->table('block_regions');
        $placementTable = $this->db->table('block_placements');

        $placements = $this->db->fetchAll(
            "SELECT p.block_type, p.page_scope, p.config_json
             FROM {$placementTable} p
             JOIN {$regionTable} r ON r.id = p.region_id
             WHERE r.`key` = :region AND p.is_enabled = 1
             ORDER BY p.weight ASC",
            ['region' => $regionKey]
        );

        $output = '';
        foreach ($placements as $placement) {
            if (!$this->appliesToPath($placement['page_scope'], $currentPath)) {
                continue;
            }

            $factory = $this->factories[$placement['block_type']] ?? null;
            if ($factory === null) {
                continue; // block's owning module is disabled or not registered
            }

            $config = $placement['config_json'] !== null
                ? (json_decode((string) $placement['config_json'], true) ?: [])
                : [];

            $block = $factory();
            $rendered = $block->render($config);
            if ($wrapInCards && trim($rendered) !== '') {
                $rendered = $this->wrapCard($block, $rendered);
            }

            $output .= $rendered;
        }

        return $output;
    }

    /**
     * The one place a CardBlock's title/icon/accent/CTA ever gets
     * rendered — see CardBlock's own docblock for why this isn't
     * hand-written per block. A block that doesn't implement CardBlock
     * still gets the plain `.sc-card` shell it always had.
     *
     * Restyled 2026-07-21 onto the new dashboard.css system (`sc-`
     * classes) — same header/CTA shape as before, moved the "View All"
     * link from a full-width footer button into the header row itself
     * (matches the design reference the CSS integration was built
     * against), added a colored icon badge reusing theme.css's existing
     * 8-accent system via the shared --strat-color-* variables.
     */
    private function wrapCard(Block $block, string $rendered): string
    {
        if (!$block instanceof CardBlock) {
            return '<div class="sc-card">' . $rendered . '</div>';
        }

        $viewAllUrl = $block->viewAllUrl();
        $cta = $viewAllUrl !== null
            ? '<a class="sc-card-cta" href="' . e(route($viewAllUrl)) . '">View All &rarr;</a>'
            : '';

        $header = '<div class="sc-card-header">'
            . '<span class="sc-card-title">'
            . '<span class="sc-card-icon-badge" data-accent="' . e($block->cardAccent()) . '">' . $block->cardIcon() . '</span>'
            . e($block->cardTitle())
            . '</span>'
            . $cta
            . '</div>';

        return '<div class="sc-card">' . $header . $rendered . '</div>';
    }

    private function appliesToPath(string $pageScope, string $currentPath): bool
    {
        return match (true) {
            $pageScope === 'site_wide' => true,
            $pageScope === 'front_page_only' => $currentPath === '/',
            default => $pageScope === $currentPath,
        };
    }
}
