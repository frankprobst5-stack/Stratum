<?php

declare(strict_types=1);

namespace Stratum\Modules\Tags;

use Stratum\Core\CardBlock;
use Stratum\Core\ConfigurableBlock;

final class TagCloudBlock implements ConfigurableBlock, CardBlock
{
    public function __construct(private readonly TagService $tags)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function configFields(): array
    {
        return [
            ['name' => 'limit', 'label' => 'Number of tags to show', 'type' => 'number', 'default' => 30],
        ];
    }

    /** @param array<string, mixed> $config */
    public function render(array $config): string
    {
        $limit = isset($config['limit']) ? max(1, (int) $config['limit']) : 30;
        $tags = $this->tags->popularTags($limit);
        if ($tags === []) {
            return '';
        }

        $maxCount = max(array_column($tags, 'count'));
        $links = '';
        foreach ($tags as $tag) {
            $size = $maxCount > 0 ? 0.8 + (($tag['count'] / $maxCount) * 0.7) : 0.8;
            $links .= '<a href="' . e(route('/tags/' . $tag['slug'])) . '" style="display:inline-block;margin:0.2rem 0.4rem;font-size:' . round($size, 2) . 'rem;text-decoration:none;color:var(--strat-accent);">' . e($tag['name']) . '</a>';
        }

        return '<div class="strat-tag-cloud">' . $links . '</div>';
    }

    public function cardTitle(): string
    {
        return 'Tags';
    }

    public function cardIcon(): string
    {
        return "\u{1F3F7}\u{FE0F}";
    }

    public function cardAccent(): string
    {
        return 'blue';
    }

    public function viewAllUrl(): ?string
    {
        return '/tags';
    }
}
