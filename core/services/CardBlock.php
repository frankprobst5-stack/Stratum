<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Optional interface (2026-07-19 design-system pass) letting a block
 * declare a title/icon/accent color and an optional "View All" link —
 * rendered as a shared header/footer by `BlockRegistry::renderRegion()`
 * itself, never hand-written per block. Same reasoning `ConfigurableBlock`
 * already established for settings forms: one shared rendering path
 * beats the same markup copy-pasted across 15 block classes.
 *
 * Only meaningful when a placement is card-wrapped ($wrapInCards); a
 * block implementing this that's placed in a non-card region (header,
 * topbar_actions, footer) just has its title/icon/CTA silently unused.
 */
interface CardBlock extends Block
{
    public function cardTitle(): string;

    /** An emoji, same convention as layout.php's $navIcons — no icon-font/SVG dependency. */
    public function cardIcon(): string;

    /** One of the fixed accent keys in docs/design-system.md: 'blue'|'green'|'orange'|'purple'|'gold'|'teal'|'red'|'cyan'. */
    public function cardAccent(): string;

    /** Route for a "View All" footer link, or null for no CTA footer. */
    public function viewAllUrl(): ?string;
}
