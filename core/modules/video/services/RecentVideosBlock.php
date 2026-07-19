<?php

declare(strict_types=1);

namespace Stratum\Modules\Video;

use Stratum\Core\CardBlock;
use Stratum\Core\ConfigurableBlock;

final class RecentVideosBlock implements ConfigurableBlock, CardBlock
{
    public function __construct(private readonly VideoService $video)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function configFields(): array
    {
        return [
            ['name' => 'limit', 'label' => 'Number of videos to show', 'type' => 'number', 'default' => 5],
        ];
    }

    /** @param array<string, mixed> $config */
    public function render(array $config): string
    {
        $limit = isset($config['limit']) ? max(1, (int) $config['limit']) : 5;
        $videos = $this->video->listRecent($limit);
        if ($videos === []) {
            return '';
        }

        $items = '';
        foreach ($videos as $video) {
            $items .= '<li style="padding:0.35rem 0;border-bottom:1px solid var(--strat-card-border);font-size:0.85rem;">'
                . '<a href="' . e(route('/videos/' . $video['id'])) . '" style="text-decoration:none;color:inherit;font-weight:600;">' . e($video['title']) . '</a>'
                . '<div style="color:var(--strat-muted-text);font-size:0.75rem;">' . (int) $video['view_count'] . ' views</div>'
                . '</li>';
        }

        return '<ul class="strat-recent-videos" style="list-style:none;margin:0;padding:0;">' . $items . '</ul>';
    }

    public function cardTitle(): string
    {
        return 'Latest Videos';
    }

    public function cardIcon(): string
    {
        return "\u{25B6}\u{FE0F}";
    }

    public function cardAccent(): string
    {
        return 'red';
    }

    public function viewAllUrl(): ?string
    {
        return '/videos';
    }
}
