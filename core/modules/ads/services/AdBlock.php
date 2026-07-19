<?php

declare(strict_types=1);

namespace Stratum\Modules\Ads;

use Stratum\Core\ConfigurableBlock;

final class AdBlock implements ConfigurableBlock
{
    public function __construct(private readonly AdService $service)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function configFields(): array
    {
        return [
            ['name' => 'zone', 'label' => 'Ad zone (matches a banner\'s zone in /admin/ads)', 'type' => 'text', 'default' => ''],
        ];
    }

    /** @param array<string, mixed> $config */
    public function render(array $config): string
    {
        $zone = (string) ($config['zone'] ?? '');
        if ($zone === '') {
            return '';
        }

        $banner = $this->service->activeBannerForZone($zone);
        if ($banner === null) {
            return '';
        }

        $img = '<img src="' . e($banner['image_url']) . '" alt="' . e($banner['alt_text']) . '" style="max-width:100%;display:block;">';

        return '<div class="strat-ad-banner strat-ad-banner-' . e($zone) . '">'
            . '<a href="' . e(route('/ads/banners/' . $banner['id'] . '/click')) . '" target="_blank" rel="noopener sponsored">' . $img . '</a>'
            . '</div>';
    }
}
