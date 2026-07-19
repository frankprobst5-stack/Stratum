<?php

declare(strict_types=1);

namespace Stratum\Modules\OrgSpaces;

use Stratum\Core\Block;
use Stratum\Core\CardBlock;

/**
 * No "featured" flag/column exists on org_spaces_orgs (confirmed during
 * the Stage 8 block-library design pass) — picks one random active org
 * per render rather than adding new schema for a single block. If a real
 * "pin this org" need ever comes up, that's a bigger, separate feature.
 */
final class FeaturedClubBlock implements Block, CardBlock
{
    public function __construct(private readonly OrgSpaceService $orgs)
    {
    }

    /** @param array<string, mixed> $config */
    public function render(array $config): string
    {
        $orgs = $this->orgs->listOrgs(true);
        if ($orgs === []) {
            return '';
        }

        $org = $orgs[array_rand($orgs)];

        return '<div class="strat-featured-club">'
            . '<a href="' . e(route('/organizations/' . $org['slug'])) . '" style="text-decoration:none;color:inherit;font-weight:600;">' . e($org['name']) . '</a>'
            . (!empty($org['description']) ? '<div style="font-size:0.85rem;color:var(--strat-muted-text);">' . e(mb_strimwidth((string) $org['description'], 0, 120, '…')) . '</div>' : '')
            . '</div>';
    }

    public function cardTitle(): string
    {
        return 'Featured Chapter';
    }

    public function cardIcon(): string
    {
        return "\u{2B50}";
    }

    public function cardAccent(): string
    {
        return 'gold';
    }

    public function viewAllUrl(): ?string
    {
        return '/organizations';
    }
}
