<?php

declare(strict_types=1);

namespace Stratum\Modules\Presence;

use Stratum\Core\Block;
use Stratum\Core\CardBlock;

/**
 * Lives in services/ (not blocks/) — ModuleManager::boot() only requires
 * services/ and controllers/, same standing gotcha ticker's/notifications'
 * blocks documented. Also placed non-card in the `header` region (the
 * ticker strip) — CardBlock's title/icon/CTA just go unused there, only
 * the front-page column placement ($wrapInCards) ever renders them.
 */
final class WhosOnlineBlock implements Block, CardBlock
{
    public function __construct(private readonly PresenceService $presence)
    {
    }

    /** @param array<string, mixed> $config */
    public function render(array $config): string
    {
        $members = $this->presence->onlineMembers();
        $guestCount = $this->presence->guestCount();

        $names = implode(', ', array_map(
            static fn (array $m): string => e($m['username']),
            $members
        ));

        $summary = count($members) . ' member' . (count($members) === 1 ? '' : 's') . ', ' . $guestCount . ' guest' . ($guestCount === 1 ? '' : 's') . ' online';

        return '<div class="strat-whos-online" style="padding:0.5rem 1rem; font-size:0.85rem;">'
            . '<a href="/online" style="text-decoration:none; color:inherit;">' . e($summary) . '</a>'
            . ($names !== '' ? '<div style="color:var(--strat-muted-text);">' . $names . '</div>' : '')
            . '</div>';
    }

    public function cardTitle(): string
    {
        return 'Online Users';
    }

    public function cardIcon(): string
    {
        return "\u{1F7E2}";
    }

    public function cardAccent(): string
    {
        return 'green';
    }

    public function viewAllUrl(): ?string
    {
        return '/online';
    }
}
