<?php

declare(strict_types=1);

namespace Stratum\Modules\Gallery;

use Stratum\Core\CardBlock;
use Stratum\Core\ConfigurableBlock;

final class GalleryHighlightsBlock implements ConfigurableBlock, CardBlock
{
    public function __construct(private readonly GalleryService $gallery)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function configFields(): array
    {
        return [
            ['name' => 'limit', 'label' => 'Number of photos to show', 'type' => 'number', 'default' => 4],
        ];
    }

    /** @param array<string, mixed> $config */
    public function render(array $config): string
    {
        $limit = isset($config['limit']) ? max(1, (int) $config['limit']) : 4;
        $photos = $this->gallery->listRecentPhotos($limit);
        if ($photos === []) {
            return '';
        }

        $items = '';
        foreach ($photos as $photo) {
            $items .= '<a href="' . e(route('/gallery/photos/' . $photo['id'])) . '" style="display:inline-block;margin:0.2rem;">'
                . '<img src="' . e(route('/gallery/photos/' . $photo['id'] . '/thumbnail')) . '" alt="' . e((string) ($photo['caption'] ?? '')) . '" style="width:70px;height:70px;object-fit:cover;border-radius:4px;">'
                . '</a>';
        }

        return '<div class="strat-gallery-highlights">' . $items . '</div>';
    }

    public function cardTitle(): string
    {
        return 'Gallery Highlights';
    }

    public function cardIcon(): string
    {
        return "\u{1F5BC}\u{FE0F}";
    }

    public function cardAccent(): string
    {
        return 'purple';
    }

    public function viewAllUrl(): ?string
    {
        return '/gallery';
    }
}
