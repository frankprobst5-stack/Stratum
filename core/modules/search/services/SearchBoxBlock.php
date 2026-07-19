<?php

declare(strict_types=1);

namespace Stratum\Modules\Search;

use Stratum\Core\Block;

final class SearchBoxBlock implements Block
{
    private static int $instanceCounter = 0;

    /** @param array<string, mixed> $config */
    public function render(array $config): string
    {
        $id = 'strat-search-' . (++self::$instanceCounter);

        return '<div class="strat-header-dropdown">'
            . '<button type="button" class="strat-header-icon" data-dropdown-trigger="' . $id . '" title="Search" aria-label="Search">'
            . '<span aria-hidden="true">&#128269;</span>'
            . '</button>'
            . '<div class="strat-header-dropdown-panel" data-dropdown-panel="' . $id . '">'
            . '<form action="/search" method="get" style="display:flex;gap:0.5rem;">'
            . '<input type="text" name="q" placeholder="Search..." autofocus>'
            . '<button type="submit">Go</button>'
            . '</form>'
            . '</div>'
            . '</div>';
    }
}
