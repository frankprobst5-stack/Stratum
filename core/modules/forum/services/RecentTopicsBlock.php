<?php

declare(strict_types=1);

namespace Stratum\Modules\Forum;

use Stratum\Core\CardBlock;
use Stratum\Core\ConfigurableBlock;

final class RecentTopicsBlock implements ConfigurableBlock, CardBlock
{
    public function __construct(private readonly ForumService $forum)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function configFields(): array
    {
        return [
            ['name' => 'limit', 'label' => 'Number of topics to show', 'type' => 'number', 'default' => 5],
        ];
    }

    /** @param array<string, mixed> $config */
    public function render(array $config): string
    {
        $limit = isset($config['limit']) ? max(1, (int) $config['limit']) : 5;
        $topics = $this->forum->listRecentTopics($limit);
        if ($topics === []) {
            return '';
        }

        $items = '';
        foreach ($topics as $topic) {
            $items .= '<li style="padding:0.35rem 0;border-bottom:1px solid var(--strat-card-border);font-size:0.85rem;">'
                . '<a href="' . e(route('/forum/topics/' . $topic['id'])) . '" style="text-decoration:none;color:inherit;font-weight:600;">' . e($topic['title']) . '</a>'
                . '<div style="color:var(--strat-muted-text);font-size:0.75rem;">in ' . e($topic['board_name']) . '</div>'
                . '</li>';
        }

        return '<ul class="strat-recent-topics" style="list-style:none;margin:0;padding:0;">' . $items . '</ul>';
    }

    public function cardTitle(): string
    {
        return 'Recent Forum Posts';
    }

    public function cardIcon(): string
    {
        return "\u{1F4AC}";
    }

    public function cardAccent(): string
    {
        return 'teal';
    }

    public function viewAllUrl(): ?string
    {
        return '/forum';
    }
}
