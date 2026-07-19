<?php

declare(strict_types=1);

namespace Stratum\Modules\Activity;

use Stratum\Core\Block;
use Stratum\Core\CardBlock;
use Stratum\Modules\Users\AuthService;

/** Lives in services/ (not blocks/) — ModuleManager::boot() only requires services/ and controllers/, same standing gotcha every other block class this session documents. */
final class ActivityFeedBlock implements Block, CardBlock
{
    private const DISPLAY_LIMIT = 8;

    public function __construct(
        private readonly ActivityService $activity,
        private readonly AuthService $auth
    ) {
    }

    /** @param array<string, mixed> $config */
    public function render(array $config): string
    {
        $items = array_slice($this->activity->recent(), 0, self::DISPLAY_LIMIT);
        if ($items === []) {
            return '';
        }

        $rows = '';
        foreach ($items as $item) {
            $actorName = 'Someone';
            if ($item['actor_id'] !== null) {
                $actor = $this->auth->findById((int) $item['actor_id']);
                $actorName = $actor['username'] ?? 'Unknown';
            }

            $titleHtml = $item['url'] !== null
                ? '<a href="' . e(route($item['url'])) . '" style="color:inherit;">' . e($item['title']) . '</a>'
                : e($item['title']);

            $rows .= '<li style="padding:0.35rem 0;border-bottom:1px solid var(--strat-card-border);font-size:0.85rem;">'
                . '<strong>' . e($actorName) . '</strong> ' . e($item['verb']) . ': ' . $titleHtml
                . '<div style="color:var(--strat-muted-text);font-size:0.75rem;">' . e((string) $item['created_at']) . '</div>'
                . '</li>';
        }

        return '<ul class="strat-activity-feed" style="list-style:none;margin:0;padding:0;">' . $rows . '</ul>';
    }

    public function cardTitle(): string
    {
        return 'Recent Activity';
    }

    public function cardIcon(): string
    {
        return "\u{26A1}";
    }

    public function cardAccent(): string
    {
        return 'orange';
    }

    public function viewAllUrl(): ?string
    {
        return '/activity';
    }
}
