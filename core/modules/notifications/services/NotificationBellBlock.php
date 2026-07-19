<?php

declare(strict_types=1);

namespace Stratum\Modules\Notifications;

use Stratum\Core\Auth;
use Stratum\Core\Block;

/**
 * Lives in services/ (not a blocks/ subdir) because ModuleManager::boot()
 * only requires services/ and controllers/ — same standing gotcha ticker's
 * TickerBlock documented in Stage 4b.
 */
final class NotificationBellBlock implements Block
{
    public function __construct(
        private readonly Auth $auth,
        private readonly NotificationService $notifications
    ) {
    }

    /** @param array<string, mixed> $config */
    public function render(array $config): string
    {
        $user = $this->auth->user();
        if ($user === null) {
            return '';
        }

        $unread = $this->notifications->unreadCount((int) $user['id']);
        $badge = $unread > 0
            ? '<span class="strat-header-icon-badge">' . ($unread > 99 ? '99+' : $unread) . '</span>'
            : '';

        return '<a href="/notifications" class="strat-header-icon" title="Notifications" aria-label="Notifications, ' . $unread . ' unread">'
            . '<span aria-hidden="true">&#128276;</span>' . $badge
            . '</a>';
    }
}
