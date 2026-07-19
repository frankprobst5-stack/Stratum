<?php

declare(strict_types=1);

namespace Stratum\Modules\Comments;

use Stratum\Core\CardBlock;
use Stratum\Core\ConfigurableBlock;
use Stratum\Core\ContentResolver;
use Stratum\Modules\Users\AuthService;

final class RecentCommentsBlock implements ConfigurableBlock, CardBlock
{
    /** Over-fetch multiplier to compensate for rows ContentResolver can't resolve (gallery/video/downloads types) — see CommentService::listRecent()'s docblock. */
    private const FETCH_MULTIPLIER = 3;

    public function __construct(
        private readonly CommentService $comments,
        private readonly ContentResolver $resolver,
        private readonly AuthService $auth
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function configFields(): array
    {
        return [
            ['name' => 'limit', 'label' => 'Number of comments to show', 'type' => 'number', 'default' => 5],
        ];
    }

    /** @param array<string, mixed> $config */
    public function render(array $config): string
    {
        $limit = isset($config['limit']) ? max(1, (int) $config['limit']) : 5;
        $raw = $this->comments->listRecent($limit * self::FETCH_MULTIPLIER);

        $items = '';
        $shown = 0;
        foreach ($raw as $comment) {
            if ($shown >= $limit) {
                break;
            }

            $target = $this->resolver->resolve((string) $comment['commentable_type'], (int) $comment['commentable_id']);
            if ($target === null) {
                continue;
            }

            $author = $this->auth->findById((int) $comment['user_id']);
            $authorName = $author['username'] ?? 'Unknown';
            $snippet = mb_strimwidth((string) $comment['body'], 0, 80, '…');

            $items .= '<li style="padding:0.35rem 0;border-bottom:1px solid var(--strat-card-border);font-size:0.85rem;">'
                . '<strong>' . e($authorName) . '</strong> on '
                . '<a href="' . e(route($target['url'])) . '" style="color:inherit;">' . e($target['title']) . '</a>'
                . '<div style="color:var(--strat-muted-text);">' . e($snippet) . '</div>'
                . '</li>';

            $shown++;
        }

        if ($items === '') {
            return '';
        }

        return '<ul class="strat-recent-comments" style="list-style:none;margin:0;padding:0;">' . $items . '</ul>';
    }

    public function cardTitle(): string
    {
        return 'Recent Comments';
    }

    public function cardIcon(): string
    {
        return "\u{1F4AD}";
    }

    public function cardAccent(): string
    {
        return 'cyan';
    }

    public function viewAllUrl(): ?string
    {
        return null;
    }
}
