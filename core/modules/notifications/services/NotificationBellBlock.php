<?php

declare(strict_types=1);

namespace Stratum\Modules\Notifications;

use Stratum\Core\Auth;
use Stratum\Core\Block;
use Stratum\Core\Session;
use Stratum\Core\TemplateEngine;

/**
 * Lives in services/ (not a blocks/ subdir) because ModuleManager::boot()
 * only requires services/ and controllers/ — same standing gotcha ticker's
 * TickerBlock documented in Stage 4b.
 *
 * Renders a self-contained dropdown (trigger + panel), not just a link —
 * reuses layout.php's existing generic [data-dropdown-trigger]/
 * [data-dropdown-panel] click-handler, so no layout.php changes were
 * needed for the toggle behavior itself. The panel is server-rendered
 * here at page load (works with zero JS), then the embedded script keeps
 * the badge live via polling and refreshes the panel's content on open.
 */
final class NotificationBellBlock implements Block
{
    public function __construct(
        private readonly Auth $auth,
        private readonly NotificationService $notifications,
        private readonly TemplateEngine $templates,
        private readonly Session $session
    ) {
    }

    /** @param array<string, mixed> $config */
    public function render(array $config): string
    {
        $user = $this->auth->user();
        if ($user === null) {
            return '';
        }

        $userId = (int) $user['id'];
        $unread = $this->notifications->unreadCount($userId);
        $badgeStyle = $unread > 0 ? '' : ' style="display:none;"';
        $badgeText = $unread > 99 ? '99+' : (string) $unread;
        $badge = '<span class="strat-header-icon-badge" id="strat-notif-badge"' . $badgeStyle . '>' . $badgeText . '</span>';

        $panelHtml = $this->templates->render('notifications', 'panel', [
            'notifications' => array_slice($this->notifications->listForUser($userId), 0, 8),
            'csrfToken' => $this->session->csrfToken(),
        ]);

        return <<<HTML
            <div class="strat-header-dropdown">
                <button type="button" class="strat-header-icon" data-dropdown-trigger="notifications-bell" aria-label="Notifications, {$unread} unread">
                    <span aria-hidden="true">&#128276;</span>{$badge}
                </button>
                <div class="strat-header-dropdown-panel" data-dropdown-panel="notifications-bell" id="strat-notif-panel" style="width:18rem; padding:0;">
                    {$panelHtml}
                </div>
            </div>
            <script>
            (function () {
                var badge = document.getElementById('strat-notif-badge');
                var panel = document.getElementById('strat-notif-panel');
                var trigger = document.querySelector('[data-dropdown-trigger="notifications-bell"]');

                function refreshCount() {
                    fetch('/notifications/unread-count')
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            var n = data.unreadCount || 0;
                            if (n > 0) {
                                badge.textContent = n > 99 ? '99+' : String(n);
                                badge.style.display = '';
                            } else {
                                badge.style.display = 'none';
                            }
                        })
                        .catch(function () {});
                }

                function refreshPanel() {
                    fetch('/notifications/panel')
                        .then(function (r) { return r.text(); })
                        .then(function (html) { panel.innerHTML = html; })
                        .catch(function () {});
                }

                if (trigger) {
                    trigger.addEventListener('click', refreshPanel);
                }
                setInterval(refreshCount, 20000);
            })();
            </script>
            HTML;
    }
}
