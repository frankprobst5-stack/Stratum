<?php

declare(strict_types=1);

namespace Stratum\Modules\Messages;

use Stratum\Core\Auth;
use Stratum\Core\Block;

final class MessagesIconBlock implements Block
{
    public function __construct(
        private readonly Auth $auth,
        private readonly MessagesService $messages
    ) {
    }

    /** @param array<string, mixed> $config */
    public function render(array $config): string
    {
        $user = $this->auth->user();
        if ($user === null) {
            return '';
        }

        $unread = $this->messages->unreadCount((int) $user['id']);
        $badge = $unread > 0
            ? '<span class="strat-header-icon-badge">' . ($unread > 99 ? '99+' : $unread) . '</span>'
            : '';

        return '<a href="' . e(route('/messages')) . '" class="strat-header-icon" title="Messages" aria-label="Messages, ' . $unread . ' unread">'
            . '<span aria-hidden="true">&#9993;</span>' . $badge
            . '</a>';
    }
}
