<?php

declare(strict_types=1);

namespace Stratum\Modules\SiteStats;

use Stratum\Core\Block;
use Stratum\Core\CardBlock;

final class StatsBlock implements Block, CardBlock
{
    public function __construct(private readonly StatsService $stats)
    {
    }

    /** @param array<string, mixed> $config */
    public function render(array $config): string
    {
        $tiles = [
            ['label' => 'Members', 'value' => $this->stats->memberCount()],
            ['label' => 'New this week', 'value' => $this->stats->newMembersThisWeek()],
            ['label' => 'Comments this week', 'value' => $this->stats->commentsThisWeek()],
        ];

        $items = '';
        foreach ($tiles as $tile) {
            $items .= '<div>'
                . '<span class="strat-stat-value">' . (int) $tile['value'] . '</span>'
                . '<span class="strat-stat-label">' . e($tile['label']) . '</span>'
                . '</div>';
        }

        return '<div class="strat-stat-row">' . $items . '</div>';
    }

    public function cardTitle(): string
    {
        return 'Community Stats';
    }

    public function cardIcon(): string
    {
        return "\u{1F4CA}";
    }

    public function cardAccent(): string
    {
        return 'teal';
    }

    public function viewAllUrl(): ?string
    {
        return null;
    }
}
