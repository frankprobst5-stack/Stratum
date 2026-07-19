<?php

declare(strict_types=1);

namespace Stratum\Modules\Sponsors;

use Stratum\Core\Block;

final class SponsorBlock implements Block
{
    public function __construct(private readonly SponsorService $service)
    {
    }

    /** @param array<string, mixed> $config */
    public function render(array $config): string
    {
        $sponsors = $this->service->listActive();
        if ($sponsors === []) {
            return '';
        }

        $logos = '';
        foreach ($sponsors as $sponsor) {
            $img = '<img src="' . e($sponsor['logo_url']) . '" alt="' . e($sponsor['name']) . '" style="max-height:48px;">';
            $logos .= '<a href="' . e(route('/sponsors/' . $sponsor['id'] . '/click')) . '" target="_blank" rel="noopener sponsored" style="display:inline-block;margin:0.5rem;">' . $img . '</a>';
        }

        return '<div class="strat-sponsor-strip" style="text-align:center;padding:0.75rem 0;">' . $logos . '</div>';
    }
}
