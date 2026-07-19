<?php

declare(strict_types=1);

namespace Stratum\Modules\Messages;

use Stratum\Core\Auth;
use Stratum\Core\Block;

/**
 * Placeholder for real private messaging (Stage 9, deliberately deferred —
 * see docs/roadmap.md's Stage 9 chat design notes: PMs are decoupled from
 * chat rooms and scoped as their own later slice). This reserves the
 * header spot and links to an honest "coming soon" page rather than a
 * fake inbox or a 404 — badge count is always 0 since there's no real
 * data behind it yet. Swap the badge/href here for real unread-count
 * logic once the feature actually exists; the header markup itself won't
 * need to change.
 */
final class MessagesIconBlock implements Block
{
    public function __construct(private readonly Auth $auth)
    {
    }

    /** @param array<string, mixed> $config */
    public function render(array $config): string
    {
        if (!$this->auth->check()) {
            return '';
        }

        return '<a href="' . e(route('/messages')) . '" class="strat-header-icon" title="Messages" aria-label="Messages">'
            . '<span aria-hidden="true">&#9993;</span>'
            . '</a>';
    }
}
